<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024, 2025
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Db\PodcastChannel;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileResponse;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\HttpUtil;

class CoverApiController extends Controller {

	private IURLGenerator $urlGenerator;
	private IRootFolder $rootFolder;
	private ArtistBusinessLayer $artistBusinessLayer;
	private AlbumBusinessLayer $albumBusinessLayer;
	private PodcastChannelBusinessLayer $podcastChannelBusinessLayer;
	private CoverHelper $coverHelper;
	private ?string $userId;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								IRootFolder $rootFolder,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								PodcastChannelBusinessLayer $podcastChannelBusinessLayer,
								CoverHelper $coverHelper,
								?string $userId, // null if this gets called after the user has logged out or on a public page
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->urlGenerator = $urlGenerator;
		$this->rootFolder = $rootFolder;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->podcastChannelBusinessLayer = $podcastChannelBusinessLayer;
		$this->coverHelper = $coverHelper;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function albumCover(int $albumId, $originalSize, $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$album = $this->albumBusinessLayer->find($albumId, $userId);
			return $this->cover($album, $userId, $originalSize);
		} catch (BusinessLayerException | \OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function artistCover(int $artistId, $originalSize, $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$artist = $this->artistBusinessLayer->find($artistId, $userId);
			return $this->cover($artist, $userId, $originalSize);
		} catch (BusinessLayerException | \OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function podcastCover(int $channelId, $originalSize, $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$channel = $this->podcastChannelBusinessLayer->find($channelId, $userId);
			return $this->cover($channel, $userId, $originalSize);
		} catch (BusinessLayerException | \OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function cachedCover(string $hash, ?string $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$coverData = $this->coverHelper->getCoverFromCache($hash, $userId);
			if ($coverData === null) {
				throw new \OutOfBoundsException("Cover with hash $hash not found");
			}
			$response =  new FileResponse($coverData);
			// instruct also the client-side to cache the result, this is safe
			// as the resource URI contains the image hash
			HttpUtil::setClientCachingDays($response, 365);
			return $response;
		} catch (\OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param Artist|Album|PodcastChannel $entity
	 */
	private function cover($entity, string $userId, $originalSize) {
		$originalSize = \filter_var($originalSize, FILTER_VALIDATE_BOOLEAN);
		$userFolder = $this->rootFolder->getUserFolder($userId);

		if ($originalSize) {
			// cover requested in original size, without scaling or cropping
			$cover = $this->coverHelper->getCover($entity, $userId, $userFolder, CoverHelper::DO_NOT_CROP_OR_SCALE);
			if ($cover !== null) {
				return new FileResponse($cover);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			}
		} else {
			$coverAndHash = $this->coverHelper->getCoverAndHash($entity, $userId, $userFolder);

			if ($coverAndHash['hash'] !== null && $this->userId !== null) {
				// Cover is in cache. Return a redirection response so that the client
				// will fetch the content through a cacheable route.
				// The redirection is not used in case this is a call from the Firefox mediaSession API with not
				// logged in user.
				$link = $this->urlGenerator->linkToRoute('music.coverApi.cachedCover', ['hash' => $coverAndHash['hash']]);
				return new RedirectResponse($link);
			} elseif ($coverAndHash['data'] !== null) {
				return new FileResponse($coverAndHash['data']);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			}
		}
	}
}
