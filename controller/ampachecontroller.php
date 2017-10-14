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
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\Middleware\AmpacheException;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Db\AmpacheSession;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\FileResponse;

use \OCA\Music\Utility\AmpacheUser;
use \OCA\Music\Utility\CoverHelper;


class AmpacheController extends Controller {

	private $ampacheUserMapper;
	private $ampacheSessionMapper;
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $trackBusinessLayer;
	private $ampacheUser;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;
	private $coverHelper;

	private $sessionExpiryTime = 6000;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AmpacheUserMapper $ampacheUserMapper,
								AmpacheSessionMapper $ampacheSessionMapper,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								AmpacheUser $ampacheUser,
								$rootFolder,
								CoverHelper $coverHelper) {
		parent::__construct($appname, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;

		// used to share user info with middleware
		$this->ampacheUser = $ampacheUser;

		// used to deliver actual media file
		$this->rootFolder = $rootFolder;

		$this->coverHelper = $coverHelper;
	}


	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @AmpacheAPI
	 */
	public function ampache($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset) {
		switch($action) {
			case 'handshake':
				return $this->handshake($user, $timestamp, $auth);
			case 'ping':
				return $this->ping($auth);
			case 'artists':
				return $this->artists($filter, $exact);
			case 'artist_albums':
				return $this->artist_albums($filter, $auth);
			case 'album_songs':
				return $this->album_songs($filter, $auth);
			case 'albums':
				return $this->albums($filter, $exact, $auth);
			case 'artist_songs':
				return $this->artist_songs($filter, $auth);
			case 'songs':
				return $this->songs($filter, $exact, $limit, $offset, $auth);
			case 'song':
				return $this->song($filter, $auth);
			case 'search_songs':
				return $this->search_songs($filter, $auth);
			# non Ampache API action - used for provide the file
			case 'play':
				return $this->play($filter);
			case '_get_cover':
				return $this->get_cover($filter);
		}
		throw new AmpacheException('Action not supported', 405);
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
	public function ampache2($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset) {
		return $this->ampache($action, $user, $timestamp, $auth, $filter, $exact, $limit, $offset);
	}

	protected function handshake($user, $timestamp, $auth) {
		// prepare hash check
		$hashes = $this->ampacheUserMapper->getPasswordHashes($user);

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

			if($expectedHash === $auth) {
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
		$session->setUserId($user);
		$session->setToken($token);
		$session->setExpiry($expiryDate);

		// save session
		$this->ampacheSessionMapper->insert($session);

		// return counts
		$artistCount = $this->artistBusinessLayer->count($user);
		$albumCount = $this->albumBusinessLayer->count($user);
		$trackCount = $this->trackBusinessLayer->count($user);

		return $this->renderXml(
			'ampache/handshake',
			[
				'token' => $token,
				'songCount' => $trackCount,
				'artistCount' => $artistCount,
				'albumCount' => $albumCount,
				'playlistCount' => 0,
				'updateDate' => $currentTime,
				'cleanDate' => $currentTime,
				'addDate' => $currentTime,
				'expireDate' => $expiryDate
			]
		);
	}

	protected function ping($auth) {
		if($auth !== null && $auth !== '') {
			$this->ampacheSessionMapper->extend($auth, time() + $this->sessionExpiryTime);
		}

		return $this->renderXml('ampache/ping', []);
	}

	protected function artists($filter, $exact) {
		$userId = $this->ampacheUser->getUserId();

		// TODO add & update

		if ($filter) {
			$fuzzy = !((boolean) $exact);
			$artists = $this->artistBusinessLayer->findAllByName($filter, $userId, $fuzzy);
		} else {
			$artists = $this->artistBusinessLayer->findAll($userId, SortBy::Name);
		}

		// set album and track count for artists
		foreach($artists as &$artist) {
			$artist->setAlbumCount($this->albumBusinessLayer->countByArtist($artist->getId()));
			$artist->setTrackCount($this->trackBusinessLayer->countByArtist($artist->getId()));
		}

		return $this->renderXml('ampache/artists', ['artists' => $artists]);
	}

	protected function artist_albums($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $userId);

		// set album and track count for artists
		foreach($albums as &$album) {
			$album->setTrackCount($this->trackBusinessLayer->countByAlbum($album->getId()));
			$albumArtist = $this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId);
			$album->setAlbumArtist($albumArtist);
		}

		return $this->renderXml(
			'ampache/albums',
			['albums' => $albums, 'l10n' => $this->l10n, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);

	}

	protected function artist_songs($artistId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		// this is used to fill in the artist information for each album
		$artist = $this->artistBusinessLayer->find($artistId, $userId);
		$tracks = $this->trackBusinessLayer->findAllByArtist($artistId, $userId);

		// set album and track count for artists
		foreach($tracks as &$track) {
			$track->setArtist($artist);
			$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
			$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));
			$track->setAlbum($album);
		}

		return $this->renderXml(
			'ampache/songs',
			['songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);

	}

	protected function album_songs($albumId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		// this is used to fill in the album information for each track
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);

		// set album and track count for artists
		foreach($tracks as &$track) {
			$track->setArtist($this->artistBusinessLayer->find($track->getArtistId(), $userId));
			$track->setAlbum($album);
		}

		return $this->renderXml(
			'ampache/songs',
			['songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);

	}

	protected function song($trackId, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$track = $this->trackBusinessLayer->find($trackId, $userId);

		// set album and track count for artists
		$track->setArtist($this->artistBusinessLayer->find($track->getArtistId(), $userId));
		$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
		$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));
		$track->setAlbum($album);

		return $this->renderXml(
			'ampache/songs',
			['songs' => array($track), 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);

	}

	protected function songs($filter, $exact, $limit, $offset, $auth) {
		$userId = $this->ampacheUser->getUserId();

		// TODO add & update

		if ($filter) {
			$fuzzy = !((boolean) $exact);
			$tracks = $this->trackBusinessLayer->findAllByName($filter, $userId, $fuzzy);
		} else {
			if($limit === 0) {
				$limit = null;
			}
			if($offset === 0) {
				$offset = null;
			}

			$tracks = $this->trackBusinessLayer->findAll($userId, SortBy::Name, $limit, $offset);
		}

		// set album and artist for tracks
		foreach($tracks as &$track) {
			$track->setArtist($this->artistBusinessLayer->find($track->getArtistId(), $userId));
			$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
			$album->setAlbumArtist($this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId));
			$track->setAlbum($album);
		}

		return $this->renderXml(
			'ampache/songs',
			['songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);
	}

	protected function search_songs($filter, $auth) {
		$userId = $this->ampacheUser->getUserId();

		$tracks = $this->trackBusinessLayer->findAllByNameRecursive($filter, $userId);

		// set album and artist for tracks
		foreach($tracks as &$track) {
			$track->setArtist($this->artistBusinessLayer->find($track->getArtistId(), $userId));
			$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
			$album->setAlbumArtist($this->artistBusinessLayer->find($track->getArtistId(), $userId));
			$track->setAlbum($album);
		}

		return $this->renderXml(
			'ampache/songs',
			['songs' => $tracks, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);
	}

	protected function albums($filter, $exact, $auth) {
		$userId = $this->ampacheUser->getUserId();

		// TODO add & update

		if ($filter) {
			$fuzzy = !((boolean) $exact);
			$albums = $this->albumBusinessLayer->findAllByName($filter, $userId, $fuzzy);
		} else {
			$albums = $this->albumBusinessLayer->findAll($userId, SortBy::Name);
		}

		// set track count for artists
		foreach($albums as &$album) {
			$album->setTrackCount($this->trackBusinessLayer->countByAlbum($album->getId()));
			$albumArtist = $this->artistBusinessLayer->find($album->getAlbumArtistId(), $userId);
			$album->setAlbumArtist($albumArtist);
		}

		return $this->renderXml(
			'ampache/albums',
			['albums' => $albums, 'l10n' => $this->l10n, 'urlGenerator' => $this->urlGenerator, 'authtoken' => $auth]
		);
	}

	protected function play($trackId) {
		$userId = $this->ampacheUser->getUserId();

		try {
			$track = $this->trackBusinessLayer->find($trackId, $userId);
		} catch(BusinessLayerException $e) {
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
	protected function get_cover($albumId) {
		$userId = $this->ampacheUser->getUserId();

		try {
			$coverData = $this->coverHelper->getCover($albumId, $userId, $this->rootFolder);
			if ($coverData !== NULL) {
				return new FileResponse($coverData);
			}
		}
		catch(BusinessLayerException $e) {
			// album not found
		}

		// no cover found
		$r = new Response();
		$r->setStatus(Http::STATUS_NOT_FOUND);
		return $r;
	}

	protected function renderXml($templateName, $params) {
		$response = new TemplateResponse($this->appName, $templateName, $params, 'blank');
		$response->setHeaders(['Content-Type' => 'text/xml']);
		return $response;
	}
}
