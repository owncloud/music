<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gavin E <no.emai@address.for.me>
 * @copyright Gavin E 2020
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
}
