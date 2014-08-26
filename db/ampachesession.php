<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Db;

use \OCA\Music\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method string getToken()
 * @method setToken(string $token)
 * @method int getExpiry()
 * @method setExpiry(int $expiry)
 */
class AmpacheSession extends Entity {

	public $userId;
	public $token;
	public $expiry;

	public function __construct(){
		$this->addType('expiry', 'int');
	}
}
