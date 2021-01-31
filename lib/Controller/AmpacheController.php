<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Middleware\AmpacheException;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\GenreBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\Album;
use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Db\AmpacheSession;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;
use \OCA\Music\Http\XmlResponse;

use \OCA\Music\Utility\AmpacheUser;
use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\Random;
use \OCA\Music\Utility\Util;

class AmpacheController extends Controller {
	private $ampacheUserMapper;
	private $ampacheSessionMapper;
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $genreBusinessLayer;
	private $playlistBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $ampacheUser;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;
	private $coverHelper;
	private $random;
	private $logger;
	private $jsonMode;

	const SESSION_EXPIRY_TIME = 6000;
	const ALL_TRACKS_PLAYLIST_ID = 10000000;
	const API_VERSION = 400001;
	const API_MIN_COMPATIBLE_VERSION = 350001;

	public function __construct(string $appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AmpacheUserMapper $ampacheUserMapper,
								AmpacheSessionMapper $ampacheSessionMapper,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								AmpacheUser $ampacheUser,
								$rootFolder,
								CoverHelper $coverHelper,
								Random $random,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->library = $library;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;

		// used to share user info with middleware
		$this->ampacheUser = $ampacheUser;

		// used to deliver actual media file
		$this->rootFolder = $rootFolder;

		$this->coverHelper = $coverHelper;
		$this->random = $random;
		$this->logger = $logger;
	}

	public function setJsonMode($useJsonMode) {
		$this->jsonMode = $useJsonMode;
	}

	public function ampacheResponse($content) {
		if ($this->jsonMode) {
			return new JSONResponse(self::prepareResultForJsonApi($content));
		} else {
			return new XmlResponse(self::prepareResultForXmlApi($content), ['id', 'index', 'count', 'code']);
		}
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function xmlApi($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset, $id, $add, $update) {
		// differentation between xmlApi and jsonApi is made already by the middleware
		return $this->dispatch($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset, $id, $add, $update);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function jsonApi($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset, $id, $add, $update) {
		// differentation between xmlApi and jsonApi is made already by the middleware
		return $this->dispatch($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset, $id, $add, $update);
	}

	protected function dispatch($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset, $id, $add, $update) {
		$this->logger->log("Ampache action '$action' requested", 'debug');

		$limit = self::validateLimitOrOffset($limit);
		$offset = self::validateLimitOrOffset($offset);

		switch ($action) {
			case 'handshake':
				return $this->handshake($user, $timestamp, $auth);
			case 'goodbye':
				return $this->goodbye($auth);
			case 'ping':
				return $this->ping($auth);
			case 'get_indexes':
				return $this->get_indexes($filter, $limit, $offset, $add, $update);
			case 'stats':
				return $this->stats($filter, $limit, $offset, $auth);
			case 'artists':
				return $this->artists($filter, $exact, $limit, $offset, $add, $update, $auth);
			case 'artist':
				return $this->artist((int)$filter, $auth);
			case 'artist_albums':
				return $this->artist_albums((int)$filter, $auth);
			case 'album_songs':
				return $this->album_songs((int)$filter, $auth);
			case 'albums':
				return $this->albums($filter, $exact, $limit, $offset, $add, $update, $auth);
			case 'album':
				return $this->album((int)$filter, $auth);
			case 'artist_songs':
				return $this->artist_songs((int)$filter, $auth);
			case 'songs':
				return $this->songs($filter, $exact, $limit, $offset, $add, $update, $auth);
			case 'song':
				return $this->song((int)$filter, $auth);
			case 'search_songs':
				return $this->search_songs($filter, $auth);
			case 'playlists':
				return $this->playlists($filter, $exact, $limit, $offset, $add, $update);
			case 'playlist':
				return $this->playlist((int)$filter);
			case 'playlist_songs':
				return $this->playlist_songs((int)$filter, $limit, $offset, $auth);
			case 'playlist_create':
				return $this->playlist_create();
			case 'playlist_edit':
				return $this->playlist_edit((int)$filter);
			case 'playlist_delete':
				return $this->playlist_delete((int)$filter);
			case 'playlist_add_song':
				return $this->playlist_add_song((int)$filter);
			case 'playlist_remove_song':
				return $this->playlist_remove_song((int)$filter);
			case 'playlist_generate':
				return $this->playlist_generate($filter, $limit, $offset, $auth);
			case 'tags':
				return $this->tags($filter, $exact, $limit, $offset);
			case 'tag':
				return $this->tag((int)$filter);
			case 'tag_artists':
				return $this->tag_artists((int)$filter, $limit, $offset, $auth);
			case 'tag_albums':
				return $this->tag_albums((int)$filter, $limit, $offset, $auth);
			case 'tag_songs':
				return $this->tag_songs((int)$filter, $limit, $offset, $auth);
			case 'flag':
				return $this->flag();
			case 'download':
				return $this->download((int)$id); // args 'type' and 'format' not supported
			case 'stream':
				return $this->stream((int)$id, $offset); // args 'type', 'bitrate', 'format', and 'length' not supported
			case 'get_art':
				return $this->get_art((int)$id);
		}

		$this->logger->log("Unsupported Ampache action '$action' requested", 'warn');
		throw new AmpacheException('Action not supported', 405);
	}

	/***********************
	 * Ampahce API methods *
	 ***********************/

	protected function handshake($user, $timestamp, $auth) {
		$currentTime = \time();
		$expiryDate = $currentTime + self::SESSION_EXPIRY_TIME;

		$this->checkHandshakeTimestamp($timestamp, $currentTime);
		$this->checkHandshakeAuthentication($user, $timestamp, $auth);
		$token = $this->startNewSession($user, $expiryDate);

		$updateTime = \max($this->library->latestUpdateTime($user), $this->playlistBusinessLayer->latestUpdateTime($user));
		$addTime = \max($this->library->latestInsertTime($user), $this->playlistBusinessLayer->latestInsertTime($user));

		return $this->ampacheResponse([
			'auth' => $token,
			'api' => self::API_VERSION,
			'update' => $updateTime->format('c'),
			'add' => $addTime->format('c'),
			'clean' => \date('c', $currentTime), // TODO: actual time of the latest item removal
			'songs' => $this->trackBusinessLayer->count($user),
			'artists' => $this->artistBusinessLayer->count($user),
			'albums' => $this->albumBusinessLayer->count($user),
			'playlists' => $this->playlistBusinessLayer->count($user) + 1, // +1 for "All tracks"
			'session_expire' => \date('c', $expiryDate),
			'tags' => $this->genreBusinessLayer->count($user),
			'videos' => 0,
			'catalogs' => 0
		]);
	}

	protected function goodbye($auth) {
		// getting the session should not throw as the middleware has already checked that the token is valid
		$session = $this->ampacheSessionMapper->findByToken($auth);
		$this->ampacheSessionMapper->delete($session);

		return $this->ampacheResponse(['success' => "goodbye: $auth"]);
	}

	protected function ping($auth) {
		$response = [
			'server' => $this->getAppNameAndVersion(),
			'version' => self::API_VERSION,
			'compatible' => self::API_MIN_COMPATIBLE_VERSION
		];

		if (!empty($auth)) {
			// getting the session should not throw as the middleware has already checked that the token is valid
			$session = $this->ampacheSessionMapper->findByToken($auth);
			$response['session_expire'] = \date('c', $session->getExpiry());
		}

		return $this->ampacheResponse($response);
	}

	protected function get_indexes($filter, $limit, $offset, $add, $update) {
		$type = $this->getRequiredParam('type');

		$businessLayer = $this->getBusinessLayer($type);
		$entities = $this->findEntities($businessLayer, $filter, false, $limit, $offset, $add, $update);
		return $this->renderEntitiesIndex($entities, $type);
	}

	protected function stats($filter, $limit, $offset, $auth) {
		$type = $this->getRequiredParam('type');
		$userId = $this->ampacheUser->getUserId();

		// Support for API v3.x: Originally, there was no 'filter' argument and the 'type'
		// argument had that role. The action only supported albums in this old format.
		// The 'filter' argument was added and role of 'type' changed in API v4.0.
		if (empty($filter)) {
			$filter = $type;
			$type = 'album';
		}

		if (!\in_array($type, ['song', 'album', 'artist'])) {
			throw new AmpacheException("Unsupported type $type", 400);
		}
		$businessLayer = $this->getBusinessLayer($type);

		switch ($filter) {
			case 'newest':
				$entities = $businessLayer->findAll($userId, SortBy::Newest, $limit, $offset);
				break;
			case 'flagged':
				$entities = $businessLayer->findAllStarred($userId, $limit, $offset);
				break;
			case 'random':
				$entities = $businessLayer->findAll($userId, SortBy::None);
				$indices = $this->random->getIndices(\count($entities), $offset, $limit, $userId, 'ampache_stats_'.$type);
				$entities = Util::arrayMultiGet($entities, $indices);
				break;
			case 'highest':		//TODO
			case 'frequent':	//TODO
			case 'recent':		//TODO
			case 'forgotten':	//TODO
			default:
				throw new AmpacheException("Unsupported filter $filter", 400);
		}

		return $this->renderEntities($entities, $type, $auth);
	}

	protected function artists($filter, $exact, $limit, $offset, $add, $update, $auth) {
		$artists = $this->findEntities($this->artistBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		return $this->renderArtists($artists, $auth);
	}

	protected function artist($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$artist = $this->artistBusinessLayer->find($artistId, $userId);
		return $this->renderArtists([$artist], $auth);
	}

	protected function artist_albums($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $userId);
		return $this->renderAlbums($albums, $auth);
	}

	protected function artist_songs($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByArtist($artistId, $userId);
		return $this->renderSongs($tracks, $auth);
	}

	protected function album_songs($albumId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);

		foreach ($tracks as &$track) {
			$track->setAlbum($album);
		}

		return $this->renderSongs($tracks, $auth);
	}

	protected function song($trackId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$track = $this->trackBusinessLayer->find($trackId, $userId);
		$trackInArray = [$track];
		return $this->renderSongs($trackInArray, $auth);
	}

	protected function songs($filter, $exact, $limit, $offset, $add, $update, $auth) {

		// optimized handling for fetching the whole library
		// note: the ordering of the songs differs between these two cases
		if (empty($filter) && !$limit && !$offset && empty($add) && empty($update)) {
			$tracks = $this->getAllTracks();
		}
		// general case
		else {
			$tracks = $this->findEntities($this->trackBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		}

		return $this->renderSongs($tracks, $auth);
	}

	protected function search_songs($filter, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByNameRecursive($filter, $userId);
		return $this->renderSongs($tracks, $auth);
	}

	protected function albums($filter, $exact, $limit, $offset, $add, $update, $auth) {
		$albums = $this->findEntities($this->albumBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		return $this->renderAlbums($albums, $auth);
	}

	protected function album($albumId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		return $this->renderAlbums([$album], $auth);
	}

	protected function playlists($filter, $exact, $limit, $offset, $add, $update) {
		$userId = $this->ampacheUser->getUserId();
		$playlists = $this->findEntities($this->playlistBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);

		// append "All tracks" if not searching by name, and it is not off-limit
		$allTracksIndex = $this->playlistBusinessLayer->count($userId);
		if (empty($filter) && empty($add) && empty($update)
				&& self::indexIsWithinOffsetAndLimit($allTracksIndex, $offset, $limit)) {
			$playlists[] = new AmpacheController_AllTracksPlaylist($userId, $this->trackBusinessLayer, $this->l10n);
		}

		return $this->renderPlaylists($playlists);
	}

	protected function playlist($listId) {
		$userId = $this->ampacheUser->getUserId();
		if ($listId == self::ALL_TRACKS_PLAYLIST_ID) {
			$playlist = new AmpacheController_AllTracksPlaylist($userId, $this->trackBusinessLayer, $this->l10n);
		} else {
			$playlist = $this->playlistBusinessLayer->find($listId, $userId);
		}
		return $this->renderPlaylists([$playlist]);
	}

	protected function playlist_songs($listId, $limit, $offset, $auth) {
		if ($listId == self::ALL_TRACKS_PLAYLIST_ID) {
			$playlistTracks = $this->getAllTracks();
			$playlistTracks = \array_slice($playlistTracks, $offset ?? 0, $limit);
		} else {
			$userId = $this->ampacheUser->getUserId();
			$playlistTracks = $this->playlistBusinessLayer->getPlaylistTracks($listId, $userId, $limit, $offset);
		}
		return $this->renderSongs($playlistTracks, $auth);
	}

	protected function playlist_create() {
		$name = $this->getRequiredParam('name');
		$playlist = $this->playlistBusinessLayer->create($name, $this->ampacheUser->getUserId());
		return $this->renderPlaylists([$playlist]);
	}

	protected function playlist_edit($listId) {
		$name = $this->request->getParam('name');
		$items = $this->request->getParam('items'); // track IDs
		$tracks = $this->request->getParam('tracks'); // 1-based indices of the tracks

		$edited = false;
		$userId = $this->ampacheUser->getUserId();
		$playlist = $this->playlistBusinessLayer->find($listId, $userId);

		if (!empty($name)) {
			$playlist->setName($name);
			$edited = true;
		}

		$newTrackIds = Util::explode(',', $items);
		$newTrackOrdinals = Util::explode(',', $tracks);

		if (\count($newTrackIds) != \count($newTrackOrdinals)) {
			throw new AmpacheException("Arguments 'items' and 'tracks' must contain equal amount of elements", 400);
		} elseif (\count($newTrackIds) > 0) {
			$trackIds = $playlist->getTrackIdsAsArray();

			for ($i = 0, $count = \count($newTrackIds); $i < $count; ++$i) {
				$trackId = $newTrackIds[$i];
				if (!$this->trackBusinessLayer->exists($trackId, $userId)) {
					throw new AmpacheException("Invalid song ID $trackId", 404);
				}
				$trackIds[$newTrackOrdinals[$i]-1] = $trackId;
			}

			$playlist->setTrackIdsFromArray($trackIds);
			$edited = true;
		}

		if ($edited) {
			$this->playlistBusinessLayer->update($playlist);
			return $this->ampacheResponse(['success' => 'playlist changes saved']);
		} else {
			throw new AmpacheException('Nothing was changed', 400);
		}
	}

	protected function playlist_delete($listId) {
		$this->playlistBusinessLayer->delete($listId, $this->ampacheUser->getUserId());
		return $this->ampacheResponse(['success' => 'playlist deleted']);
	}

	protected function playlist_add_song($listId) {
		$song = $this->getRequiredParam('song'); // track ID
		$check = $this->request->getParam('check', false);

		$userId = $this->ampacheUser->getUserId();
		if (!$this->trackBusinessLayer->exists($song, $userId)) {
			throw new AmpacheException("Invalid song ID $song", 404);
		}

		$playlist = $this->playlistBusinessLayer->find($listId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();

		if ($check && \in_array($song, $trackIds)) {
			throw new AmpacheException("Can't add a duplicate item when check is enabled", 400);
		}

		$trackIds[] = $song;
		$playlist->setTrackIdsFromArray($trackIds);
		$this->playlistBusinessLayer->update($playlist);
		return $this->ampacheResponse(['success' => 'song added to playlist']);
	}

	protected function playlist_remove_song($listId) {
		$song = $this->request->getParam('song'); // track ID
		$track = $this->request->getParam('track'); // 1-based index of the track
		$clear = $this->request->getParam('clear'); // added in API v420000 but we support this already now

		$playlist = $this->playlistBusinessLayer->find($listId, $this->ampacheUser->getUserId());

		if ((int)$clear === 1) {
			$trackIds = [];
			$message = 'all songs removed from playlist';
		} elseif ($song !== null) {
			$trackIds = $playlist->getTrackIdsAsArray();
			if (!\in_array($song, $trackIds)) {
				throw new AmpacheException("Song $song not found in playlist", 404);
			}
			$trackIds = Util::arrayDiff($trackIds, [$song]);
			$message = 'song removed from playlist';
		} elseif ($track !== null) {
			$trackIds = $playlist->getTrackIdsAsArray();
			if ($track < 1 || $track > \count($trackIds)) {
				throw new AmpacheException("Track ordinal $track is out of bounds", 404);
			}
			unset($trackIds[$track-1]);
			$message = 'song removed from playlist';
		} else {
			throw new AmpacheException("One of the arguments 'clear', 'song', 'track' is required", 400);
		}

		$playlist->setTrackIdsFromArray($trackIds);
		$this->playlistBusinessLayer->update($playlist);
		return $this->ampacheResponse(['success' => $message]);
	}

	protected function playlist_generate($filter, $limit, $offset, $auth) {
		$mode = $this->request->getParam('mode', 'random');
		$album = $this->request->getParam('album');
		$artist = $this->request->getParam('artist');
		$flag = $this->request->getParam('flag');
		$format = $this->request->getParam('format', 'song');

		$tracks = $this->findEntities($this->trackBusinessLayer, $filter, false); // $limit and $offset are applied later

		// filter the found tracks according to the additional requirements
		if ($album !== null) {
			$tracks = \array_filter($tracks, function ($track) use ($album) {
				return ($track->getAlbumId() == $album);
			});
		}
		if ($artist !== null) {
			$tracks = \array_filter($tracks, function ($track) use ($artist) {
				return ($track->getArtistId() == $artist);
			});
		}
		if ($flag == 1) {
			$tracks = \array_filter($tracks, function ($track) {
				return ($track->getStarred() !== null);
			});
		}
		// After filtering, there may be "holes" between the array indices. Reindex the array.
		$tracks = \array_values($tracks);

		// Arguments 'limit' and 'offset' are optional
		$limit = $limit ?? \count($tracks);
		$offset = $offset ?? 0;

		if ($mode == 'random') {
			$userId = $this->ampacheUser->getUserId();
			$indices = $this->random->getIndices(\count($tracks), $offset, $limit, $userId, 'ampache_playlist_generate');
			$tracks = Util::arrayMultiGet($tracks, $indices);
		} else { // 'recent', 'forgotten', 'unplayed'
			throw new AmpacheException("Mode '$mode' is not supported", 400);
		}

		switch ($format) {
			case 'song':
				return $this->renderSongs($tracks, $auth);
			case 'index':
				return $this->renderSongsIndex($tracks);
			case 'id':
				return $this->renderEntityIds($tracks);
			default:
				throw new AmpacheException("Format '$format' is not supported", 400);
		}
	}

	protected function tags($filter, $exact, $limit, $offset) {
		$genres = $this->findEntities($this->genreBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderTags($genres);
	}

	protected function tag($tagId) {
		$userId = $this->ampacheUser->getUserId();
		$genre = $this->genreBusinessLayer->find($tagId, $userId);
		return $this->renderTags([$genre]);
	}

	protected function tag_artists($genreId, $limit, $offset, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$artists = $this->artistBusinessLayer->findAllByGenre($genreId, $userId, $limit, $offset);
		return $this->renderArtists($artists, $auth);
	}

	protected function tag_albums($genreId, $limit, $offset, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$albums = $this->albumBusinessLayer->findAllByGenre($genreId, $userId, $limit, $offset);
		return $this->renderAlbums($albums, $auth);
	}

	protected function tag_songs($genreId, $limit, $offset, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByGenre($genreId, $userId, $limit, $offset);
		return $this->renderSongs($tracks, $auth);
	}

	protected function flag() {
		$type = $this->getRequiredParam('type');
		$id = $this->getRequiredParam('id');
		$flag = $this->getRequiredParam('flag');
		$flag = \filter_var($flag, FILTER_VALIDATE_BOOLEAN);

		if (!\in_array($type, ['song', 'album', 'artist'])) {
			throw new AmpacheException("Unsupported type $type", 400);
		}

		$userId = $this->ampacheUser->getUserId();
		$businessLayer = $this->getBusinessLayer($type);
		if ($flag) {
			$modifiedCount = $businessLayer->setStarred([$id], $userId);
			$message = "flag ADDED to $id";
		} else {
			$modifiedCount = $businessLayer->unsetStarred([$id], $userId);
			$message = "flag REMOVED from $id";
		}

		if ($modifiedCount > 0) {
			return $this->ampacheResponse(['success' => $message]);
		} else {
			throw new AmpacheException("The $type $id was not found", 404);
		}
	}

	protected function download(int $trackId) {
		$userId = $this->ampacheUser->getUserId();

		try {
			$track = $this->trackBusinessLayer->find($trackId, $userId);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $e->getMessage());
		}

		$files = $this->rootFolder->getUserFolder($userId)->getById($track->getFileId());

		if (\count($files) === 1) {
			return new FileResponse($files[0]);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	protected function stream(int $trackId, $offset) {
		// This is just a dummy implementation. We don't support transcoding or streaming
		// from a time offset.
		// All the other unsupported arguments are just ignored, but a request with an offset
		// is responded with an error. This is becuase the client would probably work in an
		// unexpected way if it thinks it's streaming from offset but actually it is streaming
		// from the beginning of the file. Returning an error gives the client a chance to fallback
		// to other methods of seeking.
		if ($offset !== null) {
			throw new AmpacheException('Streaming with time offset is not supported', 400);
		}

		return $this->download($trackId);
	}

	protected function get_art(int $id) {
		$type = $this->getRequiredParam('type');

		if (!\in_array($type, ['song', 'album', 'artist'])) {
			throw new AmpacheException("Unsupported type $type", 400);
		}

		if ($type === 'song') {
			// map song to its parent album
			$id = $this->trackBusinessLayer->find($id, $this->ampacheUser->getUserId())->getAlbumId();
			$type = 'album';
		}

		return $this->getCover($id, $this->getBusinessLayer($type));
	}

	/********************
	 * Helper functions *
	 ********************/

	private function getBusinessLayer($type) {
		switch ($type) {
			case 'song':		return $this->trackBusinessLayer;
			case 'album':		return $this->albumBusinessLayer;
			case 'artist':		return $this->artistBusinessLayer;
			case 'playlist':	return $this->playlistBusinessLayer;
			case 'tag':			return $this->genreBusinessLayer;
			default:			throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private function renderEntities($entities, $type, $auth) {
		switch ($type) {
			case 'song':		return $this->renderSongs($entities, $auth);
			case 'album':		return $this->renderAlbums($entities, $auth);
			case 'artist':		return $this->renderArtists($entities, $auth);
			case 'playlist':	return $this->renderPlaylists($entities);
			case 'tag':			return $this->renderTags($entities);
			default:			throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private function renderEntitiesIndex($entities, $type) {
		switch ($type) {
			case 'song':		return $this->renderSongsIndex($entities);
			case 'album':		return $this->renderAlbumsIndex($entities);
			case 'artist':		return $this->renderArtistsIndex($entities);
			case 'playlist':	return $this->renderPlaylistsIndex($entities);
			default:			throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private function getAppNameAndVersion() {
		$vendor = 'owncloud/nextcloud'; // this should get overridden by the next 'include'
		include \OC::$SERVERROOT . '/version.php';

		// Note: the following is deprecated since NC14 but the replacement
		// \OCP\App\IAppManager::getAppVersion is not available before NC14.
		$appVersion = \OCP\App::getAppVersion($this->appName);

		return "$vendor {$this->appName} $appVersion";
	}

	private function getCover(int $entityId, BusinessLayer $businessLayer) {
		$userId = $this->ampacheUser->getUserId();
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$entity = $businessLayer->find($entityId, $userId);

		try {
			$coverData = $this->coverHelper->getCover($entity, $userId, $userFolder);
			if ($coverData !== null) {
				return new FileResponse($coverData);
			}
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'entity not found');
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'entity has no cover');
	}

	private function checkHandshakeTimestamp($timestamp, $currentTime) {
		$providedTime = \intval($timestamp);

		if ($providedTime === 0) {
			throw new AmpacheException('Invalid Login - cannot parse time', 401);
		}
		if ($providedTime < ($currentTime - self::SESSION_EXPIRY_TIME)) {
			throw new AmpacheException('Invalid Login - session is outdated', 401);
		}
		// Allow the timestamp to be at maximum 10 minutes in the future. The client may use its
		// own system clock to generate the timestamp and that may differ from the server's time.
		if ($providedTime > $currentTime + 600) {
			throw new AmpacheException('Invalid Login - timestamp is in future', 401);
		}
	}

	private function checkHandshakeAuthentication($user, $timestamp, $auth) {
		$hashes = $this->ampacheUserMapper->getPasswordHashes($user);

		foreach ($hashes as $hash) {
			$expectedHash = \hash('sha256', $timestamp . $hash);

			if ($expectedHash === $auth) {
				return;
			}
		}

		throw new AmpacheException('Invalid Login - passphrase does not match', 401);
	}

	private function startNewSession($user, $expiryDate) {
		$token = Random::secure(16);

		// create new session
		$session = new AmpacheSession();
		$session->setUserId($user);
		$session->setToken($token);
		$session->setExpiry($expiryDate);

		// save session
		$this->ampacheSessionMapper->insert($session);

		return $token;
	}

	private function findEntities(
			BusinessLayer $businessLayer, $filter, $exact, $limit=null, $offset=null, $add=null, $update=null) : array {

		$userId = $this->ampacheUser->getUserId();

		// It's not documented, but Ampache supports also specifying date range on `add` and `update` parameters
		// by using '/' as separator. If there is no such separator, then the value is used as a lower limit.
		$add = Util::explode('/', $add);
		$update = Util::explode('/', $update);
		$addMin = $add[0] ?? null;
		$addMax = $add[1] ?? null;
		$updateMin = $update[0] ?? null;
		$updateMax = $update[1] ?? null;

		if ($filter) {
			$fuzzy = !((boolean) $exact);
			return $businessLayer->findAllByName($filter, $userId, $fuzzy, $limit, $offset, $addMin, $addMax, $updateMin, $updateMax);
		} else {
			return $businessLayer->findAll($userId, SortBy::Name, $limit, $offset, $addMin, $addMax, $updateMin, $updateMax);
		}
	}

	/**
	 * Getting all tracks with this helper is more efficient than with `findEntities`
	 * followed by a call to `albumBusinessLayer->find(...)` on each track.
	 * This is because, under the hood, the albums are fetched with a single DB query
	 * instead of fetching each separately.
	 *
	 * The result set is ordered first by artist and then by song title.
	 */
	private function getAllTracks() {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->library->getTracksAlbumsAndArtists($userId)['tracks'];
		\usort($tracks, ['\OCA\Music\Db\Track', 'compareArtistAndTitle']);
		foreach ($tracks as $index => &$track) {
			$track->setNumberOnPlaylist($index + 1);
		}
		return $tracks;
	}

	private function createAmpacheActionUrl($action, $id, $auth, $type=null) {
		$api = $this->jsonMode ? 'music.ampache.jsonApi' : 'music.ampache.xmlApi';
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute($api))
				. "?action=$action&id=$id&auth=$auth"
				. (!empty($type) ? "&type=$type" : '');
	}

	private function createCoverUrl($entity, $auth) {
		if ($entity instanceof Album) {
			$type = 'album';
		} elseif ($entity instanceof Artist) {
			$type = 'artist';
		} else {
			throw new AmpacheException('unexpeted entity type for cover image', 500);
		}

		if ($entity->getCoverFileId()) {
			return $this->createAmpacheActionUrl("get_art", $entity->getId(), $auth, $type);
		} else {
			return '';
		}
	}

	/**
	 * Any non-integer values and integer value 0 are converted to null to
	 * indicate "no limit" or "no offset".
	 * @param string $value
	 * @return integer|null
	 */
	private static function validateLimitOrOffset($value) : ?int {
		if (\ctype_digit(\strval($value)) && $value !== 0) {
			return \intval($value);
		} else {
			return null;
		}
	}

	/**
	 * @param int $index
	 * @param int|null $offset
	 * @param int|null $limit
	 * @return boolean
	 */
	private static function indexIsWithinOffsetAndLimit($index, $offset, $limit) {
		$offset = \intval($offset); // missing offset is interpreted as 0-offset
		return ($limit === null) || ($index >= $offset && $index < $offset + $limit);
	}

	private function renderArtists($artists, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$genreMap = Util::createIdLookupTable($this->genreBusinessLayer->findAll($userId));

		return $this->ampacheResponse([
			'artist' => \array_map(function ($artist) use ($userId, $genreMap, $auth) {
				return [
					'id' => (string)$artist->getId(),
					'name' => $artist->getNameString($this->l10n),
					'albums' => $this->albumBusinessLayer->countByArtist($artist->getId()),
					'songs' => $this->trackBusinessLayer->countByArtist($artist->getId()),
					'art' => $this->createCoverUrl($artist, $auth),
					'rating' => 0,
					'preciserating' => 0,
					'tag' => \array_map(function ($genreId) use ($genreMap) {
						return [
							'id' => (string)$genreId,
							'value' => $genreMap[$genreId]->getNameString($this->l10n),
							'count' => 1
						];
					}, $this->trackBusinessLayer->getGenresByArtistId($artist->getId(), $userId))
				];
			}, $artists)
		]);
	}

	private function renderAlbums($albums, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$genreMap = Util::createIdLookupTable($this->genreBusinessLayer->findAll($userId));

		return $this->ampacheResponse([
			'album' => \array_map(function ($album) use ($auth, $genreMap) {
				return [
					'id' => (string)$album->getId(),
					'name' => $album->getNameString($this->l10n),
					'artist' => [
						'id' => (string)$album->getAlbumArtistId(),
						'value' => $album->getAlbumArtistNameString($this->l10n)
					],
					'tracks' => $this->trackBusinessLayer->countByAlbum($album->getId()),
					'rating' => 0,
					'year' => $album->yearToAPI(),
					'art' => $this->createCoverUrl($album, $auth),
					'preciserating' => 0,
					'tag' => \array_map(function ($genreId) use ($genreMap) {
						return [
							'id' => (string)$genreId,
							'value' => $genreMap[$genreId]->getNameString($this->l10n),
							'count' => 1
						];
					}, $album->getGenres())
				];
			}, $albums)
		]);
	}

	private function renderSongs($tracks, $auth) {
		return $this->ampacheResponse([
			'song' => \array_map(function ($track) use ($auth) {
				$userId = $this->ampacheUser->getUserId();
				$album = $track->getAlbum()
						?: $this->albumBusinessLayer->findOrDefault($track->getAlbumId(), $userId);

				$result = [
					'id' => (string)$track->getId(),
					'title' => $track->getTitle() ?: '',
					'name' => $track->getTitle() ?: '',
					'artist' => [
						'id' => (string)$track->getArtistId() ?: '0',
						'value' => $track->getArtistNameString($this->l10n)
					],
					'albumartist' => [
						'id' => (string)$album->getAlbumArtistId() ?: '0',
						'value' => $album->getAlbumArtistNameString($this->l10n)
					],
					'album' => [
						'id' => (string)$album->getId() ?: '0',
						'value' => $album->getNameString($this->l10n)
					],
					'url' => $this->createAmpacheActionUrl('download', $track->getId(), $auth),
					'time' => $track->getLength(),
					'year' => $track->getYear(),
					'track' => $track->getAdjustedTrackNumber(),
					'bitrate' => $track->getBitrate(),
					'mime' => $track->getMimetype(),
					'size' => $track->getSize(),
					'art' => $this->createCoverUrl($album, $auth),
					'rating' => 0,
					'preciserating' => 0,
				];

				$genreId = $track->getGenreId();
				if ($genreId !== null) {
					$result['tag'] = [[
						'id' => (string)$genreId,
						'value' => $track->getGenreNameString($this->l10n),
						'count' => 1
					]];
				}
				return $result;
			}, $tracks)
		]);
	}

	private function renderPlaylists($playlists) {
		return $this->ampacheResponse([
			'playlist' => \array_map(function ($playlist) {
				return [
					'id' => (string)$playlist->getId(),
					'name' => $playlist->getName(),
					'owner' => $this->ampacheUser->getUserId(),
					'items' => $playlist->getTrackCount(),
					'type' => 'Private'
				];
			}, $playlists)
		]);
	}

	private function renderTags($genres) {
		return $this->ampacheResponse([
			'tag' => \array_map(function ($genre) {
				return [
					'id' => (string)$genre->getId(),
					'name' => $genre->getNameString($this->l10n),
					'albums' => $genre->getAlbumCount(),
					'artists' => $genre->getArtistCount(),
					'songs' => $genre->getTrackCount(),
					'videos' => 0,
					'playlists' => 0,
					'stream' => 0
				];
			}, $genres)
		]);
	}

	private function renderSongsIndex($tracks) {
		return $this->ampacheResponse([
			'song' => \array_map(function ($track) {
				return [
					'id' => (string)$track->getId(),
					'title' => $track->getTitle(),
					'name' => $track->getTitle(),
					'artist' => [
						'id' => (string)$track->getArtistId(),
						'value' => $track->getArtistNameString($this->l10n)
					],
					'album' => [
						'id' => (string)$track->getAlbumId(),
						'value' => $track->getAlbumNameString($this->l10n)
					]
				];
			}, $tracks)
		]);
	}

	private function renderAlbumsIndex($albums) {
		return $this->ampacheResponse([
			'album' => \array_map(function ($album) {
				return [
					'id' => (string)$album->getId(),
					'name' => $album->getNameString($this->l10n),
					'artist' => [
						'id' => (string)$album->getAlbumArtistId(),
						'value' => $album->getAlbumArtistNameString($this->l10n)
					]
				];
			}, $albums)
		]);
	}

	private function renderArtistsIndex($artists) {
		return $this->ampacheResponse([
			'artist' => \array_map(function ($artist) {
				$userId = $this->ampacheUser->getUserId();
				$albums = $this->albumBusinessLayer->findAllByArtist($artist->getId(), $userId);

				return [
					'id' => (string)$artist->getId(),
					'name' => $artist->getNameString($this->l10n),
					'album' => \array_map(function ($album) {
						return [
							'id' => (string)$album->getId(),
							'value' => $album->getNameString($this->l10n)
						];
					}, $albums)
				];
			}, $artists)
		]);
	}

	private function renderPlaylistsIndex($playlists) {
		return $this->ampacheResponse([
			'playlist' => \array_map(function ($playlist) {
				return [
					'id' => (string)$playlist->getId(),
					'name' => $playlist->getName(),
					'playlisttrack' => $playlist->getTrackIdsAsArray()
				];
			}, $playlists)
		]);
	}

	private function renderEntityIds($entities) {
		return $this->ampacheResponse(['id' => Util::extractIds($entities)]);
	}

	/**
	 * Array is considered to be "indexed" if its first element has numerical key.
	 * Empty array is considered to be "indexed".
	 * @param array $array
	 */
	private static function arrayIsIndexed(array $array) {
		\reset($array);
		return empty($array) || \is_int(\key($array));
	}

	/**
	 * The JSON API has some asymmetries with the XML API. This function makes the needed
	 * translations for the result content before it is converted into JSON.
	 * @param array $content
	 * @return array
	 */
	private static function prepareResultForJsonApi($content) {
		// In all responses returning an array of library entities, the root node is anonymous.
		// Unwrap the outermost array if it is an associative array with a single array-type value.
		if (\count($content) === 1 && !self::arrayIsIndexed($content)
				&& \is_array(\current($content)) && self::arrayIsIndexed(\current($content))) {
			$content = \array_pop($content);
		}

		// The key 'value' has a special meaning on XML responses, as it makes the corresponding value
		// to be treated as text content of the parent element. In the JSON API, these are mostly
		// substituted with property 'name', but error responses use the property 'message', instead.
		if (\array_key_exists('error', $content)) {
			$content = Util::convertArrayKeys($content, ['value' => 'message']);
		} else {
			$content = Util::convertArrayKeys($content, ['value' => 'name']);
		}
		return $content;
	}

	/**
	 * The XML API has some asymmetries with the JSON API. This function makes the needed
	 * translations for the result content before it is converted into XML.
	 * @param array $content
	 * @return array
	 */
	private static function prepareResultForXmlApi($content) {
		\reset($content);
		$firstKey = \key($content);

		// all 'entity list' kind of responses shall have the (deprecated) total_count element
		if ($firstKey == 'song' || $firstKey == 'album' || $firstKey == 'artist'
				|| $firstKey == 'playlist' || $firstKey == 'tag') {
			$content = ['total_count' => \count($content[$firstKey])] + $content;
		}

		// for some bizarre reason, the 'id' arrays have 'index' attributes in the XML format
		if ($firstKey == 'id') {
			$content['id'] = \array_map(function ($id, $index) {
				return ['index' => $index, 'value' => $id];
			}, $content['id'], \array_keys($content['id']));
		}

		return ['root' => $content];
	}

	private function getRequiredParam($paramName) {
		$param = $this->request->getParam($paramName);

		if ($param === null) {
			throw new AmpacheException("Required parameter '$paramName' missing", 400);
		}

		return $param;
	}
}

/**
 * Adapter class which acts like the Playlist class for the purpose of
 * AmpacheController::renderPlaylists but contains all the track of the user.
 */
class AmpacheController_AllTracksPlaylist {
	private $user;
	private $trackBusinessLayer;
	private $l10n;

	public function __construct($user, $trackBusinessLayer, $l10n) {
		$this->user = $user;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->l10n = $l10n;
	}

	public function getId() {
		return AmpacheController::ALL_TRACKS_PLAYLIST_ID;
	}

	public function getName() {
		return $this->l10n->t('All tracks');
	}

	public function getTrackCount() {
		return $this->trackBusinessLayer->count($this->user);
	}
}
