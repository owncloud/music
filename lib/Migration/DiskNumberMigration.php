<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2024
 */

namespace OCA\Music\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DiskNumberMigration implements IRepairStep {

	private IDBConnection $db;
	private IConfig $config;
	private array $obsoleteAlbums;
	private array $mergeFailureAlbums;

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->db = $connection;
		$this->config = $config;
		$this->obsoleteAlbums = [];
		$this->mergeFailureAlbums = [];
	}

	public function getName() {
		return 'Combine multi-disk albums and store disk numbers per track';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$installedVersion = $this->config->getAppValue('music', 'installed_version');

		if (\version_compare($installedVersion, '0.13.0', '<=')) {
			try {
				$this->executeMigrationSteps($output);
			} catch (\Exception $e) {
				$output->warning('Unexpected exception ' . \get_class($e) . ' during Music disk-number-migration. ' .
								'The music DB may need to be rebuilt.');
			}
		}
	}

	private function executeMigrationSteps(IOutput $output) {
		$n = $this->copyDiskNumberToTracks();
		$output->info("$n tracks were updated with a disk number");

		$n = $this->combineMultiDiskAlbums();
		$output->info("$n tracks were assigned to new albums when combining multi-disk albums");

		$n = $this->removeObsoleteAlbums();
		$output->info("$n obsolete album entries were removed from the database");

		$n = $this->reEvaluateAlbumHashes();
		$output->info("$n albums were updated with new hashes");

		$n = $this->removeAlbumsWhichFailedMerging();
		$output->info("$n albums were removed because merging them failed; these need to be re-scanned by the user");

		$n = $this->removeDiskNumbersFromAlbums();
		$output->info("obsolete disk number field was nullified in $n albums");
	}

	/**
	 * Copy disk numbers from the albums table to the tracks table
	 */
	private function copyDiskNumberToTracks() : int {
		$sql = 'UPDATE `*PREFIX*music_tracks` '.
				'SET `disk` = (SELECT `disk` '.
				'              FROM `*PREFIX*music_albums` '.
				'              WHERE `*PREFIX*music_tracks`.`album_id` = `*PREFIX*music_albums`.`id`) '.
				'WHERE `disk` IS NULL';
		return $this->db->executeUpdate($sql);
	}

	/**
	 * Move all tracks belonging to separate disks of the same album title to the
	 * album entity matching the first of those disks. The album entities matching
	 * the rest of the disks become obsolete.
	 */
	private function combineMultiDiskAlbums() : int {
		$sql = 'SELECT `id`, `user_id`, `album_artist_id`, `name` '.
				'FROM `*PREFIX*music_albums` '.
				'ORDER BY `user_id`, `album_artist_id`, LOWER(`name`)';

		$rows = $this->db->executeQuery($sql)->fetchAll();

		$affectedTracks = 0;
		$prevId = null;
		$prevUser = null;
		$prevArtist = null;
		$prevName = null;
		foreach ($rows as $row) {
			$id = $row['id'];
			$user = $row['user_id'];
			$artist = $row['album_artist_id'];
			$name = isset($row['name']) ? \mb_strtolower($row['name']) : null;

			if ($user === $prevUser && $artist === $prevArtist && $name === $prevName) {
				// another disk of the same album => merge
				$affectedTracks += $this->moveTracksBetweenAlbums($id, $prevId);
				$this->obsoleteAlbums[] = $id;
			} else {
				$prevId = $id;
				$prevUser = $user;
				$prevArtist = $artist;
				$prevName = $name;
			}
		}

		return $affectedTracks;
	}

	/**
	 * Move all tracks from the source album entity to the destination album entity
	 * @param int $sourceAlbum ID
	 * @param int $destinationAlbum ID
	 */
	private function moveTracksBetweenAlbums($sourceAlbum, $destinationAlbum) : int {
		$sql = 'UPDATE `*PREFIX*music_tracks` '.
				'SET `album_id` = ? '.
				'WHERE `album_id` = ?';
		return $this->db->executeUpdate($sql, [$destinationAlbum, $sourceAlbum]);
	}

	/**
	 * Delete from the albums table those rows which were made obsolete by the previous steps
	 */
	private function removeObsoleteAlbums() : int {
		$count = \count($this->obsoleteAlbums);

		if ($count > 0) {
			$sql = 'DELETE FROM `*PREFIX*music_albums` '.
					'WHERE `id` IN '. $this->questionMarks($count);
			$count = $this->db->executeUpdate($sql, $this->obsoleteAlbums);
		}

		return $count;
	}

	/**
	 * Recalculate the hashes for all albums in the table. The disk number is no longer part
	 * of the calculation schema.
	 */
	private function reEvaluateAlbumHashes() : int {
		$sql = 'SELECT `id`, `name`, `album_artist_id` '.
				'FROM `*PREFIX*music_albums`';
		$rows = $this->db->executeQuery($sql)->fetchAll();

		$affectedRows = 0;
		foreach ($rows as $row) {
			$lowerName = \mb_strtolower($row['name'] ?? '');
			$artist = $row['album_artist_id'];
			$hash = \hash('md5', "$lowerName|$artist");

			try {
				$affectedRows += $this->db->executeUpdate(
						'UPDATE `*PREFIX*music_albums` SET `hash` = ? WHERE `id` = ?',
						[$hash, $row['id']]
				);
			} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException | \OCP\DB\Exception $e) {
				$this->mergeFailureAlbums[] = $row['id'];
			}
		}

		return $affectedRows;
	}

	/**
	 * Remove any albums which should have got merged to another albums, but for some
	 * reason this has not happened. Remove also the contained tracks. The user shall
	 * be prompted to rescan these problematic albums/tracks when (s)he opens the Music
	 * app.
	 */
	private function removeAlbumsWhichFailedMerging() : int {
		$count = \count($this->mergeFailureAlbums);

		if ($count > 0) {
			$sql = 'DELETE FROM `*PREFIX*music_albums` '.
					'WHERE `id` IN '. $this->questionMarks($count);
			$count = $this->db->executeUpdate($sql, $this->mergeFailureAlbums);

			$sql = 'DELETE FROM `*PREFIX*music_tracks` '.
					'WHERE `album_id` IN '. $this->questionMarks($count);
			$this->db->executeUpdate($sql, $this->mergeFailureAlbums);
		}

		return $count;
	}

	/**
	 * Set all disk numbers stored in the albums table as NULL.
	 */
	private function removeDiskNumbersFromAlbums() : int {
		$sql = 'UPDATE `*PREFIX*music_albums` SET `disk` = NULL';
		return $this->db->executeUpdate($sql);
	}

	/**
	 * helper creating a string like '(?,?,?)' with the specified number of elements
	 * @param int $count
	 */
	private function questionMarks(int $count) : string {
		$questionMarks = [];
		for ($i = 0; $i < $count; $i++) {
			$questionMarks[] = '?';
		}
		return '(' . \implode(',', $questionMarks) . ')';
	}
}
