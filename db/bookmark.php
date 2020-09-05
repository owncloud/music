<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gavin E <no.emai@address.for.me>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Gavin E 2020
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;

/**
 */
class Bookmark extends Entity {
	public $userId;
	public $trackId;
	public $position;
	public $comment;
	public $created;
	public $updated;

	public function __construct() {
		$this->addType('trackId', 'int');
		$this->addType('position', 'int');
	}
}
