<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Service;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\AppFramework\Db\UniqueConstraintViolationException;
use OCA\Music\BusinessLayer\Library;
use OCA\Music\Db\Cache;

use OCP\ICache;

/**
 * Utility to build and cache the monolithic json data describing the whole music library.
 *
 * There has to be a logged-in user to use this class, the userId is injected via the class
 * constructor.
 *
 * This class utilizes two caching mechanism: file-backed \OCP\ICache and database-backed
 * \OCA\Music\Db\Cache. The actual json data is stored in the former and a hash of the data
 * is stored into the latter. The hash is used as a flag indicating that the data is valid.
 * The rationale of this design is that the \OCP\ICache can be used only for the logged-in
 * user, but we must be able to invalidate the cache also in cases when the affected user is
 * not logged in (in FileHooks, ShareHooks, occ commands). On the other hand, depending on
 * the database configuration, the json data may be too large to store it to \OCA\Music\Db\Cache
 * (with tens of thousands of tracks, the size of the json may be more than 10 MB and the
 * DB may be configured with maximum object size of e.g. 1 MB).
 */
class CollectionService {
	private Library $library;
	private ICache $fileCache;
	private Cache $dbCache;
	private Logger $logger;
	private string $userId;

	public function __construct(
			Library $library,
			ICache $fileCache,
			Cache $dbCache,
			Logger $logger,
			?string $userId) {
		$this->library = $library;
		$this->fileCache = $fileCache;
		$this->dbCache = $dbCache;
		$this->logger = $logger;
		$this->userId = $userId ?? ''; // TODO: null makes no sense but we need it because ApiController may be constructed for public covers without a user
	}

	public function getJson() : string {
		$collectionJson = $this->getCachedJson();

		if ($collectionJson === null) {
			$collectionJson = \json_encode($this->library->toCollection($this->userId));
			try {
				$this->addJsonToCache($collectionJson);
			} catch (UniqueConstraintViolationException $ex) {
				$this->logger->log("Race condition: collection.json for user {$this->userId} ".
						"cached twice, ignoring latter.", 'warn');
			}
		}

		return $collectionJson;
	}

	public function getCachedJsonHash() : ?string {
		return $this->dbCache->get($this->userId, 'collection');
	}

	private function getCachedJson() : ?string {
		$json = null;
		$hash = $this->dbCache->get($this->userId, 'collection');
		if ($hash !== null) {
			$json = $this->fileCache->get('music_collection.json');
			if ($json === null) {
				$this->logger->log("Inconsistent collection state for user {$this->userId}: ".
						"Hash found from DB-backed cache but data not found from the ".
						"file-backed cache. Removing also the hash.", 'debug');
				$this->dbCache->remove($this->userId, 'collection');
			}
		}
		return $json;
	}

	private function addJsonToCache(string $json) : void {
		$hash = \hash('md5', $json);
		$this->dbCache->add($this->userId, 'collection', $hash);
		$this->fileCache->set('music_collection.json', $json, 5*365*24*60*60);
	}
}
