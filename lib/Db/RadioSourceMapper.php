<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;
use \OCP\IDBConnection;

/**
 * @method RadioSource findEntity(string $sql, array $params)
 */
class RadioSourceMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_radio_sources', '\OCA\Music\Db\RadioSource', 'url');
	}

	public function findByUrl(string $url, string $userId) : RadioSource {
		$sql = $this->selectUserEntities("`url` = ?");
		return $this->findEntity($sql, [$userId, $url]);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param RadioSource $source
	 * @return RadioSource
	 */
	protected function findUniqueEntity(Entity $source) : Entity {
		return $this->findByUrl($source->getUrl());
	}
}
