<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2025
 */

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\MatchMode;
use OCA\Music\Db\RadioStationMapper;
use OCA\Music\Db\RadioStation;
use OCA\Music\Db\SortBy;
use OCA\Music\Utility\StringUtil;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method RadioStation find(int $stationId, string $userId)
 * @method RadioStation[] findAll(string $userId, int $sortBy=SortBy::Name, ?int $limit=null, ?int $offset=null)
 * @method RadioStation[] findAllByName(string $name, string $userId, int $matchMode=MatchMode::Exact, ?int $limit=null, ?int $offset=null)
 * @property RadioStationMapper $mapper
 * @extends BusinessLayer<RadioStation>
 */
class RadioStationBusinessLayer extends BusinessLayer {
	private Logger $logger;

	public function __construct(RadioStationMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->logger = $logger;
	}

	/**
	 * @throws \DomainException if one of the provided URLs is overly long
	 */
	public function create(string $userId, ?string $name, string $streamUrl, ?string $homeUrl = null) : RadioStation {
		$station = new RadioStation();

		if (\strlen($streamUrl) > 2048) {
			throw new \DomainException("URL maximum length (2048) exceeded: $streamUrl");
		}

		if ($homeUrl !== null && \strlen($homeUrl) > 2048) {
			throw new \DomainException("URL maximum length (2048) exceeded: $homeUrl");
		}

		$station->setUserId($userId);
		$station->setName(StringUtil::truncate($name, 256));
		$station->setStreamUrl($streamUrl);
		$station->setHomeUrl($homeUrl);

		return $this->mapper->insert($station);
	}

	/**
	 * Modify an existing radio station. Null-valued arguments are ignored and the corresponding properties are not modified.
	 * @throws BusinessLayerException if the station does not exist
	 * @throws \DomainException if one of the provided URLs is overly long
	 */
	public function updateStation(int $id, string $userId, ?string $name = null, ?string $streamUrl = null, ?string $homeUrl = null) : RadioStation {
		$station = $this->find($id, $userId);

		if ($name !== null) {
			$station->setName(StringUtil::truncate($name, 256));
		}
		if ($streamUrl !== null) {
			if (\strlen($streamUrl) > 2048) {
				throw new \DomainException("URL maximum length (2048) exceeded: $streamUrl");
			}
			$station->setStreamUrl($streamUrl);
		}
		if ($homeUrl !== null) {
			if (\strlen($homeUrl) > 2048) {
				throw new \DomainException("URL maximum length (2048) exceeded: $homeUrl");
			}
			$station->setHomeUrl($homeUrl);
		}

		return $this->mapper->update($station);
	}
}
