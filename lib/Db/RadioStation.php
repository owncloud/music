<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getStreamUrl()
 * @method setStreamUrl(string $url)
 * @method string getHomeUrl()
 * @method setHomeUrl(string $url)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 */
class RadioStation extends Entity {
	public $userId;
	public $name;
	public $streamUrl;
	public $homeUrl;
	public $created;
	public $updated;

	public function toApi() : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'stream_url' => $this->getStreamUrl(),
			'home_url' => $this->getHomeUrl(),
			'created' => $this->getCreated(),
			'updated' => $this->getUpdated()
		];
	}
}
