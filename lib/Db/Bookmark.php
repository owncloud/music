<?php declare(strict_types=1);

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
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getTrackId()
 * @method void setTrackId(int $trackId)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method string getComment()
 * @method void setComment(string $comment)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
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
