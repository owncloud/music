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


class HelperTest extends \PHPUnit_Framework_TestCase {

	/** @var Connection */
	private $db;

	protected function setUp(){
		/** @var Connection db */
		$this->db = \OC::$server->getDatabaseConnection();
	}

	protected function loadData($filename) {
		$filename = join(DIRECTORY_SEPARATOR, array(dirname(__DIR__), 'data', $filename));
		if(!file_exists($filename)) {
			throw new \Exception("Can't find file $filename to load data.");
		}

		$data = json_decode(file_get_contents($filename));

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

	protected function checkForEmptyTables($user) {
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
