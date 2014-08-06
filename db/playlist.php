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

namespace OCA\Music\Db;

use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method setName(string $name)
 * @method int[] getTrackIds()
 * @method setTrackIds(int[] $trackIds)
 * @method string getUserId()
 * @method setUserId(string $userId)
 */
class Playlist extends Entity {

	public $name;
	public $userId;
	public $trackIds = array();

	public function toAPI() {
		return array(
			'name' => $this->getName(),
			'trackIds' => $this->getTrackIds(),
			'id' => $this->getId(),
		);
	}

}
