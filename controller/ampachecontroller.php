<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\Core\API;

use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Http\Request;
use \OCA\Music\AppFramework\Http\Http;
use \OCA\Music\AppFramework\Http\Response;

use \OCA\Music\Middleware\AmpacheException;

use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Db\AmpacheSession;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\TrackMapper;


use \OCA\Music\Http\FileResponse;

use \OCA\Music\Utility\AmpacheUser;


class AmpacheController extends Controller {

	private $ampacheUserMapper;
	private $ampacheSessionMapper;
	private $albumMapper;
	private $artistMapper;
	private $trackMapper;
	private $ampacheUser;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;

	private $sessionExpiryTime = 6000;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AmpacheUserMapper $ampacheUserMapper,
								AmpacheSessionMapper $ampacheSessionMapper,
								AlbumMapper $albumMapper,
								ArtistMapper $artistMapper,
								TrackMapper $trackMapper,
								AmpacheUser $ampacheUser,
								$rootFolder){
		parent::__construct($appname, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->albumMapper = $albumMapper;
		$this->artistMapper = $artistMapper;
		$this->trackMapper = $trackMapper;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;

		// used to share user info with middleware
		$this->ampacheUser = $ampacheUser;

		// used to deliver actual media file
		$this->rootFolder = $rootFolder;
	}


	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @AmpacheAPI
	 */
	public function ampache() {
		switch($this->params('action')) {
			case 'handshake':
				return $this->handshake();
			case 'ping':
				return $this->ping();
			case 'artists':
				return $this->artists();
			case 'artist_albums':
				return $this->artist_albums();
			case 'album_songs':
				return $this->album_songs();
			case 'albums':
				return $this->albums();
			case 'artist_songs':
				return $this->artist_songs();
			case 'songs':
				return $this->songs();
			case 'song':
				return $this->song();
			case 'search_songs':
				return $this->search_songs();
			# non Ampache API action - used for provide the file
			case 'play':
				return $this->play();
			case '_get_cover':
				return $this->get_cover();
		}
		throw new AmpacheException('TODO', 999);
	}

	/**
	 * JustPlayer fix
	 *
	 * router crashes if same route is defined for POST and GET
	 * so this just forwards to ampache()
	 *
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @AmpacheAPI
	 */
	public function ampache2() {
		return $this->ampache();
	}

	protected function handshake() {
		$userId = $this->params('user');
		$timestamp = $this->params('timestamp');
		$authToken = $this->params('auth');

		// prepare hash check
		$hashes = $this->ampacheUserMapper->getPasswordHashes($userId);

		// prepare time check
		$currentTime = time();
		$providedTime = intval($timestamp);

		if($providedTime === 0) {
			throw new AmpacheException('Invalid Login - cannot parse time', 401);
		}
		if($providedTime < ($currentTime - $this->sessionExpiryTime)) {
			throw new AmpacheException('Invalid Login - session is outdated', 401);
		}
		// TODO - while testing with tomahawk it sometimes is $currenttime+1 ... needs further investigation
		if($providedTime > $currentTime + 100) {
			throw new AmpacheException('Invalid Login - timestamp is in future', 401);
		}

		$validTokenFound = false;

		foreach ($hashes as $hash) {
			$expectedHash = hash('sha256', $timestamp . $hash);

			if($expectedHash === $authToken) {
				$validTokenFound = true;
				break;
			}
		}

		if($validTokenFound === false) {
			throw new AmpacheException('Invalid Login - passphrase does not match', 401);
		}

		// this can cause collision, but it's just a temporary token
		$token = md5(uniqid(rand(), true));
		$expiryDate = $currentTime + $this->sessionExpiryTime;

		// create new session
		$session = new AmpacheSession();
		$session->setUserId($userId);
		$session->setToken($token);
		$session->setExpiry($expiryDate);

		// save session
		$this->ampacheSessionMapper->insert($session);

		// return counts
		$artistCount = $this->artistMapper->count($userId);
		$albumCount = $this->albumMapper->count($userId);
		$trackCount = $this->trackMapper->count($userId);

		return $this->render(
			'ampache/handshake',
			array(
				'token' => $token,
				'songCount' => $trackCount,
				'artistCount' => $artistCount,
				'albumCount' => $albumCount,
				'playlistCount' => 0,
				'updateDate' => $currentTime,
				'cleanDate' => $currentTime,
				'addDate' => $currentTime,
				'expireDate' => $expiryDate
			),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function ping() {
		$token = $this->params('auth');

		if($token !== null && $token !== '') {
			$this->ampacheSessionMapper->extend($token, time() + $this->sessionExpiryTime);
		}

		return $this->render(
			'ampache/ping',
			array(),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function artists() {
		$userId = $this->ampacheUser->getUserId();

		// filter
		$filter = $this->params('filter');
		$fuzzy = !((boolean) $this->params('exact'));

		// TODO add & update

		if ($filter) {
			$artists = $this->artistMapper->findAllByName($filter, $userId, $fuzzy);
		} else {
			$artists = $this->artistMapper->findAll($userId);
		}

		// set album and track count for artists
		foreach($artists as &$artist) {
			$artist->setAlbumCount($this->albumMapper->countByArtist($artist->getId(), $userId));
			$artist->setTrackCount($this->trackMapper->countByArtist($artist->getId(), $userId));
		}

		return $this->render(
			'ampache/artists',
			array('artists' => $artists),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function artist_albums() {
		$userId = $this->ampacheUser->getUserId();
		$artistId = $this->params('filter');

		// this is used to fill in the artist information for each album
		$artist = $this->artistMapper->find($artistId, $userId);
		$albums = $this->albumMapper->findAllByArtist($artistId, $userId);

		// set album and track count for artists
		foreach($albums as &$album) {
			$album->setTrackCount($this->trackMapper->countByAlbum($album->getId(), $userId));
			$album->setArtist($artist);
		}

		return $this->render(
			'ampache/albums',
			array('albums' => $albums, 'l10n' => $this->l10n, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);

	}

	protected function artist_songs() {
		$userId = $this->ampacheUser->getUserId();
		$artistId = $this->params('filter');

		// this is used to fill in the artist information for each album
		$artist = $this->artistMapper->find($artistId, $userId);
		$tracks = $this->trackMapper->findAllByArtist($artistId, $userId);

		// set album and track count for artists
		foreach($tracks as &$track) {
			$track->setArtist($artist);
			$track->setAlbum($this->albumMapper->find($track->getAlbumId(), $userId));
		}

		return $this->render(
			'ampache/songs',
			array('songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);

	}

	protected function album_songs() {
		$userId = $this->ampacheUser->getUserId();
		$albumId = $this->params('filter');

		// this is used to fill in the album information for each track
		$album = $this->albumMapper->find($albumId, $userId);
		$tracks = $this->trackMapper->findAllByAlbum($albumId, $userId);

		// set album and track count for artists
		foreach($tracks as &$track) {
			$track->setArtist($this->artistMapper->find($track->getArtistId(), $userId));
			$track->setAlbum($album);
		}

		return $this->render(
			'ampache/songs',
			array('songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);

	}

	protected function song() {
		$userId = $this->ampacheUser->getUserId();
		$trackId = $this->params('filter');

		$track = $this->trackMapper->find($trackId, $userId);

		// set album and track count for artists
		$track->setArtist($this->artistMapper->find($track->getArtistId(), $userId));
		$track->setAlbum($this->albumMapper->find($track->getAlbumId(), $userId));

		return $this->render(
			'ampache/songs',
			array('songs' => array($track), 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);

	}

	protected function songs() {
		$userId = $this->ampacheUser->getUserId();

		// filter
		$filter = $this->params('filter');
		$fuzzy = !((boolean) $this->params('exact'));
		$limit = intval($this->params('limit'));
		$offset = intval($this->params('offset'));

		// TODO add & update

		if ($filter) {
			$tracks = $this->trackMapper->findAllByName($filter, $userId, $fuzzy);
		} else {
			$tracks = $this->trackMapper->findAll($userId, $limit, $offset);
		}

		// set album and artist for tracks
		foreach($tracks as &$track) {
			$track->setArtist($this->artistMapper->find($track->getArtistId(), $userId));
			$track->setAlbum($this->albumMapper->find($track->getAlbumId(), $userId));
		}

		return $this->render(
			'ampache/songs',
			array('songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function search_songs() {
		$userId = $this->ampacheUser->getUserId();

		// filter
		$filter = $this->params('filter');

		$tracks = $this->trackMapper->findAllByNameRecursive($filter, $userId);

		// set album and artist for tracks
		foreach($tracks as &$track) {
			$track->setArtist($this->artistMapper->find($track->getArtistId(), $userId));
			$track->setAlbum($this->albumMapper->find($track->getAlbumId(), $userId));
		}

		return $this->render(
			'ampache/songs',
			array('songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function albums() {
		$userId = $this->ampacheUser->getUserId();

		// filter
		$filter = $this->params('filter');
		$fuzzy = !((boolean) $this->params('exact'));

		// TODO add & update

		if ($filter) {
			$albums = $this->albumMapper->findAllByName($filter, $userId, $fuzzy);
		} else {
			$albums = $this->albumMapper->findAll($userId);
		}

		$albumIds = array();

		// set track count for artists
		foreach($albums as &$album) {
			$album->setTrackCount($this->trackMapper->countByAlbum($album->getId(), $userId));
			$albumIds[] = $album->getId();
		}

		$albumWithArtistIds = $this->albumMapper->getAlbumArtistsByAlbumId($albumIds);

		// this function is used to extract the first artistId of each album
		$mapFunction = function($value) {
			if (count($value)) {
				// as Ampache only supports one artist per album
				// we only return the first one
				return $value[0];
			}
		};

		// map this array to retrieve all artist ids and make it unique it
		$artistIds = array_unique(array_map($mapFunction, $albumWithArtistIds));

		$artists = $this->artistMapper->findMultipleById($artistIds, $userId);

		$mappedArtists = array();
		foreach ($artists as $artist) {
			$mappedArtists[$artist->getId()] = $artist;
		}

		// set track count for artists
		foreach($albums as &$album) {
			if (count($albumWithArtistIds[$album->getId()])) {
				// as Ampache only supports one artist per album
				// we only use the first one
				$album->setArtist($mappedArtists[$albumWithArtistIds[$album->getId()][0]]);
			}
		}

		return $this->render(
			'ampache/albums',
			array('albums' => $albums, 'l10n' => $this->l10n, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $this->params('auth')),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}

	protected function play() {
		$userId = $this->ampacheUser->getUserId();
		$trackId = $this->params('filter');

		try {
			$track = $this->trackMapper->find($trackId, $userId);
		} catch(DoesNotExistException $e) {
			$r = new Response();
			$r->setStatus(Http::STATUS_NOT_FOUND);
			return $r;
		}

		$files = $this->rootFolder->getById($track->getFileId());

		if(count($files) === 1) {
			return new FileResponse($files[0]);
		} else {
			$r = new Response();
			$r->setStatus(Http::STATUS_NOT_FOUND);
			return $r;
		}
	}

	/* this is not ampache proto */
	protected function get_cover() {
		$userId = $this->ampacheUser->getUserId();
		$albumId = $this->params('filter');

		try {
			$album = $this->albumMapper->find($albumId, $userId);
		} catch(DoesNotExistException $e) {
			$r = new Response();
			$r->setStatus(Http::STATUS_NOT_FOUND);
			return $r;
		}

		$files = $this->rootFolder->getById($album->getCoverFileId());

		if(count($files) === 1) {
			return new FileResponse($files[0]);
		} else {
			$r = new Response();
			$r->setStatus(Http::STATUS_NOT_FOUND);
			return $r;
		}
	}
}
