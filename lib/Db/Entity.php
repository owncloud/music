<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2023
 */

namespace OCA\Music\Db;

use OCP\IL10N;

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

	/**
	 * All entities have a non-empty human-readable name, although the exact name of the
	 * corresponding DB column varies and in some cases, the value may be technically
	 * empty but replaced with some localized place-holder text.
	 *
	 * The derived classes may override this as neeeded.
	 */
	public function getNameString(IL10N $l10n) : string {
		($l10n); // unused in this base implementation
		if (\property_exists($this, 'name')) {
			return $this->getName();
		} elseif (\property_exists($this, 'title')) {
			return $this->getTitle();
		} else {
			return 'UNIMPLEMENTED';
		}
	}
}
