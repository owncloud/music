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
use \OCA\Music\Http\XMLResponse;

use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\Util;
use OCA\Music\Middleware\SubsonicException;

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
		$this->format = $this->request->getParam('f', 'xml');
		if ($this->format != 'json' && $this->format != 'xml') {
			throw new SubsonicException("Unsupported format {$this->format}", 0);
		}

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
		$artists = $this->artistBusinessLayer->findAllHavingAlbums($this->userId, SortBy::Name);

		$indexes = [];
		foreach ($artists as $artist) {
			$indexes[$artist->getIndexingChar()][] = $this->artistAsChild($artist);
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
		$id = $this->getRequiredParam('id');

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
		$type = $this->getRequiredParam('type');
		$size = $this->request->getParam('size', 10);
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		// $offset = $this->request->getParam('offset', 0); parameter not supported for now

		$albums = [];
		if ($type == 'random') {
			$allAlbums = $this->albumBusinessLayer->findAll($this->userId);
			$albums = self::randomItems($allAlbums, $size);
		}
		// TODO: support 'newest', 'highest', 'frequent', 'recent'

		return $this->subsonicResponse(['albumList' =>
				['album' => \array_map([$this, 'albumAsChild'], $albums)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getRandomSongs() {
		$size = $this->request->getParam('size', 10);
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		// $genre = $this->request->getParam('genre'); not supported
		// $fromYear = $this->request->getParam('fromYear'); not supported
		// $toYear = $this->request->getParam('genre'); not supported

		$allTracks = $this->trackBusinessLayer->findAll($this->userId);
		$tracks = self::randomItems($allTracks, $size);

		return $this->subsonicResponse(['randomSongs' =>
				['song' => \array_map([$this, 'trackAsChild'], $tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getCoverArt() {
		$id = $this->getRequiredParam('id');
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
		$id = $this->getRequiredParam('id');
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

	/**
	 * @SubsonicAPI
	 */
	private function search2() {
		$query = $this->getRequiredParam('query');
		$artistCount = $this->request->getParam('artistCount', 20);
		$artistOffset = $this->request->getParam('artistOffset', 0);
		$albumCount = $this->request->getParam('albumCount', 20);
		$albumOffset = $this->request->getParam('albumOffset', 0);
		$songCount = $this->request->getParam('songCount', 20);
		$songOffset = $this->request->getParam('songOffset', 0);

		if (empty($query)) {
			throw new SubsonicException("The 'query' argument is mandatory", 10);
		}

		$artists = $this->artistBusinessLayer->findAllByName($query, $this->userId, true, $artistCount, $artistOffset);
		$albums = $this->albumBusinessLayer->findAllByName($query, $this->userId, true, $albumCount, $albumOffset);
		$tracks = $this->trackBusinessLayer->findAllByName($query, $this->userId, true, $songCount, $songOffset);

		$results = [];
		if (!empty($artists)) {
			$results['artist'] = \array_map([$this, 'artistAsChild'], $artists);
		}
		if (!empty($albums)) {
			$results['album'] = \array_map([$this, 'albumAsChild'], $albums);
		}
		if (!empty($tracks)) {
			$results['song'] = \array_map([$this, 'trackAsChild'], $tracks);
		}

		return $this->subsonicResponse(['searchResult2' => $results]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getPlaylists() {
		$playlists = $this->playlistBusinessLayer->findAll($this->userId);

		return $this->subsonicResponse(['playlists' =>
			['playlist' => \array_map([$this, 'playlistAsChild'], $playlists)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getPlaylist() {
		$id = $this->getRequiredParam('id');
		$playlist = $this->playlistBusinessLayer->find($id, $this->userId);
		$tracks = $this->playlistBusinessLayer->getPlaylistTracks($id, $this->userId);

		$playlistNode = $this->playlistAsChild($playlist);
		$playlistNode['entry'] = \array_map([$this, 'trackAsChild'], $tracks);

		return $this->subsonicResponse(['playlist' => $playlistNode]);
	}

	/* -------------------------------------------------------------------------
	 * Helper methods
	 *------------------------------------------------------------------------*/

	private function getRequiredParam($paramName) {
		$param = $this->request->getParam($paramName);

		if ($param === null) {
			throw new SubsonicException("Required parameter '$paramName' missing", 10);
		}

		return $param;
	}

	private function doGetMusicDirectoryForArtist($id) {
		$artistId = \explode('-', $id)[1]; // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artistName = $artist->getNameString($this->l10n);
		$albums = $this->albumBusinessLayer->findAllByAlbumArtist($artistId, $this->userId);

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

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'artist-' . $album->getAlbumArtistId(),
				'name' => $albumName,
				'child' => \array_map([$this, 'trackAsChild'], $tracks)
			]
		]);
	}

	private function artistAsChild($artist) {
		return [
			'name' => $artist->getNameString($this->l10n),
			'id' => 'artist-' . $artist->getId()
		];
	}

	private function albumAsChild($album, $artistName = null) {
		$artistId = $album->getAlbumArtistId();

		if (empty($artistName)) {
			$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
			$artistName = $artist->getNameString($this->l10n);
		}

		$result = [
			'id' => 'album-' . $album->getId(),
			'parent' => 'artist-' . $artistId,
			'title' => $album->getNameString($this->l10n),
			'artist' => $artistName,
			'isDir' => true
		];

		if (!empty($album->getCoverFileId())) {
			$result['coverArt'] = $album->getId();
		}

		return $result;
	}

	private function trackAsChild($track, $album = null, $albumName = null) {
		$albumId = $track->getAlbumId();
		if ($album == null) {
			$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		}
		if (empty($albumName)) {
			$albumName = $album->getNameString($this->l10n);
		}

		$trackArtist = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
		$result = [
			'id' => 'track-' . $track->getId(),
			'parent' => 'album-' . $albumId,
			'title' => $track->getTitle(),
			'artist' => $trackArtist->getNameString($this->l10n),
			'isDir' => false,
			'album' => $albumName,
			//'genre' => '',
			'year' => $track->getYear(),
			//'size' => 0,
			'contentType' => $track->getMimetype(),
			//'suffix' => '',
			'duration' => $track->getLength() ?: 0,
			'bitRate' => \round($track->getBitrate()/1000) ?: 0, // convert bps to kbps
			//'path' => ''
		];

		if (!empty($album->getCoverFileId())) {
			$result['coverArt'] = $album->getId();
		}

		if ($track->getNumber() !== null) {
			$result['track'] = $track->getNumber();
		}

		return $result;
	}

	private function playlistAsChild($playlist) {
		return [
			'id' => $playlist->getId(),
			'name' => $playlist->getName(),
			'owner' => $this->userId,
			'public' => false,
			'songCount' => $playlist->getTrackCount(),
			// comment => '',
			// duration => '',
			// created => '',
			// coverArt => ''
		];
	}

	private static function randomItems($itemArray, $count) {
		$count = \min($count, \count($itemArray)); // can't return more than all items
		$indices = \array_rand($itemArray, $count);
		if ($count == 1) { // return type is not array when randomizing a single index
			$indices = [$indices];
		}

		$result = [];
		foreach ($indices as $index) {
			$result[] = $itemArray[$index];
		}

		return $result;
	}

	private function subsonicResponse($content, $status = 'ok') {
		$content['status'] = $status; 
		$content['version'] = self::API_VERSION;
		$responseData = ['subsonic-response' => $content];
		
		if ($this->format == 'json') {
			return new JSONResponse($responseData);
		} else {
			return new XMLResponse($responseData);
		}
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
