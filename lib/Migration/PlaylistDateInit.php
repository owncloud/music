<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class PlaylistDateInit implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->db = $connection;
		$this->config = $config;
	}

	public function getName() {
		return 'Set creation date for playlists without one';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$installedVersion = $this->config->getAppValue('music', 'installed_version');

		// Music version 0.14.1 added the `created` column to the playlists table
		if (\version_compare($installedVersion, '0.14.1', '<')) {
			$n = $this->setCreationDates();
			$output->info("This date was added as creation date for $n playlists");
		}
	}

	private function setCreationDates() {
		$now = new \DateTime();
		$sql = "UPDATE `*PREFIX*music_playlists` SET `created` = ? WHERE `created` IS NULL";
		$params = [$now->format('Y-m-d H:i:s')];
		return $this->db->executeUpdate($sql, $params);
	}
}
