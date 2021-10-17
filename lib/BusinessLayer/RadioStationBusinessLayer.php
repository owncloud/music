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

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\RadioStationMapper;
use OCA\Music\Db\RadioStation;

use OCA\Music\Utility\Util;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method RadioStation find(int $stationId, string $userId)
 * @method RadioStation[] findAll(string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null)
 * @method RadioStation[] findAllByName(string $name, string $userId, bool $fuzzy=false, int $limit=null, int $offset=null)
 * @phpstan-extends BusinessLayer<RadioStation>
 */
class RadioStationBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(RadioStationMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	public function create(string $userId, ?string $name, string $streamUrl, ?string $homeUrl = null) : RadioStation {
		$station = new RadioStation();

		if ($streamUrl !== null && \strlen($streamUrl) > 2048) {
			throw new BusinessLayerException("URL maximum length (2048) exceeded: $streamUrl");
		}

		if ($homeUrl !== null && \strlen($homeUrl) > 2048) {
			throw new BusinessLayerException("URL maximum length (2048) exceeded: $homeUrl");
		}

		$station->setUserId($userId);
		$station->setName(Util::truncate($name, 256));
		$station->setStreamUrl($streamUrl);
		$station->setHomeUrl($homeUrl);

		return $this->mapper->insert($station);
	}

}
