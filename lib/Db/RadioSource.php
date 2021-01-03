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

use \OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getUrl()
 * @method setUrl(string $url)
 * @method string getHash()
 * @method void setHash(string $hash)
 * @method string getAdded()
 * @method setAdded(string $timestamp)
 */
class RadioSource extends Entity {
	public $userId;
	public $url;
	public $hash;
	public $added;

	public function toApi() : array {
		return [
			'id' => $this->getId(),
			'url' => $this->getUrl(),
			'added' => $this->getAdded()
		];
	}
}
