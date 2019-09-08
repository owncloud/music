<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;

use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\Util;

class SubsonicController extends Controller {
	const API_VERSION = '1.4.0';

	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $playlistBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;
	private $coverHelper;
	private $logger;
	private $userId;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								$rootFolder,
								CoverHelper $coverHelper,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->library = $library;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;

		// used to deliver actual media file
		$this->rootFolder = $rootFolder;

		$this->coverHelper = $coverHelper;
		$this->logger = $logger;
	}

	/**
	 * Called by the middleware once the user credentials have been checked
	 * @param string $userId
	 */
	public function setAuthenticatedUser($userId) {
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @SubsonicAPI
	 */
	public function handleRequest($method) {
		// Allow calling all methods with or without the postfix ".view"
		if (Util::endsWith($method, ".view")) {
			$method = \substr($method, 0, -\strlen(".view"));
		}

		// Allow calling any functions annotated to be part of the API, except for
		// recursive call back to this dispatcher function.
		if ($method !== 'handleRequest' && \method_exists($this, $method)) {
			$annotationReader = new MethodAnnotationReader($this, $method);
			if ($annotationReader->hasAnnotation('SubsonicAPI')) {
				return $this->$method();
			}
		}

		$this->logger->log("Request $method not supported", 'warn');
		return $this->subsonicErrorResponse(70, "Requested action $method is not supported");
	}

	/* -------------------------------------------------------------------------
	 * REST API methods
	 *------------------------------------------------------------------------*/

	/**
	 * @SubsonicAPI
	 */
	private function ping() {
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getLicense() {
		return $this->subsonicResponse([
			'license' => [
				'valid' => 'true',
				'email' => '',
				'licenseExpires' => 'never'
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getMusicFolders() {
		// Only single root folder is supported
		return $this->subsonicResponse([
			'musicFolders' => ['musicFolder' => [
				['id' => 'root', 
				'name' => $this->l10n->t('Music')]
			]]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getIndexes() {
		$artists = $this->artistBusinessLayer->findAll($this->userId, SortBy::Name);

		$indexes = [];
		foreach ($artists as $artist) {
			$indexes[$artist->getIndexingChar()][] = [
				'name' => $artist->getNameString($this->l10n),
				'id' => 'artist-' . $artist->getId()
			];
		}

		$result = [];
		foreach ($indexes as $indexChar => $bucketArtists) {
			$result[] = ['name' => $indexChar, 'artist' => $bucketArtists];
		}

		return $this->subsonicResponse(['indexes' => ['index' => $result]]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getMusicDirectory() {
		$id = $this->request->getParam('id');
		
		if (Util::startsWith($id, 'artist-')) {
			return $this->doGetMusicDirectoryForArtist($id);
		} else {
			return $this->doGetMusicDirectoryForAlbum($id);
		}
	}

	/**
	 * @SubsonicAPI
	 */
	private function getAlbumList() {
		$type = $this->request->getParam('type');
		$size = $this->request->getParam('size', 10);
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		// $offset = $this->request->getParam('offset', 0); parameter not supported for now

		$albums = [];
		if ($size > 0 && $type == 'random') {
			$allAlbums = $this->albumBusinessLayer->findAll($this->userId);
			$size = \min($size, \count($allAlbums)); // can't return more than all albums
			$indices = \array_rand($allAlbums, $size);

			foreach ($indices as $index) {
				$albums[] = $this->albumAsChild($allAlbums[$index]);
			}
		}
		// TODO: support 'newest', 'highest', 'frequent', 'recent'

		return $this->subsonicResponse(['albumList' => ['album' => $albums]]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getRandomSongs() {
		return $this->subsonicResponse([
			'randomSongs' => ['song' => [
					['id' => '101', 'parent'=>100, 'title'=>'Dancing Queen', 'album'=>'First album', 'artist'=>'ABBA', 
						'track'=>1, 'isDir'=>'false', 'coverArt'=>123, 'genre'=>'Pop', 'year'=>1978, 'size'=>'123456',
						'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track101'
					], 
					['id' => '102', 'parent'=>100, 'title'=>'Money, Money, Money', 'album'=>'First album', 'artist'=>'ABBA', 
						'track'=>2, 'isDir'=>'false', 'coverArt'=>456, 'genre'=>'Pop', 'year'=>1978, 'size'=>'678123',
						'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track102'
					] 
				]
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getCoverArt() {
		$id = $this->request->getParam('id');
		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		try {
			$coverData = $this->coverHelper->getCover($id, $this->userId, $userFolder);
			if ($coverData !== null) {
				return new FileResponse($coverData);
			}
		} catch (BusinessLayerException $e) {
			return $this->subsonicErrorResponse(70, 'album not found');
		}

		return $this->subsonicErrorResponse(70, 'album has no cover');
	}

	/**
	 * @SubsonicAPI
	 */
	private function stream() {
		// We don't support transcaoding, so 'stream' and 'download' act identically
		return $this->download();
	}

	/**
	 * @SubsonicAPI
	 */
	private function download() {
		$id = $this->request->getParam('id');
		$trackId = \explode('-', $id)[1]; // get rid of 'track-' prefix
		
		try {
			$track = $this->trackBusinessLayer->find($trackId, $this->userId);
		} catch (BusinessLayerException $e) {
			return $this->subsonicErrorResponse(70, $e->getMessage());
		}
		
		$files = $this->rootFolder->getUserFolder($this->userId)->getById($track->getFileId());
		
		if (\count($files) === 1) {
			return new FileResponse($files[0]);
		} else {
			return $this->subsonicErrorResponse(70, 'file not found');
		}
	}

	/* -------------------------------------------------------------------------
	 * Helper methods
	 *------------------------------------------------------------------------*/

	private function doGetMusicDirectoryForArtist($id) {
		$artistId = \explode('-', $id)[1]; // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artistName = $artist->getNameString($this->l10n);
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);

		$children = [];
		foreach ($albums as $album) {
			$children[] = $this->albumAsChild($album, $artistName);
		}

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'root',
				'name' => $artistName,
				'child' => $children
			]
		]);
	}

	private function doGetMusicDirectoryForAlbum($id) {
		$albumId = \explode('-', $id)[1]; // get rid of 'album-' prefix

		$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		$albumName = $album->getNameString($this->l10n);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);

		$children = [];
		foreach ($tracks as $track) {
			$trackArtist = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
			$children[] = [
				'id' => 'track-' . $track->getId(),
				'parent' => $id,
				'title' => $track->getTitle(),
				'artist' => $trackArtist->getNameString($this->l10n),
				'isDir' => false,
				'coverArt' => empty($album->getCoverFileId()) ? '' : $album->getId(),
				'album' => $albumName,
				'track' => $track->getNumber() ?: 0,
				//'genre' => '',
				'year' => $track->getYear(),
				'size' => 0,
				'contentType' => $track->getMimetype(),
				//'suffix' => '',
				'duration' => $track->getLength() ?: 0,
				'bitRate' => $track->getBitrate() ?: 0,
				//'path' => ''
			];
		}

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'artist-' . $album->getAlbumArtistId(),
				'name' => $albumName,
				'child' => $children
			]
		]);
	}

	private function albumAsChild($album, $artistName = null) {
		$artistId = $album->getAlbumArtistId();

		if (empty($artistName)) {
			$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
			$artistName = $artist->getNameString($this->l10n);
		}

		return [
			'id' => 'album-' . $album->getId(),
			'parent' => 'artist-' . $artistId,
			'title' => $album->getNameString($this->l10n),
			'artist' => $artistName,
			'isDir' => true,
			'coverArt' => empty($album->getCoverFileId()) ? '' : $album->getId()
		];
	}

	private function subsonicResponse($content, $status = 'ok') {
		$content['status'] = $status; 
		$content['version'] = self::API_VERSION;
		return new JSONResponse(['subsonic-response' => $content]);
	}

	public function subsonicErrorResponse($errorCode, $errorMessage) {
		return $this->subsonicResponse([
				'error' => [
					'code' => $errorCode,
					'message' => $errorMessage
				]
			], 'failed');
	}
}
