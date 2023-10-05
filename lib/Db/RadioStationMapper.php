<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2023
 */

namespace OCA\Music\Db;

use OCP\IConfig;
use OCP\IDBConnection;

/**
 * @method RadioStation findEntity(string $sql, array $params)
 * @phpstan-extends BaseMapper<RadioStation>
 */
class RadioStationMapper extends BaseMapper {
	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, $config, 'music_radio_stations', RadioStation::class, 'name');
	}

	/**
	 * @return RadioStation
	 */
	public function findByStreamUrl(string $url, string $userId) : RadioStation {
		$sql = $this->selectUserEntities("`stream_url` = ?");
		return $this->findEntity($sql, [$userId, $url]);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 */
	protected function findUniqueEntity(Entity $station) : Entity {
		// The radio_stations table has no unique constraints, and hence, this function
		// should never be called.
		throw new \BadMethodCallException('not supported');
	}
}
