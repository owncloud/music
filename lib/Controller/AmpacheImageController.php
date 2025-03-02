<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023 - 2025
 */

namespace OCA\Music\Controller;

use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileResponse;
use OCA\Music\Utility\AmpacheImageService;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\HttpUtil;
use OCA\Music\Utility\LibrarySettings;
use OCA\Music\Utility\PlaceholderImage;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class AmpacheImageController extends Controller {
	private AmpacheImageService $service;
	private CoverHelper $coverHelper;
	private LibrarySettings $librarySettings;
	private AlbumBusinessLayer $albumBusinessLayer;
	private ArtistBusinessLayer $artistBusinessLayer;
	private PlaylistBusinessLayer $playlistBusinessLayer;
	private Logger $logger;

	public function __construct(
			string $appname,
			IRequest $request,
			AmpacheImageService $service,
			CoverHelper $coverHelper,
			LibrarySettings $librarySettings,
			AlbumBusinessLayer $albumBusinessLayer,
			ArtistBusinessLayer $artistBusinessLayer,
			PlaylistBusinessLayer $playlistBusinessLayer,
			Logger $logger) {
		parent::__construct($appname, $request);
		$this->service = $service;
		$this->coverHelper = $coverHelper;
		$this->librarySettings = $librarySettings;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 * @CORS
	 */
	public function image(?string $token, ?string $object_id, string $object_type='album', ?int $size=null) : Response {
		if ($token === null) {
			// Workaround for Ample client which uses this kind of call to get the placeholder graphics
			$response = new FileResponse(PlaceholderImage::generateForResponse('?', $object_type, 200));
			HttpUtil::setClientCachingDays($response, 365);
			return $response;
		}

		$userId = $this->service->getUserForToken($token, $object_type, (int)$object_id);
		if ($userId === null) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'invalid token');
		}

		$businessLayer = $this->getBusinessLayer($object_type);
		if ($businessLayer === null) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, "invalid object_type $object_type");
		}

		try {
			$entity = $businessLayer->find((int)$object_id, $userId);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, "$object_type $object_id not found");
		}

		$coverImage = $this->coverHelper->getCover($entity, $userId, $this->librarySettings->getFolder($userId), $size);
		if ($coverImage === null) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, "$object_type $object_id has no cover image");
		}

		$response = new FileResponse($coverImage);
		HttpUtil::setClientCachingDays($response, 30);
		return $response;
	}

	private function getBusinessLayer(string $object_type) : ?BusinessLayer {
		switch ($object_type) {
			case 'album':		return $this->albumBusinessLayer;
			case 'artist':		return $this->artistBusinessLayer;
			case 'playlist':	return $this->playlistBusinessLayer;
			default:			return null;
		}
	}
}
