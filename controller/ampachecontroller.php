<?php

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
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Middleware\AmpacheException;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Db\AmpacheSession;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;
use \OCA\Music\Http\XMLResponse;

use \OCA\Music\Utility\AmpacheUser;
use \OCA\Music\Utility\CoverHelper;

class AmpacheController extends Controller {
	private $ampacheUserMapper;
	private $ampacheSessionMapper;
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $playlistBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $ampacheUser;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;
	private $coverHelper;
	private $logger;

	const SESSION_EXPIRY_TIME = 6000;
	const ALL_TRACKS_PLAYLIST_ID = 10000000;
	const API_VERSION = 350001;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AmpacheUserMapper $ampacheUserMapper,
								AmpacheSessionMapper $ampacheSessionMapper,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								AmpacheUser $ampacheUser,
								$rootFolder,
								CoverHelper $coverHelper,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
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
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @AmpacheAPI
	 */
	public function ampache($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset) {
		$this->logger->log("Ampache action '$action' requested", 'debug');

		$limit = self::validateLimitOrOffset($limit);
		$offset = self::validateLimitOrOffset($offset);

		switch ($action) {
			case 'handshake':
				return $this->handshake($user, $timestamp, $auth);
			case 'ping':
				return $this->ping($auth);
			case 'artists':
				return $this->artists($filter, $exact, $limit, $offset);
			case 'artist':
				return $this->artist($filter);
			case 'artist_albums':
				return $this->artist_albums($filter, $auth);
			case 'album_songs':
				return $this->album_songs($filter, $auth);
			case 'albums':
				return $this->albums($filter, $exact, $limit, $offset, $auth);
			case 'album':
				return $this->album($filter, $auth);
			case 'artist_songs':
				return $this->artist_songs($filter, $auth);
			case 'songs':
				return $this->songs($filter, $exact, $limit, $offset, $auth);
			case 'song':
				return $this->song($filter, $auth);
			case 'search_songs':
				return $this->search_songs($filter, $auth);
			case 'playlists':
				return $this->playlists($filter, $exact, $limit, $offset);
			case 'playlist':
				return $this->playlist($filter);
			case 'playlist_songs':
				return $this->playlist_songs($filter, $limit, $offset, $auth);
			# non Ampache API action - used for provide the file
			case 'play':
				return $this->play($filter);
			case '_get_cover':
				return $this->get_cover($filter);
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

		$currentTimeFormated = \date('c', $currentTime);
		$expiryDateFormated = \date('c', $expiryDate);

		return new XMLResponse(['root' => [
			'auth' => [$token],
			'version' => [self::API_VERSION],
			'update' => [$currentTimeFormated],
			'add' => [$currentTimeFormated],
			'clean' => [$currentTimeFormated],
			'songs' => [$this->trackBusinessLayer->count($user)],
			'artists' => [$this->artistBusinessLayer->count($user)],
			'albums' => [$this->albumBusinessLayer->count($user)],
			'playlists' => [$this->playlistBusinessLayer->count($user) + 1], // +1 for "All tracks"
			'session_expire' => [$expiryDateFormated],
			'tags' => [0],
			'videos' => [0]
		]]);
	}

	protected function ping($auth) {
		if ($auth !== null && $auth !== '') {
			$this->ampacheSessionMapper->extend($auth, \time() + self::SESSION_EXPIRY_TIME);
		}

		return new XMLResponse(['root' => [
			'version' => [self::API_VERSION]
		]]);
	}

	protected function artists($filter, $exact, $limit, $offset) {
		$artists = $this->findEntities($this->artistBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderArtists($artists);
	}

	protected function artist($artistId) {
		$userId = $this->ampacheUser->getUserId();
		$artist = $this->artistBusinessLayer->find($artistId, $userId);
		return $this->renderArtists([$artist]);
	}

	protected function artist_albums($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $userId);
		return $this->renderAlbums($albums, $auth);
	}

	protected function artist_songs($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$artist = $this->artistBusinessLayer->find($artistId, $userId);
		$tracks = $this->trackBusinessLayer->findAllByArtist($artistId, $userId);
		$this->injectArtistAndAlbum($tracks, $artist);
		return $this->renderSongs($tracks, $auth);
	}

	protected function album_songs($albumId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));

		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
		$this->injectArtistAndAlbum($tracks, null, $album);

		return $this->renderSongs($tracks, $auth);
	}

	protected function song($trackId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$track = $this->trackBusinessLayer->find($trackId, $userId);
		$trackInArray = [$track];
		$this->injectArtistAndAlbum($trackInArray);
		return $this->renderSongs($trackInArray, $auth);
	}

	protected function songs($filter, $exact, $limit, $offset, $auth) {

		// optimized handling for fetching the whole library
		// note: the ordering of the songs differs between these two cases
		if (empty($filter) && !$limit && !$offset) {
			$tracks = $this->getAllTracks();
		}
		// general case
		else {
			$tracks = $this->findEntities($this->trackBusinessLayer, $filter, $exact, $limit, $offset);
			$this->injectArtistAndAlbum($tracks);
		}

		return $this->renderSongs($tracks, $auth);
	}

	protected function search_songs($filter, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByNameRecursive($filter, $userId);
		$this->injectArtistAndAlbum($tracks);
		return $this->renderSongs($tracks, $auth);
	}

	protected function albums($filter, $exact, $limit, $offset, $auth) {
		$albums = $this->findEntities($this->albumBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderAlbums($albums, $auth);
	}

	protected function album($albumId, $auth) {
		$userId = $this->ampacheUser->getUserId();
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		return $this->renderAlbums([$album], $auth);
	}

	protected function playlists($filter, $exact, $limit, $offset) {
		$userId = $this->ampacheUser->getUserId();
		$playlists = $this->findEntities($this->playlistBusinessLayer, $filter, $exact, $limit, $offset);

		// append "All tracks" if not searching by name, and it is not off-limit
		if (empty($filter) && ($limit === null || \count($playlists) < $limit)) {
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
			$playlistTracks = \array_slice($playlistTracks, $offset, $limit);
		}
		else {
			$userId = $this->ampacheUser->getUserId();
			$playlistTracks = $this->playlistBusinessLayer->getPlaylistTracks($listId, $userId, $limit, $offset);
			$this->injectArtistAndAlbum($playlistTracks);
		}
		return $this->renderSongs($playlistTracks, $auth);
	}

	protected function play($trackId) {
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

	/* this is not ampache proto */
	protected function get_cover($albumId) {
		$userId = $this->ampacheUser->getUserId();
		$userFolder = $this->rootFolder->getUserFolder($userId);

		try {
			$coverData = $this->coverHelper->getCover($albumId, $userId, $userFolder);
			if ($coverData !== null) {
				return new FileResponse($coverData);
			}
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'album not found');
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'album has no cover');
	}


	/********************
	 * Helper functions *
	 ********************/

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
		// this can cause collision, but it's just a temporary token
		$token = \md5(\uniqid(\rand(), true));

		// create new session
		$session = new AmpacheSession();
		$session->setUserId($user);
		$session->setToken($token);
		$session->setExpiry($expiryDate);

		// save session
		$this->ampacheSessionMapper->insert($session);

		return $token;
	}

	private function findEntities(BusinessLayer $businessLayer, $filter, $exact, $limit=null, $offset=null) {
		$userId = $this->ampacheUser->getUserId();

		if ($filter) {
			$fuzzy = !((boolean) $exact);
			return $businessLayer->findAllByName($filter, $userId, $fuzzy, $limit, $offset);
		} else {
			return $businessLayer->findAll($userId, SortBy::Name, $limit, $offset);
		}
	}

	/**
	 * Getting all tracks with this helper is more efficient than with `findEntities`
	 * followed by `injectArtistAndAlbum`. This is because, under the hood, the albums
	 * and artists are fetched with a single DB query instead of fetching each separately.
	 * 
	 * The result set is ordered first by artist and then by song title.
	 */
	private function getAllTracks() {
		$userId = $this->ampacheUser->getUserId();
		$tracks = $this->library->getTracksAlbumsAndArtists($userId)['tracks'];
		\usort($tracks, ['\OCA\Music\Db\Track', 'compareArtistAndTitle']);
		return $tracks;
	}

	private static function createAmpacheActionUrl($urlGenerator, $action, $filter, $auth) {
		return $urlGenerator->getAbsoluteURL($urlGenerator->linkToRoute('music.ampache.ampache'))
				. "?action=$action&filter=$filter&auth=$auth";
	}

	private static function createAlbumCoverUrl($urlGenerator, $album, $auth) {
		if ($album->getCoverFileId()) {
			return self::createAmpacheActionUrl($urlGenerator, '_get_cover', $album->getId(), $auth);
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
	private static function validateLimitOrOffset($value) {
		if (\ctype_digit(\strval($value)) && $value !== 0) {
			return \intval($value);
		} else {
			return null;
		}
	}

	private function renderArtists($artists) {
		return new XMLResponse(['root' => ['artist' => \array_map(function($artist) {
			return [
				'id' => $artist->getId(),
				'name' => [$artist->getNameString($this->l10n)],
				'albums' => [$this->albumBusinessLayer->countByArtist($artist->getId())],
				'songs' => [$this->trackBusinessLayer->countByArtist($artist->getId())],
				'rating' => [0],
				'preciserating' => [0]
			];
		}, $artists)]]);
	}

	private function renderAlbums($albums, $auth) {
		$userId = $this->ampacheUser->getUserId();

		return new XMLResponse(['root' => ['album' => \array_map(function($album) use ($userId, $auth) {
			$artist = $this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId);
			return [
				'id' => $album->getId(),
				'name' => [$album->getNameString($this->l10n)],
				'artist' => [
					'id' => $artist->getId(),
					'value' => $artist->getNameString($this->l10n)
				],
				'tracks' => [$this->trackBusinessLayer->countByAlbum($album->getId())],
				'rating' => [0],
				'year' => [$album->yearToAPI()],
				'art' => [self::createAlbumCoverUrl($this->urlGenerator, $album, $auth)],
				'preciserating' => [0]
			];
		}, $albums)]]);
	}

	private function injectArtistAndAlbum(&$tracks, $commonArtist=null, $commonAlbum=null) {
		$userId = $this->ampacheUser->getUserId();

		foreach ($tracks as &$track) {
			$artist = $commonArtist ?: $this->artistBusinessLayer->find($track->getArtistId(), $userId);
			$track->setArtist($artist);

			if (!empty($commonAlbum)) {
				$track->setAlbum($commonAlbum);
			} else {
				$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
				$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));
				$track->setAlbum($album);
			}
		}
	}

	private function renderSongs($tracks, $auth) {
		return new XMLResponse(['root' => ['song' => \array_map(function($track) use ($auth) {
			$artist = $track->getArtist();
			$album = $track->getAlbum();
			$albumArtist = $album->getAlbumArtist();

			return [
				'id' => $track->getId(),
				'title' => [$track->getTitle()],
				'artist' => [
					'id' => $artist->getId(),
					'value' => $artist->getNameString($this->l10n)
				],
				'albumartist' => [
					'id' => $albumArtist->getId(),
					'value' => $albumArtist->getNameString($this->l10n)
				],
				'album' => [
					'id' => $album->getId(),
					'value' => $album->getNameString($this->l10n)
				],
				'url' => [self::createAmpacheActionUrl($this->urlGenerator, 'play', $track->getId(), $auth)],
				'time' => [$track->getLength()],
				'track' => [$track->getDiskAdjustedTrackNumber()],
				'bitrate' => [$track->getBitrate()],
				'mime' => [$track->getMimetype()],
				'size' => [$track->getSize()],
				'art' => [self::createAlbumCoverUrl($this->urlGenerator, $album, $auth)],
				'rating' => [0],
				'preciserating' => [0]
			];
		}, $tracks)]]);
	}

	private function renderPlaylists($playlists) {
		return new XMLResponse(['root' => ['playlist' => \array_map(function($playlist) {
			return [
				'id' => $playlist->getId(),
				'name' => [$playlist->getName()],
				'owner' => [$this->ampacheUser->getUserId()],
				'items' => [$playlist->getTrackCount()],
				'type' => ['Private']
			];
		}, $playlists)]]);
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
