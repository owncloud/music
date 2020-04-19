<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019, 2020
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\Files\File;
use \OCP\Files\Folder;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\GenreBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\Album;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\Genre;
use \OCA\Music\Db\Playlist;
use \OCA\Music\Db\SortBy;
use \OCA\Music\Db\Track;

use \OCA\Music\Http\FileResponse;
use \OCA\Music\Http\XMLResponse;

use \OCA\Music\Middleware\SubsonicException;

use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\DetailsHelper;
use \OCA\Music\Utility\Random;
use \OCA\Music\Utility\UserMusicFolder;
use \OCA\Music\Utility\Util;

class SubsonicController extends Controller {
	const API_VERSION = '1.9.0';

	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $playlistBusinessLayer;
	private $genreBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $urlGenerator;
	private $userMusicFolder;
	private $l10n;
	private $coverHelper;
	private $detailsHelper;
	private $random;
	private $logger;
	private $userId;
	private $format;
	private $callback;
	private $timezone;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								UserMusicFolder $userMusicFolder,
								CoverHelper $coverHelper,
								DetailsHelper $detailsHelper,
								Random $random,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->library = $library;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->userMusicFolder = $userMusicFolder;
		$this->coverHelper = $coverHelper;
		$this->detailsHelper = $detailsHelper;
		$this->random = $random;
		$this->logger = $logger;

		// For timestamps in the Subsonic API, we would prefer to use the local timezone,
		// but the core has set the default timezone as 'UTC'. Get the timezone from php.ini
		// if available, and store it for later use.
		$tz = \ini_get('date.timezone') ?: 'UTC';
		$this->timezone = new \DateTimeZone($tz);
	}

	/**
	 * Called by the middleware to set the reponse format to be used
	 * @param string $format Response format: xml/json/jsonp
	 * @param string|null $callback Function name to use if the @a $format is 'jsonp'
	 */
	public function setResponseFormat($format, $callback) {
		$this->format = $format;
		$this->callback = $callback;
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
	 */
	public function handleRequest($method) {
		$this->logger->log("Subsonic request $method", 'debug');

		// Allow calling all methods with or without the postfix ".view"
		if (Util::endsWith($method, ".view")) {
			$method = \substr($method, 0, -\strlen(".view"));
		}

		// Allow calling any functions annotated to be part of the API
		if (\method_exists($this, $method)) {
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
		$albums = $this->albumsForGetAlbumList();
		return $this->subsonicResponse(['albumList' =>
				['album' => \array_map([$this, 'albumToOldApi'], $albums)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getAlbumList2() {
		/*
		 * According to the API specification, the difference between this and getAlbumList
		 * should be that this function would organize albums according the metadata while
		 * getAlbumList would organize them by folders. However, we organize by metadata
		 * also in getAlbumList, because that's more natural for the Music app and many/most
		 * clients do not support getAlbumList2.
		 */
		$albums = $this->albumsForGetAlbumList();
		return $this->subsonicResponse(['albumList2' =>
				['album' => \array_map([$this, 'albumToNewApi'], $albums)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getArtists() {
		return $this->getIndexesForArtists('artists');
	}

	/**
	 * @SubsonicAPI
	 */
	private function getArtist() {
		$id = $this->getRequiredParam('id');
		$artistId = self::ripIdPrefix($id); // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artistName = $artist->getNameString($this->l10n);
		$albums = $this->albumBusinessLayer->findAllByAlbumArtist($artistId, $this->userId);

		$artistNode = $this->artistToApi($artist);
		$artistNode['album'] = \array_map(function($album) use ($artistName) {
			return $this->albumToNewApi($album, $artistName);
		}, $albums);

		return $this->subsonicResponse(['artist' => $artistNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getAlbum() {
		$id = $this->getRequiredParam('id');
		$albumId = self::ripIdPrefix($id); // get rid of 'album-' prefix

		$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);

		$albumNode = $this->albumToNewApi($album);
		$albumNode['song'] = \array_map(function($track) use ($album) {
			$track->setAlbum($album);
			return $this->trackToApi($track);
		}, $tracks);
		return $this->subsonicResponse(['album' => $albumNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getSong() {
		$id = $this->getRequiredParam('id');
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix
		$track = $this->trackBusinessLayer->find($trackId, $this->userId);

		return $this->subsonicResponse(['song' => $this->trackToApi($track)]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getRandomSongs() {
		$size = $this->request->getParam('size', 10);
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		$genre = $this->request->getParam('genre');
		// $fromYear = $this->request->getParam('fromYear'); not supported
		// $toYear = $this->request->getParam('genre'); not supported

		if ($genre !== null) {
			$trackPool = $this->findTracksByGenre($genre);
		} else {
			$trackPool = $this->trackBusinessLayer->findAll($this->userId);
		}
		$tracks = Random::pickItems($trackPool, $size);

		return $this->subsonicResponse(['randomSongs' =>
				['song' => \array_map([$this, 'trackToApi'], $tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getCoverArt() {
		$id = $this->getRequiredParam('id');
		$size = $this->request->getParam('size');

		$rootFolder = $this->userMusicFolder->getFolder($this->userId);
		$coverData = $this->coverHelper->getCover($id, $this->userId, $rootFolder, $size);

		if ($coverData !== null) {
			return new FileResponse($coverData);
		}

		return $this->subsonicErrorResponse(70, 'album has no cover');
	}

	/**
	 * @SubsonicAPI
	 */
	private function getLyrics() {
		$artistPar = $this->request->getParam('artist');
		$titlePar = $this->request->getParam('title');

		$matches = $this->trackBusinessLayer->findAllByNameAndArtistName($titlePar, $artistPar, $this->userId);
		$matchingCount = \count($matches);

		if ($matchingCount === 0) {
			$this->logger->log("No matching track for title '$titlePar' and artist '$artistPar'", 'debug');
			return $this->subsonicResponse(['lyrics' => new \stdClass]);
		}
		else {
			if ($matchingCount > 1) {
				$this->logger->log("Found $matchingCount tracks matching title ".
									"'$titlePar' and artist '$artistPar'; using the first", 'debug');
			}
			$track = $matches[0];

			$artist = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
			$rootFolder = $this->userMusicFolder->getFolder($this->userId);
			$lyrics = $this->detailsHelper->getLyrics($track->getFileId(), $rootFolder);

			return $this->subsonicResponse(['lyrics' => [
					'artist' => $artist->getNameString($this->l10n),
					'title' => $track->getTitle(),
					'value' => $lyrics
			]]);
		}
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

		$track = $this->trackBusinessLayer->find($trackId, $this->userId);
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
		$results = $this->doSearch();
		return $this->searchResponse('searchResult2', $results, /*$useNewApi=*/false);
	}

	/**
	 * @SubsonicAPI
	 */
	private function search3() {
		$results = $this->doSearch();
		return $this->searchResponse('searchResult3', $results, /*$useNewApi=*/true);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getGenres() {
		$genres = $this->genreBusinessLayer->findAllWithCounts($this->userId);

		return $this->subsonicResponse(['genres' =>
			[
				'genre' => \array_map(function($genre) {
					return [
						'songCount' => $genre->getTrackCount(),
						'albumCount' => $genre->getAlbumCount(),
						'value' => $genre->getNameString($this->l10n)
					];
				},
				$genres)
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getSongsByGenre() {
		$genre = $this->getRequiredParam('genre');
		$count = $this->request->getParam('count', 10);
		$offset = $this->request->getParam('offset', 0);

		$tracks = $this->findTracksByGenre($genre, $count, $offset);

		return $this->subsonicResponse(['songsByGenre' =>
			['song' => \array_map([$this, 'trackToApi'], $tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getPlaylists() {
		$playlists = $this->playlistBusinessLayer->findAll($this->userId);

		return $this->subsonicResponse(['playlists' =>
			['playlist' => \array_map([$this, 'playlistToApi'], $playlists)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getPlaylist() {
		$id = $this->getRequiredParam('id');
		$playlist = $this->playlistBusinessLayer->find($id, $this->userId);
		$tracks = $this->playlistBusinessLayer->getPlaylistTracks($id, $this->userId);

		$playlistNode = $this->playlistToApi($playlist);
		$playlistNode['entry'] = \array_map([$this, 'trackToApi'], $tracks);

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

	/**
	 * @SubsonicAPI
	 */
	private function updatePlaylist() {
		$listId = $this->getRequiredParam('playlistId');
		$newName = $this->request->getParam('name');
		$songIdsToAdd = $this->getRepeatedParam('songIdToAdd');
		$songIdsToAdd = \array_map('self::ripIdPrefix', $songIdsToAdd);
		$songIndicesToRemove = $this->getRepeatedParam('songIndexToRemove');

		if (!empty($newName)) {
			$this->playlistBusinessLayer->rename($newName, $listId, $this->userId);
		}

		if (!empty($songIndicesToRemove)) {
			$this->playlistBusinessLayer->removeTracks($songIndicesToRemove, $listId, $this->userId);
		}

		if (!empty($songIdsToAdd)) {
			$this->playlistBusinessLayer->addTracks($songIdsToAdd, $listId, $this->userId);
		}

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function deletePlaylist() {
		$id = $this->getRequiredParam('id');
		$this->playlistBusinessLayer->delete($id, $this->userId);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getUser() {
		$username = $this->getRequiredParam('username');

		if ($username != $this->userId) {
			throw new SubsonicException("{$this->userId} is not authorized to get details for other users.", 50);
		}

		return $this->subsonicResponse([
			'user' => [
				'username' => $username,
				'email' => '',
				'scrobblingEnabled' => false,
				'adminRole' => false,
				'settingsRole' => false,
				'downloadRole' => true,
				'uploadRole' => false,
				'playlistRole' => true,
				'coverArtRole' => false,
				'commentRole' => false,
				'podcastRole' => false,
				'streamRole' => true,
				'jukeboxRole' => false,
				'shareRole' => false,
				'videoConversionRole' => false,
				'folder' => ['artists', 'folders'],
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getUsers() {
		throw new SubsonicException("{$this->userId} is not authorized to get details for other users.", 50);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getAvatar() {
		// TODO: Use 'username' parameter to fetch user-specific avatar from the OC core.
		// Remember to check the permission.
		// For now, use the Music app logo for all users.
		$fileName = \join(DIRECTORY_SEPARATOR, [\dirname(__DIR__), 'img', 'logo', 'music_logo.png']);
		$content = \file_get_contents($fileName);
		return new FileResponse(['content' => $content, 'mimetype' => 'image/png']);
	}

	/**
	 * @SubsonicAPI
	 */
	private function star() {
		$targetIds = $this->getStarringParameters();

		$this->trackBusinessLayer->setStarred($targetIds['tracks'], $this->userId);
		$this->albumBusinessLayer->setStarred($targetIds['albums'], $this->userId);
		$this->artistBusinessLayer->setStarred($targetIds['artists'], $this->userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function unstar() {
		$targetIds = $this->getStarringParameters();

		$this->trackBusinessLayer->unsetStarred($targetIds['tracks'], $this->userId);
		$this->albumBusinessLayer->unsetStarred($targetIds['albums'], $this->userId);
		$this->artistBusinessLayer->unsetStarred($targetIds['artists'], $this->userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getStarred() {
		$starred = $this->doGetStarred();
		return $this->searchResponse('starred', $starred, /*$useNewApi=*/false);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getStarred2() {
		$starred = $this->doGetStarred();
		return $this->searchResponse('starred2', $starred, /*$useNewApi=*/true);
	}

	/**
	 * @SubsonicAPI
	 */
	private function getVideos() {
		// TODO: dummy implementation
		return $this->subsonicResponse([
			'videos' => [
				'video' => []
			]
		]);
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
	 * Get parameters used in the `star` and `unstar` API methods
	 */
	private function getStarringParameters() {
		// album IDs from newer clients
		$albumIds = $this->getRepeatedParam('albumId');
		$albumIds = \array_map('self::ripIdPrefix', $albumIds);

		// artist IDs from newer clients
		$artistIds = $this->getRepeatedParam('artistId');
		$artistIds = \array_map('self::ripIdPrefix', $artistIds);

		// song IDs from newer clients and song/folder/album/artist IDs from older clients
		$ids = $this->getRepeatedParam('id');

		$trackIds = [];

		foreach ($ids as $prefixedId) {
			$parts = \explode('-', $prefixedId);
			$type = $parts[0];
			$id = $parts[1];

			if ($type == 'track') {
				$trackIds[] = $id;
			} elseif ($type == 'album') {
				$albumIds[] = $id;
			} elseif ($type == 'artist') {
				$artistIds[] = $id;
			} elseif ($type == 'folder') {
				throw new SubsonicException('Starring folders is not supported', 0);
			} else {
				throw new SubsonicException("Unexpected ID format: $prefixedId", 0);
			}
		}

		return [
			'tracks' => $trackIds,
			'albums' => $albumIds,
			'artists' => $artistIds
		];
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
		$rootFolder = $this->userMusicFolder->getFolder($this->userId);
		$nodes = $rootFolder->getById($id);

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

		// A folder may contain thousands of audio files, and getting artist and album data
		// for each of those individually would take a lot of time and great many DB queries.
		// To prevent having to do this in `trackToApi`, we fetch all the albums and artists
		// in one go.
		$this->injectAlbumsArtistsAndGenresToTracks($tracks);

		$children = \array_merge(
			\array_map([$this, 'folderToApi'], $subFolders),
			\array_map([$this, 'trackToApi'], $tracks)
		);

		$content = [
			'directory' => [
				'id' => $id,
				'name' => $folder->getName(),
				'child' => $children
			]
		];

		// Parent folder ID is included if and only if the parent folder is not the top level
		$rootFolderId = $this->userMusicFolder->getFolder($this->userId)->getId();
		$parentFolderId = $folder->getParent()->getId();
		if ($rootFolderId != $parentFolderId) {
			$content['parent'] = 'folder-' . $parentFolderId;
		}

		return $this->subsonicResponse($content);
	}

	private function injectAlbumsArtistsAndGenresToTracks(&$tracks) {
		$albumIds = [];
		$artistIds = [];

		// get unique album and artist IDs
		foreach ($tracks as $track) {
			$albumIds[$track->getAlbumId()] = 1;
			$artistIds[$track->getArtistId()] = 1;
		}
		$albumIds = \array_keys($albumIds);
		$artistIds = \array_keys($artistIds);

		// get the corresponding entities from the business layer
		$albums = $this->albumBusinessLayer->findById($albumIds, $this->userId);
		$artists = $this->artistBusinessLayer->findById($artistIds, $this->userId);
		$genres = $this->genreBusinessLayer->findAll($this->userId);

		// create hash tables "id => entity" for the albums and artists for fast access
		$albumMap = Util::createIdLookupTable($albums);
		$artistMap = Util::createIdLookupTable($artists);
		$genreMap = Util::createIdLookupTable($genres);

		// finally, set the references on the tracks
		foreach ($tracks as &$track) {
			$track->setAlbum($albumMap[$track->getAlbumId()]);
			$track->setArtist($artistMap[$track->getArtistId()]);
			$track->setGenre($genreMap[$track->getGenreId()]);
		}
	}

	private function getIndexesForArtists($rootElementName = 'indexes') {
		$artists = $this->artistBusinessLayer->findAllHavingAlbums($this->userId, SortBy::Name);
	
		$indexes = [];
		foreach ($artists as $artist) {
			$indexes[$artist->getIndexingChar()][] = $this->artistToApi($artist);
		}
	
		$result = [];
		foreach ($indexes as $indexChar => $bucketArtists) {
			$result[] = ['name' => $indexChar, 'artist' => $bucketArtists];
		}
	
		return $this->subsonicResponse([$rootElementName => ['index' => $result]]);
	}

	private function getMusicDirectoryForArtist($id) {
		$artistId = self::ripIdPrefix($id); // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artistName = $artist->getNameString($this->l10n);
		$albums = $this->albumBusinessLayer->findAllByAlbumArtist($artistId, $this->userId);

		$children = [];
		foreach ($albums as $album) {
			$children[] = $this->albumToOldApi($album, $artistName);
		}

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
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
				'child' => \array_map(function($track) use ($album) {
					$track->setAlbum($album);
					return $this->trackToApi($track);
				}, $tracks)
			]
		]);
	}

	/**
	 * @param Folder $folder
	 * @return array
	 */
	private function folderToApi($folder) {
		return [
			'id' => 'folder-' . $folder->getId(),
			'title' => $folder->getName(),
			'isDir' => true
		];
	}

	/**
	 * @param Artist $artist
	 * @return array
	 */
	private function artistToApi($artist) {
		$result = [
			'name' => $artist->getNameString($this->l10n),
			'id' => 'artist-' . $artist->getId(),
			'albumCount' => $this->albumBusinessLayer->countByArtist($artist->getId())
		];

		if ($artist->getStarred() != null) {
			$result['starred'] = $this->formatDateTime($artist->getStarred());
		}

		return $result;
	}

	/**
	 * The "old API" format is used e.g. in getMusicDirectory and getAlbumList
	 * @param Album $album
	 * @param string|null $artistName
	 * @return array
	 */
	private function albumToOldApi($album, $artistName = null) {
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

		if ($album->getStarred() != null) {
			$result['starred'] = $this->formatDateTime($album->getStarred());
		}

		return $result;
	}

	/**
	 * The "new API" format is used e.g. in getAlbum and getAlbumList2
	 * @param Album $album
	 * @param string|null $artistName
	 * @return array
	 */
	private function albumToNewApi($album, $artistName = null) {
		$artistId = $album->getAlbumArtistId();

		if (empty($artistName)) {
			$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
			$artistName = $artist->getNameString($this->l10n);
		}

		$result = [
			'id' => 'album-' . $album->getId(),
			'artistId' => 'artist-' . $artistId,
			'name' => $album->getNameString($this->l10n),
			'artist' => $artistName,
			'songCount' => $this->trackBusinessLayer->countByAlbum($album->getId()),
			//'duration' => 0
		];

		if (!empty($album->getCoverFileId())) {
			$result['coverArt'] = $album->getId();
		}

		if ($album->getStarred() != null) {
			$result['starred'] = $this->formatDateTime($album->getStarred());
		}

		return $result;
	}

	/**
	 * The same API format is used both on "old" and "new" API methods. The "new" API adds some
	 * new fields for the songs, but providing some extra fields shouldn't be a problem for the
	 * older clients. 
	 * @param Track $track If the track entity has no album, artist and/or genre references set, then
	 *                     those are automatically fetched from the respective BusinessLayer modules.
	 * @return array
	 */
	private function trackToApi($track) {
		$albumId = $track->getAlbumId();

		$album = $track->getAlbum();
		if (empty($album)) {
			$album = $this->albumBusinessLayer->find($albumId, $this->userId);
			$track->setAlbum($album);
		}

		$artist = $track->getArtist();
		if (empty($artist)) {
			$artist = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
			$track->setArtist($artist);
		}

		$genre = $track->getGenre();
		if (empty($genre)) {
			$genre = $this->genreBusinessLayer->find($track->getGenreId(), $this->userId);
			$track->setGenre($genre);
		}

		$result = [
			'id' => 'track-' . $track->getId(),
			'parent' => 'album-' . $albumId,
			//'discNumber' => $track->getDisk(), // not supported on any of the tested clients => adjust track number instead
			'title' => $track->getTitle(),
			'artist' => $artist->getNameString($this->l10n),
			'isDir' => false,
			'album' => $album->getNameString($this->l10n),
			'genre' => $genre->getNameString($this->l10n),
			'year' => $track->getYear(),
			'size' => $track->getSize(),
			'contentType' => $track->getMimetype(),
			'suffix' => $track->getFileExtension(),
			'duration' => $track->getLength() ?: 0,
			'bitRate' => \round($track->getBitrate()/1000) ?: 0, // convert bps to kbps
			//'path' => '',
			'isVideo' => false,
			'albumId' => 'album-' . $albumId,
			'artistId' => 'artist-' . $track->getArtistId(),
			'type' => 'music'
		];

		if (!empty($album->getCoverFileId())) {
			$result['coverArt'] = $album->getId();
		}

		$trackNumber = $track->getDiskAdjustedTrackNumber();
		if ($trackNumber !== null) {
			$result['track'] = $trackNumber;
		}

		if ($track->getStarred() != null) {
			$result['starred'] = $this->formatDateTime($track->getStarred());
		}

		return $result;
	}

	/**
	 * @param Playlist $playlist
	 * @return array
	 */
	private function playlistToApi($playlist) {
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
	 * Common logic for getAlbumList and getAlbumList2
	 * @return Album[]
	 */
	private function albumsForGetAlbumList() {
		$type = $this->getRequiredParam('type');
		$size = $this->request->getParam('size', 10);
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		$offset = $this->request->getParam('offset', 0);

		$albums = [];

		switch ($type) {
			case 'random':
				$allAlbums = $this->albumBusinessLayer->findAll($this->userId);
				$indices = $this->random->getIndices(\count($allAlbums), $offset, $size, $this->userId, 'subsonic_albums');
				$albums = Util::arrayMultiGet($allAlbums, $indices);
				break;
			case 'starred':
				$albums = $this->albumBusinessLayer->findAllStarred($this->userId, $size, $offset);
				break;
			case 'alphabeticalByName':
				$albums = $this->albumBusinessLayer->findAll($this->userId, SortBy::Name, $size, $offset);
				break;
			case 'alphabeticalByArtist':
			case 'newest':
			case 'highest':
			case 'frequent':
			case 'recent':
			case 'byYear':
			case 'byGenre':
			default:
				$this->logger->log("Album list type '$type' is not supported", 'debug');
				break;
		}

		return $albums;
	}

	/**
	 * Common logic for search2 and search3
	 * @return array with keys 'artists', 'albums', and 'tracks'
	 */
	private function doSearch() {
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

		return [
			'artists' => $this->artistBusinessLayer->findAllByName($query, $this->userId, true, $artistCount, $artistOffset),
			'albums' => $this->albumBusinessLayer->findAllByName($query, $this->userId, true, $albumCount, $albumOffset),
			'tracks' => $this->trackBusinessLayer->findAllByName($query, $this->userId, true, $songCount, $songOffset)
		];
	}

	/**
	 * Common logic for getStarred and getStarred2
	 * @return array
	 */
	private function doGetStarred() {
		return [
			'artists' => $this->artistBusinessLayer->findAllStarred($this->userId),
			'albums' => $this->albumBusinessLayer->findAllStarred($this->userId),
			'tracks' => $this->trackBusinessLayer->findAllStarred($this->userId)
		];
	}

	/**
	 * Common response building logic for search2, search3, getStarred, and getStarred2
	 * @param string $title Name of the main node in the response message
	 * @param array $results Search results with keys 'artists', 'albums', and 'tracks'
	 * @param boolean $useNewApi Set to true for search3 and getStarred3. There is a difference
	 *                           in the formatting of the album nodes.
	 * @return \OCP\AppFramework\Http\Response
	 */
	private function searchResponse($title, $results, $useNewApi) {
		$albumMapFunc = $useNewApi ? 'albumToNewApi' : 'albumToOldApi';

		return $this->subsonicResponse([$title => [
			'artist' => \array_map([$this, 'artistToApi'], $results['artists']),
			'album' => \array_map([$this, $albumMapFunc], $results['albums']),
			'song' => \array_map([$this, 'trackToApi'], $results['tracks'])
		]]);
	}

	/**
	 * Find tracks by genre name
	 * @param string $genreName
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Track[]
	 */
	private function findTracksByGenre($genreName, $limit=null, $offset=null) {
		$genreArr = $this->genreBusinessLayer->findAllByName($genreName, $this->userId);
		if (\count($genreArr) == 0 && $genreName == Genre::unknownGenreName($this->l10n)) {
			$genreArr = $this->genreBusinessLayer->findAllByName('', $this->userId);
		}

		if (\count($genreArr) > 0) {
			return $this->trackBusinessLayer->findAllByGenre($genreArr[0]->getId(), $this->userId, $limit, $offset);
		} else {
			return [];
		}
	}

	/**
	 * Given a prefixed ID like 'artist-123' or 'track-45', return just the numeric part.
	 * @param string $id
	 * @return integer
	 */
	private static function ripIdPrefix($id) {
		return (int)(\explode('-', $id)[1]);
	}

	private function formatDateTime($dateString) {
		$dateTime = new \DateTime($dateString);
		$dateTime->setTimezone($this->timezone);
		return $dateTime->format('Y-m-d\TH:i:s');
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
