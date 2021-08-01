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

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\PodcastChannelMapper;
use \OCA\Music\Db\PodcastChannel;

use \OCA\Music\Utility\Util;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method PodcastChannel find(int $stationId, string $userId)
 * @method PodcastChannel[] findAll(
 *			string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null,
 *			?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @method PodcastChannel[] findAllByName(
 *			string $name, string $userId, bool $fuzzy=false, int $limit=null, int $offset=null,
 *			?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 */
class PodcastChannelBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(PodcastChannelMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	public function create(string $userId, string $rssUrl, \SimpleXMLElement $xmlNode) : PodcastChannel {
		$channel = new PodcastChannel();

		// TODO

		return $this->mapper->insert($channel);
	}

}
