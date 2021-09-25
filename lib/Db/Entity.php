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

/**
 * Base class for all the entities belonging to the data model of the Music app
 * 
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 */
class Entity extends \OCP\AppFramework\Db\Entity {
	public $userId;
	public $created;
	public $updated;
}
