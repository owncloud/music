<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
 */

namespace OCA\Music\Command;

use OCA\Music\AppFramework\Core\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ResetDatabase extends Command {

	/** @var Db */
	private $db;

	public function __construct(Db $db) {
		$this->db = $db;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:reset-database')
			->setDescription('will drop all metadata gathered by the music app (artists, albums, tracks)')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'specify the user'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'use all known users'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getOption('all')) {
			$output->writeln("Drop tables for <info>all users</info>");
			$this->dropTables();
		} else {
			$users = $input->getArgument('user_id');
			foreach($users as $user) {
				$output->writeln("Drop tables for <info>$user</info>");
				$this->dropTables($user);
			}
		}

		$output->writeln("Clean up relations (album/artist and playlist/track)");
		$this->cleanupRelation();
	}

	private function dropTables($userID=null) {
		$tables = array('tracks', 'albums', 'artists');
		foreach($tables as $table) {
			$sql = 'DELETE FROM `*PREFIX*music_' . $table . '` ';
			$params = array();
			if($userID) {
				$sql .= 'WHERE `user_id` = ?';
				$params[] = $userID;
			}
			$query = $this->db->prepareQuery($sql);
			$query->execute($params);
		}
	}

	private function cleanupRelation() {
		$sql = 'DELETE FROM `*PREFIX*music_album_artists` ' .
			'WHERE `album_id` NOT IN (SELECT `id` FROM `*PREFIX*music_albums`) ' .
			'OR `artist_id` NOT IN (SELECT `id` FROM `*PREFIX*music_artists`)';
		$this->db->prepareQuery($sql)->execute();

		$sql = 'DELETE FROM `*PREFIX*music_playlist_tracks` ' .
			'WHERE `track_id` NOT IN (SELECT `id` FROM `*PREFIX*music_tracks`)';
		$this->db->prepareQuery($sql)->execute();
	}

}
