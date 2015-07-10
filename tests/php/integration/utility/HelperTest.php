<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Utility;

use Doctrine\DBAL\Connection;
use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Album;


class HelperTest extends \PHPUnit_Framework_TestCase {

	/** @var Connection */
	private $db;

	protected function setUp(){
		/** @var Connection db */
		$this->db = \OC::$server->getDatabaseConnection();
	}

	/**
	 * TODO: remove with drop of stable7 support
	 */
	protected function loadDataStable7($data) {
		foreach($data as $table => $dataSets) {
			foreach($dataSets as $dataSet) {
				$columns = array();
				$values = array();
				$placeholder = array();

				foreach($dataSet as $column => $value) {
					$columns[] = '`' . $column . '`';
					$values[] = $value;
					$placeholder[] = '?';
				}

				$columns = join(', ', $columns);
				$placeholder = join(', ', $placeholder);

				$sql = 'INSERT INTO `*PREFIX*' . $table . '` (' . $columns . ') VALUES (' . $placeholder . ')';
				$stmt = $this->db->prepare($sql);
				$stmt->execute($values);
			}
		}
	}

	protected function loadData($filename) {
		$filename = join(DIRECTORY_SEPARATOR, array(dirname(__DIR__), 'data', $filename));
		if(!file_exists($filename)) {
			throw new \Exception("Can't find file $filename to load data.");
		}

		$data = json_decode(file_get_contents($filename));

		if(version_compare(implode('.', \OCP\Util::getVersion()), '7.8', '<=')) {
			$this->loadDataStable7($data);
			return;
		}

		foreach($data as $table => $dataSets) {
			foreach($dataSets as $dataSet) {
				$qb = $this->db->createQueryBuilder();
				$q = $qb->insert('`*PREFIX*' . $table . '`');

				foreach($dataSet as $column => $value) {
					$q->setValue('`' . $column . '`', $qb->createNamedParameter($value));
				}

				$q->execute();
			}
		}
	}

	/**
	 * TODO: remove with drop of stable7 support
	 */
	protected function checkForEmptyTablesStable7($user) {
		$tables = array(
			'music_artists',
			'music_albums',
			'music_tracks',
		);

		foreach($tables as $table) {
			$sql = 'SELECT COUNT(*) FROM `*PREFIX*' . $table . '` WHERE `user_id` = ?';
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($user));
			$row = $stmt->fetch();
			$count = $row['COUNT(*)'];

			$this->assertEquals(0, $count);
		}

		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_album_artists` `album_artists` JOIN `*PREFIX*music_albums` `albums` ON `album_artists`.`album_id` = `albums`.`id` WHERE `user_id` = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($user));
		$row = $stmt->fetch();
		$count = $row['COUNT(*)'];

		$this->assertEquals(0, $count);
	}

	protected function checkForEmptyTables($user) {
		if(version_compare(implode('.', \OCP\Util::getVersion()), '7.8', '<=')) {
			$this->checkForEmptyTablesStable7($user);
			return;
		}

		$tables = array(
			'music_artists',
			'music_albums',
			'music_tracks',
		);

		foreach($tables as $table) {
			$qb = $this->db->createQueryBuilder();
			$qb->select('COUNT(*)')
				->from('`*PREFIX*' . $table . '`')
				->where('`user_id` = :user_id')
				->setParameter(':user_id', $user);
			$stmt = $qb->execute();
			$row = $stmt->fetch();
			$count = $row['COUNT(*)'];

			$this->assertEquals(0, $count);
		}

		$qb = $this->db->createQueryBuilder();
		$qb->select('COUNT(*)')
			->from('`*PREFIX*music_album_artists`', '`album_artists`')
			->join('`album_artists`', '`*PREFIX*music_albums`', '`albums`', '`album_artists`.`album_id` = `albums`.`id`')
			->where('`user_id` = :user_id')
			->setParameter(':user_id', $user);
		$stmt = $qb->execute();
		$row = $stmt->fetch();
		$count = $row['COUNT(*)'];

		$this->assertEquals(0, $count);
	}

	public function testCleanup(){
		$user = 'integration';
		$this->checkForEmptyTables($user);
		$this->loadData('HelperCleanupData.json');

		$helper = new Helper($this->db);
		$helper->cleanUp();
		$this->checkForEmptyTables($user);
	}
}
