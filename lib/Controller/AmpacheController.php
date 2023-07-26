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
 * @copyright Pauli Järvinen 2017 - 2023
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\AppFramework\Utility\MethodAnnotationReader;
use OCA\Music\AppFramework\Utility\RequestParameterExtractor;
use OCA\Music\AppFramework\Utility\RequestParameterExtractorException;

use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\BookmarkBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\Library;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;

use OCA\Music\Db\Album;
use OCA\Music\Db\AmpacheSession;
use OCA\Music\Db\Artist;
use OCA\Music\Db\Bookmark;
use OCA\Music\Db\Entity;
use OCA\Music\Db\Genre;
use OCA\Music\Db\MatchMode;
use OCA\Music\Db\Playlist;
use OCA\Music\Db\PodcastChannel;
use OCA\Music\Db\PodcastEpisode;
use OCA\Music\Db\SortBy;
use OCA\Music\Db\Track;

use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileResponse;
use OCA\Music\Http\FileStreamResponse;
use OCA\Music\Http\XmlResponse;

use OCA\Music\Middleware\AmpacheException;

use OCA\Music\Utility\AmpacheImageService;
use OCA\Music\Utility\AppInfo;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\LastfmService;
use OCA\Music\Utility\LibrarySettings;
use OCA\Music\Utility\PodcastService;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Util;

class AmpacheController extends Controller {
	private $config;
	private $l10n;
	private $urlGenerator;
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $bookmarkBusinessLayer;
	private $genreBusinessLayer;
	private $playlistBusinessLayer;
	private $podcastChannelBusinessLayer;
	private $podcastEpisodeBusinessLayer;
	private $radioStationBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $podcastService;
	private $imageService;
	private $coverHelper;
	private $lastfmService;
	private $librarySettings;
	private $random;
	private $logger;

	private $jsonMode;
	private $session;
	private $namePrefixes;

	const ALL_TRACKS_PLAYLIST_ID = -1;
	const API4_VERSION = '440000';
	const API5_VERSION = '560000';
	const API6_VERSION = '600000';
	const API_MIN_COMPATIBLE_VERSION = '350001';

	public function __construct(string $appname,
								IRequest $request,
								IConfig $config,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								BookmarkBusinessLayer $bookmarkBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								PodcastChannelBusinessLayer $podcastChannelBusinessLayer,
								PodcastEpisodeBusinessLayer $podcastEpisodeBusinessLayer,
								RadioStationBusinessLayer $radioStationBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								PodcastService $podcastService,
								AmpacheImageService $imageService,
								CoverHelper $coverHelper,
								LastfmService $lastfmService,
								LibrarySettings $librarySettings,
								Random $random,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->config = $config;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->bookmarkBusinessLayer = $bookmarkBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->podcastChannelBusinessLayer = $podcastChannelBusinessLayer;
		$this->podcastEpisodeBusinessLayer = $podcastEpisodeBusinessLayer;
		$this->radioStationBusinessLayer = $radioStationBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->library = $library;
		$this->podcastService = $podcastService;
		$this->imageService = $imageService;
		$this->coverHelper = $coverHelper;
		$this->lastfmService = $lastfmService;
		$this->librarySettings = $librarySettings;
		$this->random = $random;
		$this->logger = $logger;
	}

	public function setJsonMode(bool $useJsonMode) : void {
		$this->jsonMode = $useJsonMode;
	}

	public function setSession(AmpacheSession $session) : void {
		$this->session = $session;
		$this->namePrefixes = $this->librarySettings->getIgnoredArticles($session->getUserId());
	}

	public function ampacheResponse(array $content) : Response {
		if ($this->jsonMode) {
			return new JSONResponse($this->prepareResultForJsonApi($content));
		} else {
			return new XmlResponse($this->prepareResultForXmlApi($content), ['id', 'index', 'count', 'code', 'errorCode'], true, true);
		}
	}

	public function ampacheErrorResponse(int $code, string $message) : Response {
		$this->logger->log($message, 'debug');

		if ($this->apiMajorVersion() > 4) {
			$code = $this->mapApiV4ErrorToV5($code);
			$content = [
				'error' => [
					'errorCode' => (string)$code,
					'errorAction' => $this->request->getParam('action'),
					'errorType' => 'system',
					'errorMessage' => $message
				]
			];
		} else {
			$content = [
				'error' => [
					'code' => (string)$code,
					'value' => $message
				]
			];
		}
		return $this->ampacheResponse($content);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function xmlApi(string $action) : Response {
		// differentation between xmlApi and jsonApi is made already by the middleware
		return $this->dispatch($action);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function jsonApi(string $action) : Response {
		// differentation between xmlApi and jsonApi is made already by the middleware
		return $this->dispatch($action);
	}

	protected function dispatch(string $action) : Response {
		$this->logger->log("Ampache action '$action' requested", 'debug');

		// Allow calling any functions annotated to be part of the API
		if (\method_exists($this, $action)) {
			$annotationReader = new MethodAnnotationReader($this, $action);
			if ($annotationReader->hasAnnotation('AmpacheAPI')) {
				// custom "filter" which modifies the value of the request argument `limit`
				$limitFilter = function(?string $value) : int {
					// Any non-integer values and integer value 0 are interpreted as "no limit".
					// On the other hand, the API spec mandates limiting responses to 5000 entries
					// even if no limit or larger limit has been passed.
					$value = (int)$value;
					if ($value <= 0) {
						$value = 5000;
					}
					return \min($value, 5000);
				};

				$parameterExtractor = new RequestParameterExtractor($this->request, ['limit' => $limitFilter]);
				try {
					$parameterValues = $parameterExtractor->getParametersForMethod($this, $action);
				} catch (RequestParameterExtractorException $ex) {
					throw new AmpacheException($ex->getMessage(), 400);
				}
				$response = \call_user_func_array([$this, $action], $parameterValues);
				// The API methods may return either a Response object or an array, which should be converted to Response
				if (!($response instanceof Response)) {
					$response = $this->ampacheResponse($response);
				}
				return $response;
			}
		}

		// No method was found for this action
		$this->logger->log("Unsupported Ampache action '$action' requested", 'warn');
		throw new AmpacheException('Action not supported', 405);
	}

	/***********************
	 * Ampahce API methods *
	 ***********************/

	/**
	 * Get the handshake result. The actual user authentication and session creation logic has happened prior to calling
	 * this in the class AmpacheMiddleware.
	 * 
	 * @AmpacheAPI
	 */
	 protected function handshake() : array {
		$user = $this->session->getUserId();
		$updateTime = \max($this->library->latestUpdateTime($user), $this->playlistBusinessLayer->latestUpdateTime($user));
		$addTime = \max($this->library->latestInsertTime($user), $this->playlistBusinessLayer->latestInsertTime($user));
		$genresKey = $this->genreKey() . 's';
		$playlistCount = $this->playlistBusinessLayer->count($user);
		
		return [
			'session_expire' => \date('c', $this->session->getExpiry()),
			'auth' => $this->session->getToken(),
			'api' => $this->apiVersionString(),
			'update' => $updateTime->format('c'),
			'add' => $addTime->format('c'),
			'clean' => \date('c', \time()), // TODO: actual time of the latest item removal
			'songs' => $this->trackBusinessLayer->count($user),
			'artists' => $this->artistBusinessLayer->count($user),
			'albums' => $this->albumBusinessLayer->count($user),
			'playlists' => $playlistCount,
			'searches' => 1, // "All tracks"
			'playlists_searches' => $playlistCount + 1,
			'podcasts' => $this->podcastChannelBusinessLayer->count($user),
			'podcast_episodes' => $this->podcastEpisodeBusinessLayer->count($user),
			'live_streams' => $this->radioStationBusinessLayer->count($user),
			$genresKey => $this->genreBusinessLayer->count($user),
			'videos' => 0,
			'catalogs' => 0,
			'shares' => 0,
			'licenses' => 0,
			'labels' => 0
		];
	}

	/**
	 * Get the result for the 'goodbye' command. The actual logout is handled by AmpacheMiddleware.
	 * 
	 * @AmpacheAPI
	 */
	protected function goodbye() : array {
		return ['success' => "goodbye: {$this->session->getToken()}"];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function ping() : array {
		$response = [
			'server' => $this->getAppNameAndVersion(),
			'version' => self::API6_VERSION,
			'compatible' => self::API_MIN_COMPATIBLE_VERSION
		];

		if ($this->session) {
			// in case ping is called within a valid session, the response will contain also the "handshake fields"
			$response += $this->handshake();
		}

		return $response;
	}

	/**
	 * @AmpacheAPI
	 */
	protected function get_indexes(string $type, ?string $filter, ?string $add, ?string $update, int $limit, int $offset=0) : array {
		if ($type === 'album_artist') {
			if (!empty($filter) || !empty($add) || !empty($update)) {
				throw new AmpacheException("Arguments 'filter', 'add', and 'update' are not supported for the type 'album_artist'", 400);
			}
			$entities = $this->artistBusinessLayer->findAllHavingAlbums($this->session->getUserId(), SortBy::Name, $limit, $offset);
			$type = 'artist';
		} else {
			$businessLayer = $this->getBusinessLayer($type);
			$entities = $this->findEntities($businessLayer, $filter, false, $limit, $offset, $add, $update);
		}
		return $this->renderEntitiesIndex($entities, $type);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function stats(string $type, ?string $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();

		// Support for API v3.x: Originally, there was no 'filter' argument and the 'type'
		// argument had that role. The action only supported albums in this old format.
		// The 'filter' argument was added and role of 'type' changed in API v4.0.
		if (empty($filter)) {
			$filter = $type;
			$type = 'album';
		}

		// Note: In addition to types specified in APIv6, we support also types 'genre' and 'live_stream'
		// as that's possible without extra effort. All types don't support all possible filters.
		$businessLayer = $this->getBusinessLayer($type);

		$getEntitiesIfSupported = function(
				BusinessLayer $businessLayer, string $method, string $userId,
				int $limit, int $offset) use ($type, $filter) {
			if (\method_exists($businessLayer, $method)) {
				return $businessLayer->$method($userId, $limit, $offset);
			} else {
				throw new AmpacheException("Filter $filter not supported for type $type", 400);
			}
		};

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
			case 'frequent':
				$entities = $getEntitiesIfSupported($businessLayer, 'findFrequentPlay', $userId, $limit, $offset);
				break;
			case 'recent':
				$entities = $getEntitiesIfSupported($businessLayer, 'findRecentPlay', $userId, $limit, $offset);
				break;
			case 'forgotten':
				$entities = $getEntitiesIfSupported($businessLayer, 'findNotRecentPlay', $userId, $limit, $offset);
				break;
			case 'highest': //TODO
			default:
				throw new AmpacheException("Unsupported filter $filter", 400);
		}

		return $this->renderEntities($entities, $type);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function artists(
			?string $filter, ?string $add, ?string $update,
			int $limit, int $offset=0, bool $exact=false) : array {

		$artists = $this->findEntities($this->artistBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		return $this->renderArtists($artists);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function artist(int $filter) : array {
		$userId = $this->session->getUserId();
		$artist = $this->artistBusinessLayer->find($filter, $userId);
		return $this->renderArtists([$artist]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function artist_albums(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$albums = $this->albumBusinessLayer->findAllByArtist($filter, $userId, $limit, $offset);
		return $this->renderAlbums($albums);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function artist_songs(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByArtist($filter, $userId, $limit, $offset);
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function album_songs(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByAlbum($filter, $userId, null, $limit, $offset);
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function song(int $filter) : array {
		$userId = $this->session->getUserId();
		$track = $this->trackBusinessLayer->find($filter, $userId);
		$trackInArray = [$track];
		return $this->renderSongs($trackInArray);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function songs(
			?string $filter, ?string $add, ?string $update,
			int $limit, int $offset=0, bool $exact=false) : array {

		$tracks = $this->findEntities($this->trackBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function search_songs(string $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByNameRecursive($filter, $userId, $limit, $offset);
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function albums(
			?string $filter, ?string $add, ?string $update,
			int $limit, int $offset=0, bool $exact=false) : array {

		$albums = $this->findEntities($this->albumBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);
		return $this->renderAlbums($albums);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function album(int $filter) : array {
		$userId = $this->session->getUserId();
		$album = $this->albumBusinessLayer->find($filter, $userId);
		return $this->renderAlbums([$album]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function get_similar(string $type, int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		if ($type == 'artist') {
			$entities = $this->lastfmService->getSimilarArtists($filter, $userId);
		} elseif ($type == 'song') {
			$entities = $this->lastfmService->getSimilarTracks($filter, $userId);
		} else {
			throw new AmpacheException("Type '$type' is not supported", 400);
		}
		$entities = \array_slice($entities, $offset, $limit);
		return $this->renderEntitiesIndex($entities, $type);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlists(
			?string $filter, ?string $add, ?string $update,
			int $limit, int $offset=0, bool $exact=false, int $hide_search=0) : array {

		$userId = $this->session->getUserId();
		$playlists = $this->findEntities($this->playlistBusinessLayer, $filter, $exact, $limit, $offset, $add, $update);

		// append "All tracks" if "seaches" are not forbidden, and not filtering by any criteria, and it is not off-limits
		$allTracksIndex = $this->playlistBusinessLayer->count($userId);
		if (!$hide_search && empty($filter) && empty($add) && empty($update)
				&& self::indexIsWithinOffsetAndLimit($allTracksIndex, $offset, $limit)) {
			$playlists[] = new AmpacheController_AllTracksPlaylist($userId, $this->trackBusinessLayer, $this->l10n);
		}

		return $this->renderPlaylists($playlists);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist(int $filter) : array {
		$userId = $this->session->getUserId();
		if ($filter == self::ALL_TRACKS_PLAYLIST_ID) {
			$playlist = new AmpacheController_AllTracksPlaylist($userId, $this->trackBusinessLayer, $this->l10n);
		} else {
			$playlist = $this->playlistBusinessLayer->find($filter, $userId);
		}
		return $this->renderPlaylists([$playlist]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist_songs(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		if ($filter == self::ALL_TRACKS_PLAYLIST_ID) {
			$tracks = $this->trackBusinessLayer->findAll($userId, SortBy::Parent, $limit, $offset);
			foreach ($tracks as $index => &$track) {
				$track->setNumberOnPlaylist($index + 1);
			}
		} else {
			$tracks = $this->playlistBusinessLayer->getPlaylistTracks($filter, $userId, $limit, $offset);
		}
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist_create(string $name) : array {
		$playlist = $this->playlistBusinessLayer->create($name, $this->session->getUserId());
		return $this->renderPlaylists([$playlist]);
	}

	/**
	 * @AmpacheAPI
	 *
	 * @param int $filter Playlist ID
	 * @param ?string $name New name for the playlist
	 * @param ?string $items Track IDs
	 * @param ?string $tracks 1-based indices of the tracks
	 */
	protected function playlist_edit(int $filter, ?string $name, ?string $items, ?string $tracks) : array {
		$edited = false;
		$userId = $this->session->getUserId();
		$playlist = $this->playlistBusinessLayer->find($filter, $userId);

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
			return ['success' => 'playlist changes saved'];
		} else {
			throw new AmpacheException('Nothing was changed', 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist_delete(int $filter) : array {
		$this->playlistBusinessLayer->delete($filter, $this->session->getUserId());
		return ['success' => 'playlist deleted'];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist_add_song(int $filter, int $song, bool $check=false) : array {
		$userId = $this->session->getUserId();
		if (!$this->trackBusinessLayer->exists($song, $userId)) {
			throw new AmpacheException("Invalid song ID $song", 404);
		}

		$playlist = $this->playlistBusinessLayer->find($filter, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();

		if ($check && \in_array($song, $trackIds)) {
			throw new AmpacheException("Can't add a duplicate item when check is enabled", 400);
		}

		$trackIds[] = $song;
		$playlist->setTrackIdsFromArray($trackIds);
		$this->playlistBusinessLayer->update($playlist);
		return ['success' => 'song added to playlist'];
	}

	/**
	 * @AmpacheAPI
	 *
	 * @param int $filter Playlist ID
	 * @param ?int $song Track ID
	 * @param ?int $track 1-based index of the track
	 * @param ?int $clear Value 1 erases all the songs from the playlist
	 */
	protected function playlist_remove_song(int $filter, ?int $song, ?int $track, ?int $clear) : array {
		$playlist = $this->playlistBusinessLayer->find($filter, $this->session->getUserId());

		if ($clear === 1) {
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
		return ['success' => $message];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function playlist_generate(
			?string $filter, ?int $album, ?int $artist, ?int $flag,
			int $limit, int $offset=0, string $mode='random', string $format='song') : array {

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

		if ($mode == 'random') {
			$userId = $this->session->getUserId();
			$indices = $this->random->getIndices(\count($tracks), $offset, $limit, $userId, 'ampache_playlist_generate');
			$tracks = Util::arrayMultiGet($tracks, $indices);
		} else { // 'recent', 'forgotten', 'unplayed'
			throw new AmpacheException("Mode '$mode' is not supported", 400);
		}

		switch ($format) {
			case 'song':
				return $this->renderSongs($tracks);
			case 'index':
				return $this->renderSongsIndex($tracks);
			case 'id':
				return $this->renderEntityIds($tracks);
			default:
				throw new AmpacheException("Format '$format' is not supported", 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcasts(?string $filter, ?string $include, int $limit, int $offset=0, bool $exact=false) : array {
		$channels = $this->findEntities($this->podcastChannelBusinessLayer, $filter, $exact, $limit, $offset);

		if ($include === 'episodes') {
			$userId = $this->session->getUserId();
			$actuallyLimited = ($limit < $this->podcastChannelBusinessLayer->count($userId));
			$allChannelsIncluded = (!$filter && !$actuallyLimited && !$offset);
			$this->podcastService->injectEpisodes($channels, $userId, $allChannelsIncluded);
		}

		return $this->renderPodcastChannels($channels);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcast(int $filter, ?string $include) : array {
		$userId = $this->session->getUserId();
		$channel = $this->podcastChannelBusinessLayer->find($filter, $userId);

		if ($include === 'episodes') {
			$channel->setEpisodes($this->podcastEpisodeBusinessLayer->findAllByChannel($filter, $userId));
		}

		return $this->renderPodcastChannels([$channel]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcast_create(string $url) : array {
		$userId = $this->session->getUserId();
		$result = $this->podcastService->subscribe($url, $userId);

		switch ($result['status']) {
			case PodcastService::STATUS_OK:
				return $this->renderPodcastChannels([$result['channel']]);
			case PodcastService::STATUS_INVALID_URL:
				throw new AmpacheException("Invalid URL $url", 400);
			case PodcastService::STATUS_INVALID_RSS:
				throw new AmpacheException("The document at URL $url is not a valid podcast RSS feed", 400);
			case PodcastService::STATUS_ALREADY_EXISTS:
				throw new AmpacheException('User already has this podcast channel subscribed', 400);
			default:
				throw new AmpacheException("Unexpected status code {$result['status']}", 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcast_delete(int $filter) : array {
		$userId = $this->session->getUserId();
		$status = $this->podcastService->unsubscribe($filter, $userId);

		switch ($status) {
			case PodcastService::STATUS_OK:
				return ['success' => 'podcast deleted'];
			case PodcastService::STATUS_NOT_FOUND:
				throw new AmpacheException('Channel to be deleted not found', 404);
			default:
				throw new AmpacheException("Unexpected status code $status", 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcast_episodes(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$episodes = $this->podcastEpisodeBusinessLayer->findAllByChannel($filter, $userId, $limit, $offset);
		return $this->renderPodcastEpisodes($episodes);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function podcast_episode(int $filter) : array {
		$userId = $this->session->getUserId();
		$episode = $this->podcastEpisodeBusinessLayer->find($filter, $userId);
		return $this->renderPodcastEpisodes([$episode]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function update_podcast(int $id) : array {
		$userId = $this->session->getUserId();
		$result = $this->podcastService->updateChannel($id, $userId);

		switch ($result['status']) {
			case PodcastService::STATUS_OK:
				$message = $result['updated'] ? 'channel was updated from the source' : 'no changes found';
				return ['success' => $message];
			case PodcastService::STATUS_NOT_FOUND:
				throw new AmpacheException('Channel to be updated not found', 404);
			case PodcastService::STATUS_INVALID_URL:
				throw new AmpacheException('failed to read from the channel URL', 400);
			case PodcastService::STATUS_INVALID_RSS:
				throw new AmpacheException('the document at the channel URL is not a valid podcast RSS feed', 400);
			default:
				throw new AmpacheException("Unexpected status code {$result['status']}", 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function live_streams(?string $filter, int $limit, int $offset=0, bool $exact=false) : array {
		$stations = $this->findEntities($this->radioStationBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderLiveStreams($stations);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function live_stream(int $filter) : array {
		$station = $this->radioStationBusinessLayer->find($filter, $this->session->getUserId());
		return $this->renderLiveStreams([$station]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function live_stream_create(string $name, string $url, ?string $site_url) : array {
		$station = $this->radioStationBusinessLayer->create($this->session->getUserId(), $name, $url, $site_url);
		return $this->renderLiveStreams([$station]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function live_stream_delete(int $filter) : array {
		$this->radioStationBusinessLayer->delete($filter, $this->session->getUserId());
		return ['success' => "Deleted live stream: $filter"];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function live_stream_edit(int $filter, ?string $name, ?string $url, ?string $site_url) : array {
		$station = $this->radioStationBusinessLayer->find($filter, $this->session->getUserId());

		if ($name !== null) {
			$station->setName($name);
		}
		if ($url !== null) {
			$station->setStreamUrl($url);
		}
		if ($site_url !== null) {
			$station->setHomeUrl($site_url);
		}
		$station = $this->radioStationBusinessLayer->update($station);

		return $this->renderLiveStreams([$station]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function tags(?string $filter, int $limit, int $offset=0, bool $exact=false) : array {
		$genres = $this->findEntities($this->genreBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderTags($genres);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function tag(int $filter) : array {
		$userId = $this->session->getUserId();
		$genre = $this->genreBusinessLayer->find($filter, $userId);
		return $this->renderTags([$genre]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function tag_artists(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$artists = $this->artistBusinessLayer->findAllByGenre($filter, $userId, $limit, $offset);
		return $this->renderArtists($artists);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function tag_albums(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$albums = $this->albumBusinessLayer->findAllByGenre($filter, $userId, $limit, $offset);
		return $this->renderAlbums($albums);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function tag_songs(int $filter, int $limit, int $offset=0) : array {
		$userId = $this->session->getUserId();
		$tracks = $this->trackBusinessLayer->findAllByGenre($filter, $userId, $limit, $offset);
		return $this->renderSongs($tracks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function genres(?string $filter, int $limit, int $offset=0, bool $exact=false) : array {
		$genres = $this->findEntities($this->genreBusinessLayer, $filter, $exact, $limit, $offset);
		return $this->renderGenres($genres);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function genre(int $filter) : array {
		$userId = $this->session->getUserId();
		$genre = $this->genreBusinessLayer->find($filter, $userId);
		return $this->renderGenres([$genre]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function genre_artists(?int $filter, int $limit, int $offset=0) : array {
		if ($filter === null) {
			return $this->artists(null, null, null, $limit, $offset);
		} else {
			return $this->tag_artists($filter, $limit, $offset);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function genre_albums(?int $filter, int $limit, int $offset=0) : array {
		if ($filter === null) {
			return $this->albums(null, null, null, $limit, $offset);
		} else {
			return $this->tag_albums($filter, $limit, $offset);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function genre_songs(?int $filter, int $limit, int $offset=0) : array {
		if ($filter === null) {
			return $this->songs(null, null, null, $limit, $offset);
		} else {
			return $this->tag_songs($filter, $limit, $offset);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function bookmarks() : array {
		$bookmarks = $this->bookmarkBusinessLayer->findAll($this->session->getUserId());
		return $this->renderBookmarks($bookmarks);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function get_bookmark(int $filter, string $type) : array {
		$entryType = self::mapBookmarkType($type);
		$bookmark = $this->bookmarkBusinessLayer->findByEntry($entryType, $filter, $this->session->getUserId());
		return $this->renderBookmarks([$bookmark]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function bookmark_create(int $filter, string $type, int $position, string $client='AmpacheAPI') : array {
		// Note: the optional argument 'date' is not supported and is disregarded
		$entryType = self::mapBookmarkType($type);
		$position *= 1000; // seconds to milliseconds
		$bookmark = $this->bookmarkBusinessLayer->addOrUpdate($this->session->getUserId(), $entryType, $filter, $position, $client);
		return $this->renderBookmarks([$bookmark]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function bookmark_edit(int $filter, string $type, int $position, ?string $client) : array {
		// Note: the optional argument 'date' is not supported and is disregarded
		$entryType = self::mapBookmarkType($type);
		$bookmark = $this->bookmarkBusinessLayer->findByEntry($entryType, $filter, $this->session->getUserId());
		$bookmark->setPosition($position * 1000); // seconds to milliseconds
		if ($client !== null) {
			$bookmark->setComment($client);
		}
		$bookmark = $this->bookmarkBusinessLayer->update($bookmark);
		return $this->renderBookmarks([$bookmark]);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function bookmark_delete(int $filter, string $type) : array {
		$entryType = self::mapBookmarkType($type);
		$bookmark = $this->bookmarkBusinessLayer->findByEntry($entryType, $filter, $this->session->getUserId());
		$this->bookmarkBusinessLayer->delete($bookmark->getId(), $bookmark->getUserId());
		return ['success' => "Deleted Bookmark: $type $filter"];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function flag(string $type, int $id, bool $flag) : array {
		if (!\in_array($type, ['song', 'album', 'artist', 'podcast', 'podcast_episode'])) {
			throw new AmpacheException("Unsupported type $type", 400);
		}

		$userId = $this->session->getUserId();
		$businessLayer = $this->getBusinessLayer($type);
		if ($flag) {
			$modifiedCount = $businessLayer->setStarred([$id], $userId);
			$message = "flag ADDED to $type $id";
		} else {
			$modifiedCount = $businessLayer->unsetStarred([$id], $userId);
			$message = "flag REMOVED from $type $id";
		}

		if ($modifiedCount > 0) {
			return ['success' => $message];
		} else {
			throw new AmpacheException("The $type $id was not found", 404);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function record_play(int $id, ?int $date) : array {
		$timeOfPlay = ($date === null) ? null : new \DateTime('@' . $date);
		$this->trackBusinessLayer->recordTrackPlayed($id, $this->session->getUserId(), $timeOfPlay);
		return ['success' => 'play recorded'];
	}

	/**
	 * @AmpacheAPI
	 */
	protected function download(int $id, string $type='song') : Response {
		// request param `format` is ignored
		$userId = $this->session->getUserId();

		if ($type === 'song') {
			try {
				$track = $this->trackBusinessLayer->find($id, $userId);
			} catch (BusinessLayerException $e) {
				return new ErrorResponse(Http::STATUS_NOT_FOUND, $e->getMessage());
			}

			$file = $this->librarySettings->getFolder($userId)->getById($track->getFileId())[0] ?? null;

			if ($file instanceof \OCP\Files\File) {
				return new FileStreamResponse($file);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			}
		} elseif ($type === 'podcast' || $type === 'podcast_episode') { // there's a difference between APIv4 and APIv5
			$episode = $this->podcastEpisodeBusinessLayer->find($id, $userId);
			return new RedirectResponse($episode->getStreamUrl());
		} elseif ($type === 'playlist') {
			$songIds = ($id === self::ALL_TRACKS_PLAYLIST_ID)
				? $this->trackBusinessLayer->findAllIds($userId)
				: $this->playlistBusinessLayer->find($id, $userId)->getTrackIdsAsArray();
			if (empty($songIds)) {
				throw new AmpacheException("The playlist $id is empty", 404);
			} else {
				return $this->download(Random::pickItem($songIds));
			}
		} else {
			throw new AmpacheException("Unsupported type '$type'", 400);
		}
	}

	/**
	 * @AmpacheAPI
	 */
	protected function stream(int $id, ?int $offset, string $type='song') : Response {
		// request params `bitrate`, `format`, and `length` are ignored

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

		return $this->download($id, $type);
	}

	/**
	 * @AmpacheAPI
	 */
	protected function get_art(string $type, int $id) : Response {
		if (!\in_array($type, ['song', 'album', 'artist', 'podcast', 'playlist'])) {
			throw new AmpacheException("Unsupported type $type", 400);
		}

		if ($type === 'song') {
			// map song to its parent album
			$id = $this->trackBusinessLayer->find($id, $this->session->getUserId())->getAlbumId();
			$type = 'album';
		}

		return $this->getCover($id, $this->getBusinessLayer($type));
	}

	/********************
	 * Helper functions *
	 ********************/

	private function getBusinessLayer(string $type) : BusinessLayer {
		switch ($type) {
			case 'song':			return $this->trackBusinessLayer;
			case 'album':			return $this->albumBusinessLayer;
			case 'artist':			return $this->artistBusinessLayer;
			case 'playlist':		return $this->playlistBusinessLayer;
			case 'podcast':			return $this->podcastChannelBusinessLayer;
			case 'podcast_episode':	return $this->podcastEpisodeBusinessLayer;
			case 'live_stream':		return $this->radioStationBusinessLayer;
			case 'tag':				return $this->genreBusinessLayer;
			case 'genre':			return $this->genreBusinessLayer;
			default:				throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private function renderEntities(array $entities, string $type) : array {
		switch ($type) {
			case 'song':			return $this->renderSongs($entities);
			case 'album':			return $this->renderAlbums($entities);
			case 'artist':			return $this->renderArtists($entities);
			case 'playlist':		return $this->renderPlaylists($entities);
			case 'podcast':			return $this->renderPodcastChannels($entities);
			case 'podcast_episode':	return $this->renderPodcastEpisodes($entities);
			case 'live_stream':		return $this->renderLiveStreams($entities);
			case 'tag':				return $this->renderTags($entities);
			case 'genre':			return $this->renderTags($entities);
			default:				throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private function renderEntitiesIndex($entities, $type) : array {
		switch ($type) {
			case 'song':			return $this->renderSongsIndex($entities);
			case 'album':			return $this->renderAlbumsIndex($entities);
			case 'artist':			return $this->renderArtistsIndex($entities);
			case 'playlist':		return $this->renderPlaylistsIndex($entities);
			case 'podcast':			return $this->renderPodcastChannelsIndex($entities);
			case 'podcast_episode':	return $this->renderPodcastEpisodesIndex($entities);
			case 'live_stream':		return $this->renderLiveStreamsIndex($entities);
			default:				throw new AmpacheException("Unsupported type $type", 400);
		}
	}

	private static function mapBookmarkType(string $ampacheType) : int {
		switch ($ampacheType) {
			case 'song':			return Bookmark::TYPE_TRACK;
			case 'podcast_episode':	return Bookmark::TYPE_PODCAST_EPISODE;
			default:				throw new AmpacheException("Unsupported type $ampacheType", 400);
		}
	}

	private function getAppNameAndVersion() : string {
		$vendor = 'owncloud/nextcloud'; // this should get overridden by the next 'include'
		include \OC::$SERVERROOT . '/version.php';

		$appVersion = AppInfo::getVersion();

		return "$vendor {$this->appName} $appVersion";
	}

	private function getCover(int $entityId, BusinessLayer $businessLayer) : Response {
		$userId = $this->session->getUserId();
		$userFolder = $this->librarySettings->getFolder($userId);

		try {
			$entity = $businessLayer->find($entityId, $userId);
			$coverData = $this->coverHelper->getCover($entity, $userId, $userFolder);
			if ($coverData !== null) {
				return new FileResponse($coverData);
			}
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'entity not found');
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'entity has no cover');
	}

	private function findEntities(
			BusinessLayer $businessLayer, ?string $filter, bool $exact, ?int $limit=null, ?int $offset=null, ?string $add=null, ?string $update=null) : array {

		$userId = $this->session->getUserId();

		// It's not documented, but Ampache supports also specifying date range on `add` and `update` parameters
		// by using '/' as separator. If there is no such separator, then the value is used as a lower limit.
		$add = Util::explode('/', $add);
		$update = Util::explode('/', $update);
		$addMin = $add[0] ?? null;
		$addMax = $add[1] ?? null;
		$updateMin = $update[0] ?? null;
		$updateMax = $update[1] ?? null;

		if ($filter) {
			$matchMode = $exact ? MatchMode::Exact : MatchMode::Substring;
			return $businessLayer->findAllByName($filter, $userId, $matchMode, $limit, $offset, $addMin, $addMax, $updateMin, $updateMax);
		} else {
			return $businessLayer->findAll($userId, SortBy::Name, $limit, $offset, $addMin, $addMax, $updateMin, $updateMax);
		}
	}

	private function createAmpacheActionUrl(string $action, int $id, ?string $type=null) : string {
		$api = $this->jsonMode ? 'music.ampache.jsonApi' : 'music.ampache.xmlApi';
		$auth = $this->session->getToken();
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute($api))
				. "?action=$action&id=$id&auth=$auth"
				. (!empty($type) ? "&type=$type" : '');
	}

	private function createCoverUrl(Entity $entity) : string {
		if ($entity instanceof Album) {
			$type = 'album';
		} elseif ($entity instanceof Artist) {
			$type = 'artist';
		} elseif ($entity instanceof Playlist) {
			$type = 'playlist';
		} else {
			throw new AmpacheException('unexpeted entity type for cover image', 500);
		}

		if ($type === 'playlist' || $entity->getCoverFileId()) {
			$id = $entity->getId();
			$token = $this->imageService->getToken($type, $id, $this->session->getAmpacheUserId());
			return $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->linkToRoute('music.ampacheImage.image') . "?object_type=$type&object_id=$id&token=$token"
			);
		} else {
			return '';
		}
	}

	private static function indexIsWithinOffsetAndLimit(int $index, ?int $offset, ?int $limit) : bool {
		$offset = \intval($offset); // missing offset is interpreted as 0-offset
		return ($limit === null) || ($index >= $offset && $index < $offset + $limit);
	}

	private function prefixAndBaseName(?string $name) : array {
		$parts = ['prefix' => null, 'basename' => $name];

		if ($name !== null) {
			foreach ($this->namePrefixes as $prefix) {
				if (Util::startsWith($name, $prefix . ' ', /*ignoreCase=*/true)) {
					$parts['prefix'] = $prefix;
					$parts['basename'] = \substr($name, \strlen($prefix) + 1);
					break;
				}
			}
		}

		return $parts;
	}

	/**
	 * @param Artist[] $artists
	 */
	private function renderArtists(array $artists) : array {
		$userId = $this->session->getUserId();
		$genreMap = Util::createIdLookupTable($this->genreBusinessLayer->findAll($userId));
		$genreKey = $this->genreKey();

		return [
			'artist' => \array_map(function (Artist $artist) use ($userId, $genreMap, $genreKey) {
				$albumCount = $this->albumBusinessLayer->countByArtist($artist->getId());
				$songCount = $this->trackBusinessLayer->countByArtist($artist->getId());
				$name = $artist->getNameString($this->l10n);
				$nameParts = $this->prefixAndBaseName($name);
				return [
					'id' => (string)$artist->getId(),
					'name' => $name,
					'prefix' => $nameParts['prefix'],
					'basename' => $nameParts['basename'],
					'albums' => $albumCount, // TODO: this should contain objects if requested; in API5+, this never contains the count
					'albumcount' => $albumCount,
					'songs' => $songCount, // TODO: this should contain objects if requested; in API5+, this never contains the count
					'songcount' => $songCount,
					'time' => $this->trackBusinessLayer->totalDurationByArtist($artist->getId()),
					'art' => $this->createCoverUrl($artist),
					'rating' => 0,
					'preciserating' => 0,
					'flag' => !empty($artist->getStarred()),
					$genreKey => \array_map(function ($genreId) use ($genreMap) {
						return [
							'id' => (string)$genreId,
							'value' => $genreMap[$genreId]->getNameString($this->l10n),
							'count' => 1
						];
					}, $this->trackBusinessLayer->getGenresByArtistId($artist->getId(), $userId))
				];
			}, $artists)
		];
	}

	/**
	 * @param Album[] $albums
	 */
	private function renderAlbums(array $albums) : array {
		$genreKey = $this->genreKey();
		// In APIv6 JSON format, there is a new property `artists` with an array value
		$includeArtists = ($this->jsonMode && $this->apiMajorVersion() > 5);

		return [
			'album' => \array_map(function (Album $album) use ($genreKey, $includeArtists) {
				$songCount = $this->trackBusinessLayer->countByAlbum($album->getId());
				$name = $album->getNameString($this->l10n);
				$nameParts = $this->prefixAndBaseName($name);
				$apiAlbum = [
					'id' => (string)$album->getId(),
					'name' => $name,
					'prefix' => $nameParts['prefix'],
					'basename' => $nameParts['basename'],
					'artist' => [
						'id' => (string)$album->getAlbumArtistId(),
						'value' => $album->getAlbumArtistNameString($this->l10n)
					],
					'tracks' => $songCount, // TODO: this should contain objects if requested; in API5+, this never contains the count
					'songcount' => $songCount,
					'time' => $this->trackBusinessLayer->totalDurationOfAlbum($album->getId()),
					'rating' => 0,
					'year' => $album->yearToAPI(),
					'art' => $this->createCoverUrl($album),
					'preciserating' => 0,
					'flag' => !empty($album->getStarred()),
					$genreKey => \array_map(function ($genre) {
						return [
							'id' => (string)$genre->getId(),
							'value' => $genre->getNameString($this->l10n),
							'count' => 1
						];
					}, $album->getGenres() ?? [])
				];
				if ($includeArtists) {
					$apiAlbum['artists'] = [$apiAlbum['artist']];
				}

				return $apiAlbum;
			}, $albums)
		];
	}

	/**
	 * @param Track[] $tracks
	 */
	private function renderSongs(array $tracks) : array {
		$userId = $this->session->getUserId();
		$this->albumBusinessLayer->injectAlbumsToTracks($tracks, $userId);

		$createPlayUrl = function(Track $track) : string {
			return $this->createAmpacheActionUrl('download', $track->getId());
		};
		$createImageUrl = function(Track $track) : string {
			return $this->createCoverUrl($track->getAlbum());
		};
		$genreKey = $this->genreKey();
		// In APIv6 JSON format, there is a new property `artists` with an array value
		$includeArtists = ($this->jsonMode && $this->apiMajorVersion() > 5);

		return [
			'song' => Util::arrayMapMethod($tracks, 'toAmpacheApi', [$this->l10n, $createPlayUrl, $createImageUrl, $genreKey, $includeArtists])
		];
	}

	/**
	 * @param Playlist[] $playlists
	 */
	private function renderPlaylists(array $playlists) : array {
		$createImageUrl = function(Playlist $playlist) : string {
			if ($playlist->getId() === self::ALL_TRACKS_PLAYLIST_ID) {
				return '';
			} else {
				return $this->createCoverUrl($playlist);
			}
		};

		return [
			'playlist' => Util::arrayMapMethod($playlists, 'toAmpacheApi', [$createImageUrl])
		];
	}

	/**
	 * @param PodcastChannel[] $channels
	 */
	private function renderPodcastChannels(array $channels) : array {
		return [
			'podcast' => Util::arrayMapMethod($channels, 'toAmpacheApi')
		];
	}

	/**
	 * @param PodcastEpisode[] $episodes
	 */
	private function renderPodcastEpisodes(array $episodes) : array {
		return [
			'podcast_episode' => Util::arrayMapMethod($episodes, 'toAmpacheApi')
		];
	}

	/**
	 * @param RadioStation[] $stations
	 */
	private function renderLiveStreams(array $stations) : array {
		return [
			'live_stream' => Util::arrayMapMethod($stations, 'toAmpacheApi')
		];
	}

	/**
	 * @param Genre[] $genres
	 */
	private function renderTags(array $genres) : array {
		return [
			'tag' => Util::arrayMapMethod($genres, 'toAmpacheApi', [$this->l10n])
		];
	}

	/**
	 * @param Genre[] $genres
	 */
	private function renderGenres(array $genres) : array {
		return [
			'genre' => Util::arrayMapMethod($genres, 'toAmpacheApi', [$this->l10n])
		];
	}

	/**
	 * @param Bookmark[] $bookmarks
	 */
	private function renderBookmarks(array $bookmarks) : array {
		return [
			'bookmark' => Util::arrayMapMethod($bookmarks, 'toAmpacheApi')
		];
	}

	/**
	 * @param Track[] $tracks
	 */
	private function renderSongsIndex(array $tracks) : array {
		return [
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
		];
	}

	/**
	 * @param Album[] $albums
	 */
	private function renderAlbumsIndex(array $albums) : array {
		return [
			'album' => \array_map(function ($album) {
				$name = $album->getNameString($this->l10n);
				$nameParts = $this->prefixAndBaseName($name);

				return [
					'id' => (string)$album->getId(),
					'name' => $name,
					'prefix' => $nameParts['prefix'],
					'basename' => $nameParts['basename'],
					'artist' => [
						'id' => (string)$album->getAlbumArtistId(),
						'value' => $album->getAlbumArtistNameString($this->l10n)
					]
				];
			}, $albums)
		];
	}

	/**
	 * @param Artist[] $artists
	 */
	private function renderArtistsIndex(array $artists) : array {
		return [
			'artist' => \array_map(function ($artist) {
				$userId = $this->session->getUserId();
				$albums = $this->albumBusinessLayer->findAllByArtist($artist->getId(), $userId);
				$name = $artist->getNameString($this->l10n);
				$nameParts = $this->prefixAndBaseName($name);

				return [
					'id' => (string)$artist->getId(),
					'name' => $name,
					'prefix' => $nameParts['prefix'],
					'basename' => $nameParts['basename'],
					'album' => \array_map(function ($album) {
						return [
							'id' => (string)$album->getId(),
							'value' => $album->getNameString($this->l10n)
						];
					}, $albums)
				];
			}, $artists)
		];
	}

	/**
	 * @param Playlist[] $playlists
	 */
	private function renderPlaylistsIndex(array $playlists) : array {
		return [
			'playlist' => \array_map(function ($playlist) {
				return [
					'id' => (string)$playlist->getId(),
					'name' => $playlist->getName(),
					'playlisttrack' => $playlist->getTrackIdsAsArray()
				];
			}, $playlists)
		];
	}

	/**
	 * @param PodcastChannel[] $channels
	 */
	private function renderPodcastChannelsIndex(array $channels) : array {
		// The v4 API spec does not give any examples of this, and the v5 example is almost identical to the v4 "normal" result
		return $this->renderPodcastChannels($channels);
	}

	/**
	 * @param PodcastEpisode[] $episodes
	 */
	private function renderPodcastEpisodesIndex(array $episodes) : array {
		// The v4 API spec does not give any examples of this, and the v5 example is almost identical to the v4 "normal" result
		return $this->renderPodcastEpisodes($episodes);
	}

	/**
	 * @param RadioStation[] $stations
	 */
	private function renderLiveStreamsIndex(array $stations) : array {
		// The API spec gives no examples of this, but testing with Ampache demo server revealed that the format is indentical to the "full" format
		return $this->renderLiveStreams($stations);
	}

	/**
	 * @param Entity[] $entities
	 */
	private function renderEntityIds(array $entities) : array {
		return ['id' => Util::extractIds($entities)];
	}

	/**
	 * Array is considered to be "indexed" if its first element has numerical key.
	 * Empty array is considered to be "indexed".
	 */
	private static function arrayIsIndexed(array $array) : bool {
		\reset($array);
		return empty($array) || \is_int(\key($array));
	}

	/**
	 * The JSON API has some asymmetries with the XML API. This function makes the needed
	 * translations for the result content before it is converted into JSON.
	 */
	private function prepareResultForJsonApi(array $content) : array {
		$apiVer = $this->apiMajorVersion();

		// Special handling is needed for responses returning an array of library entities,
		// depending on the API version. In these cases, the outermost array is of associative
		// type with a single value which is a non-associative array.
		if (\count($content) === 1 && !self::arrayIsIndexed($content)
				&& \is_array(\current($content)) && self::arrayIsIndexed(\current($content))) {
			// In API versions < 5, the root node is an anonymous array. Unwrap the outermost array.
			if ($apiVer < 5) {
				$content = \array_pop($content);
			}
			// In later versions, the root object has a named array for plural actions (like "songs", "artists").
			// For singular actions (like "song", "artist"), the root object contains directly the entity properties.
			else {
				$action = $this->request->getParam('action');
				$plural = (\substr($action, -1) === 's' || $action === 'get_similar' || $action === 'advanced_search');

				// In APIv5, the action "album" is an excption, it is formatted as if it was a plural action.
				// This outlier has been fixed in APIv6.
				$api5albumOddity = ($apiVer === 5 && $action === 'album');

				if (!($plural  || $api5albumOddity)) {
					$content = \array_pop(\array_pop($content));
				}
			}
		}

		// In API versions < 6, all boolean valued properties should be converted to 0/1.
		if ($apiVer < 6) {
			Util::intCastArrayValues($content, 'is_bool');
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
	 */
	private function prepareResultForXmlApi(array $content) : array {
		\reset($content);
		$firstKey = \key($content);

		// all 'entity list' kind of responses shall have the (deprecated) total_count element
		if ($firstKey == 'song' || $firstKey == 'album' || $firstKey == 'artist' || $firstKey == 'playlist'
				|| $firstKey == 'tag' || $firstKey == 'genre' || $firstKey == 'podcast' || $firstKey == 'podcast_episode'
				|| $firstKey == 'live_stream') {
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

	private function genreKey() : string {
		return ($this->apiMajorVersion() > 4) ? 'genre' : 'tag';
	}

	private function apiMajorVersion() : int {
		// During the handshake, we don't yet have a session but the requeted version may be in the request args
		$verString = ($this->session !== null) 
			? $this->session->getApiVersion()
			: $this->request->getParam('version');
		
		if (\is_string($verString) && \strlen($verString)) {
			$ver = (int)$verString[0];
		} else {
			// Default version is 6 unless otherwise defined in config.php
			$ver = (int)$this->config->getSystemValue('music.ampache_api_default_ver', 6);
		}

		// For now, we have three supported major versions. Major version 3 can be sufficiently supported
		// with our "version 4" implementation.
		return Util::limit($ver, 4, 6);
	}

	private function apiVersionString() : string {
		switch ($this->apiMajorVersion()) {
			case 4:		return self::API4_VERSION;
			case 5:		return self::API5_VERSION;
			case 6:		return self::API6_VERSION;
			default:	throw new AmpacheException('Unexpected api major version', 500);
		}
	}

	private function mapApiV4ErrorToV5(int $code) : int {
		switch ($code) {
			case 400:	return 4710;	// bad request
			case 401:	return 4701;	// invalid handshake
			case 403:	return 4703;	// access denied
			case 404:	return 4704;	// not found
			case 405:	return 4705;	// missing
			case 412:	return 4742;	// failed access check
			case 501:	return 4700;	// access control not enabled
			default:	return 5000;	// unexcpected (not part of the API spec)
		}
	}
}

/**
 * Adapter class which acts like the Playlist class for the purpose of
 * AmpacheController::renderPlaylists but contains all the track of the user.
 */
class AmpacheController_AllTracksPlaylist extends Playlist {
	private $trackBusinessLayer;
	private $l10n;

	public function __construct(string $userId, TrackBusinessLayer $trackBusinessLayer, IL10N $l10n) {
		$this->userId = $userId;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->l10n = $l10n;
	}

	public function getId() : int {
		return AmpacheController::ALL_TRACKS_PLAYLIST_ID;
	}

	public function getName() : string {
		return $this->l10n->t('All tracks');
	}

	public function getTrackCount() : int {
		return $this->trackBusinessLayer->count($this->userId);
	}
}
