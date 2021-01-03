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

use \OCA\Music\Db\RadioSourceMapper;
use \OCA\Music\Db\RadioSource;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method RadioSource find(int $sourceId, string $userId)
 * @method RadioSource[] findAll(string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null)
 * @method RadioSource[] findAllByName(string $name, string $userId, bool $fuzzy=false, int $limit=null, int $offset=null)
 */
class RadioSourceBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(RadioSourceMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	public function addIfNotExists(string $userId, string $url) : ?RadioSource {
		$source = new RadioSource();

		if ($url === null) {
			throw new BusinessLayerException('URL must not be null');
		} elseif (\strlen($url) > 2048) {
			throw new BusinessLayerException("URL maximum length (2048) exceeded: $url");
		}

		// Strip down unnecessary parts of the url, leaving only scheme, host, and possibly port.
		// This part of the URL is case insensitive, so store everything consistently in lower case.
		$urlParts = \parse_url($url);
		$url = \mb_strtolower("{$urlParts['scheme']}://{$urlParts['host']}");
		if (isset($urlParts['port'])) {
			$url .= ':' . $urlParts['port'];
		}

		$source->setUserId($userId);
		$source->setUrl($url);

		$now = new \DateTime();
		$source->setAdded($now->format(RadioSourceMapper::SQL_DATE_FORMAT));

		// Generate hash from the url to prevent duplicates, the url field itself is too long to be used for unique index
		$source->setHash(\hash('md5', $url));

		try {
			return $this->mapper->insert($source);
		} catch (UniqueConstraintViolationException $ex) {
			$this->logger->log("The radio source URL $url was already present", 'debug');
			return null;
		}
	}

}
