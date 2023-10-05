<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2023
 */

namespace OCA\Music\Db;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string getStreamUrl()
 * @method setStreamUrl(string $url)
 * @method string getHomeUrl()
 * @method setHomeUrl(string $url)
 */
class RadioStation extends Entity {
	public $name;
	public $streamUrl;
	public $homeUrl;

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

	public function toAmpacheApi() : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'url' => $this->getStreamUrl(),
			'site_url' => $this->getHomeUrl()
		];
	}
}
