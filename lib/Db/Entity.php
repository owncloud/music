<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
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
	 * The derived classes may override this as needed.
	 */
	public function getNameString(IL10N $l10n) : string {
		($l10n); // @phpstan-ignore-line // unused in this base implementation

		if (\property_exists($this, 'name')) {
			return $this->name ?? '';
		} elseif (\property_exists($this, 'title')) {
			return $this->title ?? '';
		} else {
			return 'UNIMPLEMENTED';
		}
	}
}
