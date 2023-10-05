<?php declare(strict_types=1);

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

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method string getToken()
 * @method setToken(string $token)
 * @method int getExpiry()
 * @method setExpiry(int $expiry)
 * @method ?string getApiVersion()
 * @method setApiVersion(?string $version)
 * @method int getAmpacheUserId()
 * @method setAmpacheUserId(int $id)
 */
class AmpacheSession extends Entity {
	public $userId;
	public $token;
	public $expiry;
	public $apiVersion;
	public $ampacheUserId;

	public function __construct() {
		$this->addType('expiry', 'int');
		$this->addType('ampacheUserId', 'int');
	}
}
