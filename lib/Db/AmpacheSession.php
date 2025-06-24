<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2023 - 2025
 */

namespace OCA\Music\Db;

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
class AmpacheSession extends \OCP\AppFramework\Db\Entity {
	public string $userId = '';
	public string $token = '';
	public int $expiry = 0;
	public ?string $apiVersion = null;
	public int $ampacheUserId = 0;

	public function __construct() {
		$this->addType('expiry', 'int');
		$this->addType('ampacheUserId', 'int');
	}
}
