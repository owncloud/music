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
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\Files\File;
use \OCP\Files\Folder;
use \OCP\Files\IRootFolder;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\FileResponse;
use \OCA\Music\Http\XMLResponse;

use \OCA\Music\Middleware\SubsonicException;

use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\UserMusicFolder;
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
	private $userMusicFolder;
	private $l10n;
	private $coverHelper;
	private $logger;
	private $userId;
	private $format;
	private $callback;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								IRootFolder $rootFolder,
								UserMusicFolder $userMusicFolder,
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
		$this->rootFolder = $rootFolder;
		$this->userMusicFolder = $userMusicFolder;
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
		$this->callback = $this->request->getParam('callback');

		if ($this->format != 'json' && $this->format != 'xml' && $this->format != 'jsonp') {
			throw new SubsonicException("Unsupported format {$this->format}", 0);
		}

		if ($this->format === 'jsonp' && $this->callback === null) {
			$this->format = 'json';
			throw new SubsonicException("Argument 'callback' is required with jsonp format", 10);
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
				['id' => 'artists', 'name' => $this->l10n->t('Artists')],
				['id' => 'folders', 'name' => $this->l10n->t('Folders')]
			]]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getIndexes() {
		$id = $this->request->getParam('musicFolderId');

		if ($id === 'folders') {
			return $this->getIndexesForFolders();
		} else {
			return $this->getIndexesForArtists();
		}
	}

	/**
	 * @SubsonicAPI
	 */
	private function getMusicDirectory() {
		$id = $this->getRequiredParam('id');

		if (Util::startsWith($id, 'folder-')) {
			return $this->getMusicDirectoryForFolder($id);
		} elseif (Util::startsWith($id, 'artist-')) {
			return $this->getMusicDirectoryForArtist($id);
		} else {
			return $this->getMusicDirectoryForAlbum($id);
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
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix

		try {
			$track = $this->trackBusinessLayer->find($trackId, $this->userId);
		} catch (BusinessLayerException $e) {
			return $this->subsonicErrorResponse(70, $e->getMessage());
		}

		$file = $this->getFilesystemNode($track->getFileId());

		if ($file instanceof File) {
			return new FileResponse($file);
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

	/**
	 * @SubsonicAPI
	 */
	private function createPlaylist() {
		$name = $this->getRequiredParam('name');
		$songIds = $this->getRepeatedParam('songId');
		$songIds = \array_map('self::ripIdPrefix', $songIds);

		$playlist = $this->playlistBusinessLayer->create($name, $this->userId);
		$this->playlistBusinessLayer->addTracks($songIds, $playlist->getId(), $this->userId);

		return $this->subsonicResponse([]);
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

	/** 
	 * Get values for parameter which may be present multiple times in the query
	 * string or POST data.
	 * @param string $paramName
	 * @return string[]
	 */
	private function getRepeatedParam($paramName) {
		// We can't use the IRequest object nor $_GET and $_POST to get the data
		// because all of these are based to the idea of unique parameter names.
		// If the same name is repeated, only the last value is saved. Hence, we
		// need to parse the raw data manually.

		// query string is always present (although it could be empty)
		$values = $this->parseRepeatedKeyValues($paramName, $_SERVER['QUERY_STRING']);

		// POST data is available if the method is POST
		if ($this->request->getMethod() == 'POST') {
			$values = \array_merge($values,
					$this->parseRepeatedKeyValues($paramName, file_get_contents('php://input')));
		}

		return $values;
	}

	/**
	 * Parse a string like "someKey=value1&someKey=value2&anotherKey=valueA&someKey=value3"
	 * and return an array of values for the given key
	 * @param string $key
	 * @param string $data
	 */
	private function parseRepeatedKeyValues($key, $data) {
		$result = [];

		$keyValuePairs = \explode('&', $data);

		foreach ($keyValuePairs as $pair) {
			$keyAndValue = \explode('=', $pair);

			if ($keyAndValue[0] == $key) {
				$result[] = $keyAndValue[1];
			}
		}

		return $result;
	}

	private function getFilesystemNode($id) {
		$nodes = $this->rootFolder->getUserFolder($this->userId)->getById($id);

		if (\count($nodes) != 1) {
			throw new SubsonicException('file not found', 70);
		}

		return $nodes[0];
	}

	private function getIndexesForFolders() {
		$rootFolder = $this->userMusicFolder->getFolder($this->userId);
	
		return $this->subsonicResponse(['indexes' => ['index' => [
				['name' => '*',
				'artist' => [['id' => 'folder-' . $rootFolder->getId(), 'name' => $rootFolder->getName()]]]
				]]]);
	}
	
	private function getMusicDirectoryForFolder($id) {
		$folderId = self::ripIdPrefix($id); // get rid of 'folder-' prefix
		$folder = $this->getFilesystemNode($folderId);

		if (!($folder instanceof Folder)) {
			throw new SubsonicException("$id is not a valid folder", 70);
		}

		$nodes = $folder->getDirectoryListing();
		$subFolders = \array_filter($nodes, function ($n) {
			return $n instanceof Folder;
		});
		$tracks = $this->trackBusinessLayer->findAllByFolder($folderId, $this->userId);

		$children = \array_merge(
			\array_map([$this, 'folderAsChild'], $subFolders),
			\array_map([$this, 'trackAsChild'], $tracks)
		);

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'folder-' . $folder->getParent()->getId(),
				'name' => $folder->getName(),
				'child' => $children
			]
		]);
	}

	private function getIndexesForArtists() {
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

	private function getMusicDirectoryForArtist($id) {
		$artistId = self::ripIdPrefix($id); // get rid of 'artist-' prefix

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
				'parent' => 'artists',
				'name' => $artistName,
				'child' => $children
			]
		]);
	}

	private function getMusicDirectoryForAlbum($id) {
		$albumId = self::ripIdPrefix($id); // get rid of 'album-' prefix

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

	private function folderAsChild($folder) {
		return [
			'id' => 'folder-' . $folder->getId(),
			'title' => $folder->getName(),
			'isDir' => true
		];
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
			'size' => $track->getSize(),
			'contentType' => $track->getMimetype(),
			'suffix' => \end(\explode('.', $track->getFilename())),
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

	/**
	 * Given a prefixed ID like 'artist-123' or 'track-45', return just the numeric part.
	 * @param string $id
	 * @return string
	 */
	private static function ripIdPrefix($id) {
		return \explode('-', $id)[1];
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
			$response = new JSONResponse($responseData);
		} else if ($this->format == 'jsonp') {
			$responseData = \json_encode($responseData);
			$response = new DataDisplayResponse("{$this->callback}($responseData);");
			$response->addHeader('Content-Type', 'text/javascript; charset=UTF-8');
		} else {
			$response = new XMLResponse($responseData);
		}

		return $response;
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
