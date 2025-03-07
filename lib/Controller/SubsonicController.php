<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2025
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\AppFramework\Utility\MethodAnnotationReader;
use OCA\Music\AppFramework\Utility\RequestParameterExtractor;
use OCA\Music\AppFramework\Utility\RequestParameterExtractorException;

use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\BookmarkBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;

use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Db\Bookmark;
use OCA\Music\Db\Genre;
use OCA\Music\Db\MatchMode;
use OCA\Music\Db\PodcastEpisode;
use OCA\Music\Db\SortBy;
use OCA\Music\Db\Track;

use OCA\Music\Http\FileResponse;
use OCA\Music\Http\FileStreamResponse;
use OCA\Music\Http\XmlResponse;

use OCA\Music\Middleware\SubsonicException;

use OCA\Music\Utility\AmpacheImageService;
use OCA\Music\Utility\AppInfo;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\DetailsHelper;
use OCA\Music\Utility\HttpUtil;
use OCA\Music\Utility\LastfmService;
use OCA\Music\Utility\LibrarySettings;
use OCA\Music\Utility\PodcastService;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Util;

class SubsonicController extends Controller {
	const API_VERSION = '1.16.1';
	const FOLDER_ID_ARTISTS = -1;
	const FOLDER_ID_FOLDERS = -2;

	private AlbumBusinessLayer $albumBusinessLayer;
	private ArtistBusinessLayer $artistBusinessLayer;
	private BookmarkBusinessLayer $bookmarkBusinessLayer;
	private GenreBusinessLayer $genreBusinessLayer;
	private PlaylistBusinessLayer $playlistBusinessLayer;
	private PodcastChannelBusinessLayer $podcastChannelBusinessLayer;
	private PodcastEpisodeBusinessLayer $podcastEpisodeBusinessLayer;
	private RadioStationBusinessLayer $radioStationBusinessLayer;
	private TrackBusinessLayer $trackBusinessLayer;
	private IURLGenerator $urlGenerator;
	private IUserManager $userManager;
	private LibrarySettings $librarySettings;
	private IL10N $l10n;
	private CoverHelper $coverHelper;
	private DetailsHelper $detailsHelper;
	private LastfmService $lastfmService;
	private PodcastService $podcastService;
	private AmpacheImageService $imageService;
	private Random $random;
	private Logger $logger;
	private ?string $userId;
	private ?int $keyId;
	private array $ignoredArticles;
	private string $format;
	private ?string $callback;

	public function __construct(string $appname,
								IRequest $request,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								BookmarkBusinessLayer $bookmarkBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								PodcastChannelBusinessLayer $podcastChannelBusinessLayer,
								PodcastEpisodeBusinessLayer $podcastEpisodeBusinessLayer,
								RadioStationBusinessLayer $radioStationBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								LibrarySettings $librarySettings,
								CoverHelper $coverHelper,
								DetailsHelper $detailsHelper,
								LastfmService $lastfmService,
								PodcastService $podcastService,
								AmpacheImageService $imageService,
								Random $random,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->bookmarkBusinessLayer = $bookmarkBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->podcastChannelBusinessLayer = $podcastChannelBusinessLayer;
		$this->podcastEpisodeBusinessLayer = $podcastEpisodeBusinessLayer;
		$this->radioStationBusinessLayer = $radioStationBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->l10n = $l10n;
		$this->librarySettings = $librarySettings;
		$this->coverHelper = $coverHelper;
		$this->detailsHelper = $detailsHelper;
		$this->lastfmService = $lastfmService;
		$this->podcastService = $podcastService;
		$this->imageService = $imageService;
		$this->random = $random;
		$this->logger = $logger;
		$this->userId = null;
		$this->keyId = null;
		$this->ignoredArticles = [];
		$this->format = 'xml'; // default, should be immediately overridden by SubsonicMiddleware
	}

	/**
	 * Called by the middleware to set the response format to be used
	 * @param string $format Response format: xml/json/jsonp
	 * @param string|null $callback Function name to use if the @a $format is 'jsonp'
	 */
	public function setResponseFormat(string $format, string $callback = null) {
		$this->format = $format;
		$this->callback = $callback;
	}

	/**
	 * Called by the middleware once the user credentials have been checked
	 */
	public function setAuthenticatedUser(string $userId, int $keyId) {
		$this->userId = $userId;
		$this->keyId = $keyId;
		$this->ignoredArticles = $this->librarySettings->getIgnoredArticles($userId);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function handleRequest($method) {
		$this->logger->log("Subsonic request $method", 'debug');

		// Allow calling all methods with or without the postfix ".view"
		if (Util::endsWith($method, ".view")) {
			$method = \substr($method, 0, -\strlen(".view"));
		}

		// There's only one method allowed without a logged-in user
		if ($method !== 'getOpenSubsonicExtensions' && $this->userId === null) {
			throw new SubsonicException('User authentication required', 10);
		}

		// Allow calling any functions annotated to be part of the API
		if (\method_exists($this, $method)) {
			$annotationReader = new MethodAnnotationReader($this, $method);
			if ($annotationReader->hasAnnotation('SubsonicAPI')) {
				$parameterExtractor = new RequestParameterExtractor($this->request);
				try {
					$parameterValues = $parameterExtractor->getParametersForMethod($this, $method);
				} catch (RequestParameterExtractorException $ex) {
					return $this->subsonicErrorResponse(10, $ex->getMessage());
				}
				return \call_user_func_array([$this, $method], $parameterValues);
			}
		}

		$this->logger->log("Request $method not supported", 'warn');
		return $this->subsonicErrorResponse(70, "Requested action $method is not supported");
	}

	/* -------------------------------------------------------------------------
	 * REST API methods
	 * -------------------------------------------------------------------------
	 */

	/**
	 * @SubsonicAPI
	 */
	protected function ping() {
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getLicense() {
		return $this->subsonicResponse([
			'license' => [
				'valid' => true
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getMusicFolders() {
		// Only single root folder is supported
		return $this->subsonicResponse([
			'musicFolders' => ['musicFolder' => [
				['id' => self::FOLDER_ID_ARTISTS, 'name' => $this->l10n->t('Artists')],
				['id' => self::FOLDER_ID_FOLDERS, 'name' => $this->l10n->t('Folders')]
			]]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getIndexes(?int $musicFolderId) {
		if ($musicFolderId === self::FOLDER_ID_FOLDERS) {
			return $this->getIndexesForFolders();
		} else {
			return $this->getIndexesForArtists();
		}
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getMusicDirectory(string $id) {
		if (Util::startsWith($id, 'folder-')) {
			return $this->getMusicDirectoryForFolder($id);
		} elseif (Util::startsWith($id, 'artist-')) {
			return $this->getMusicDirectoryForArtist($id);
		} elseif (Util::startsWith($id, 'album-')) {
			return $this->getMusicDirectoryForAlbum($id);
		} elseif (Util::startsWith($id, 'podcast_channel-')) {
			return $this->getMusicDirectoryForPodcastChannel($id);
		} else {
			throw new SubsonicException("Unsupported id format $id");
		}
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbumList(
			string $type, ?string $genre, ?int $fromYear, ?int $toYear, int $size=10, int $offset=0) {
		$albums = $this->albumsForGetAlbumList($type, $genre, $fromYear, $toYear, $size, $offset);
		return $this->subsonicResponse(['albumList' =>
				['album' => \array_map([$this, 'albumToOldApi'], $albums)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbumList2(
			string $type, ?string $genre, ?int $fromYear, ?int $toYear, int $size=10, int $offset=0) {
		/*
		 * According to the API specification, the difference between this and getAlbumList
		 * should be that this function would organize albums according the metadata while
		 * getAlbumList would organize them by folders. However, we organize by metadata
		 * also in getAlbumList, because that's more natural for the Music app and many/most
		 * clients do not support getAlbumList2.
		 */
		$albums = $this->albumsForGetAlbumList($type, $genre, $fromYear, $toYear, $size, $offset);
		return $this->subsonicResponse(['albumList2' =>
				['album' => \array_map([$this, 'albumToNewApi'], $albums)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getArtists() {
		return $this->getIndexesForArtists('artists');
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getArtist(string $id) {
		$artistId = self::ripIdPrefix($id); // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->user());
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->user());

		$artistNode = $this->artistToApi($artist);
		$artistNode['album'] = \array_map([$this, 'albumToNewApi'], $albums);

		return $this->subsonicResponse(['artist' => $artistNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getArtistInfo(string $id, bool $includeNotPresent=false) {
		return $this->doGetArtistInfo('artistInfo', $id, $includeNotPresent);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getArtistInfo2(string $id, bool $includeNotPresent=false) {
		return $this->doGetArtistInfo('artistInfo2', $id, $includeNotPresent);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbumInfo(string $id) {
		return $this->doGetAlbumInfo($id);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbumInfo2(string $id) {
		return $this->doGetAlbumInfo($id);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getSimilarSongs(string $id, int $count=50) {
		return $this->doGetSimilarSongs('similarSongs', $id, $count);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getSimilarSongs2(string $id, int $count=50) {
		return $this->doGetSimilarSongs('similarSongs2', $id, $count);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getTopSongs(string $artist, int $count=50) {
		$tracks = $this->lastfmService->getTopTracks($artist, $this->user(), $count);
		return $this->subsonicResponse(['topSongs' =>
			['song' => $this->tracksToApi($tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbum(string $id) {
		$albumId = self::ripIdPrefix($id); // get rid of 'album-' prefix

		$album = $this->albumBusinessLayer->find($albumId, $this->user());
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->user());

		$albumNode = $this->albumToNewApi($album);
		$albumNode['song'] = $this->tracksToApi($tracks);
		return $this->subsonicResponse(['album' => $albumNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getSong(string $id) {
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix
		$track = $this->trackBusinessLayer->find($trackId, $this->user());
		return $this->subsonicResponse(['song' => $this->trackToApi($track)]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getRandomSongs(?string $genre, ?string $fromYear, ?string $toYear, int $size=10) {
		$size = \min($size, 500); // the API spec limits the maximum amount to 500

		if ($genre !== null) {
			$trackPool = $this->findTracksByGenre($genre);
		} else {
			$trackPool = $this->trackBusinessLayer->findAll($this->user());
		}

		if ($fromYear !== null) {
			$trackPool = \array_filter($trackPool, fn($track) => ($track->getYear() !== null && $track->getYear() >= $fromYear));
		}

		if ($toYear !== null) {
			$trackPool = \array_filter($trackPool, fn($track) => ($track->getYear() !== null && $track->getYear() <= $toYear));
		}

		$tracks = Random::pickItems($trackPool, $size);

		return $this->subsonicResponse(['randomSongs' =>
				['song' => $this->tracksToApi($tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getCoverArt(string $id, ?int $size) {
		list($type, $entityId) = self::parseEntityId($id);
		$userId = $this->user();

		if ($type == 'album') {
			$entity = $this->albumBusinessLayer->find($entityId, $userId);
		} elseif ($type == 'artist') {
			$entity = $this->artistBusinessLayer->find($entityId, $userId);
		} elseif ($type == 'podcast_channel') {
			$entity = $this->podcastService->getChannel($entityId, $userId, /*$includeEpisodes=*/ false);
		} elseif ($type == 'pl') {
			$entity = $this->playlistBusinessLayer->find($entityId, $userId);
		}

		if (!empty($entity)) {
			$rootFolder = $this->librarySettings->getFolder($userId);
			$coverData = $this->coverHelper->getCover($entity, $userId, $rootFolder, $size);
			$response = new FileResponse($coverData);
			HttpUtil::setClientCachingDays($response, 30);
			return $response;
		}

		return $this->subsonicErrorResponse(70, "entity $id has no cover");
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getLyrics(?string $artist, ?string $title) {
		$userId = $this->user();
		$matches = $this->trackBusinessLayer->findAllByNameArtistOrAlbum($title, $artist, null, $userId);
		$matchingCount = \count($matches);

		if ($matchingCount === 0) {
			$this->logger->log("No matching track for title '$title' and artist '$artist'", 'debug');
			return $this->subsonicResponse(['lyrics' => new \stdClass]);
		} else {
			if ($matchingCount > 1) {
				$this->logger->log("Found $matchingCount tracks matching title ".
								"'$title' and artist '$artist'; using the first", 'debug');
			}
			$track = $matches[0];

			$artistObj = $this->artistBusinessLayer->find($track->getArtistId(), $userId);
			$rootFolder = $this->librarySettings->getFolder($userId);
			$lyrics = $this->detailsHelper->getLyricsAsPlainText($track->getFileId(), $rootFolder);

			return $this->subsonicResponse(['lyrics' => [
					'artist' => $artistObj->getNameString($this->l10n),
					'title' => $track->getTitle(),
					'value' => $lyrics
			]]);
		}
	}

	/**
	 * OpenSubsonic extension
	 * @SubsonicAPI
	 */
	protected function getLyricsBySongId(string $id) {
		$userId = $this->user();
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix
		$track = $this->trackBusinessLayer->find($trackId, $userId);
		$artist = $this->artistBusinessLayer->find($track->getArtistId(), $userId);
		$rootFolder = $this->librarySettings->getFolder($userId);
		$allLyrics = $this->detailsHelper->getLyricsAsStructured($track->getFileId(), $rootFolder);

		return $this->subsonicResponse(['lyricsList' => [
			'structuredLyrics' => \array_map(function ($lyrics) use ($track, $artist) {
				$isSynced = $lyrics['synced'];
				return [
					'displayArtist' => $artist->getNameString($this->l10n),
					'displayTitle' => $track->getTitle(),
					'lang' => 'xxx',
					'offset' => 0,
					'synced' => $isSynced,
					'line' => \array_map(function($lineVal, $lineKey) use ($isSynced) {
						$line = ['value' => \trim($lineVal)];
						if ($isSynced) {
							$line['start'] = $lineKey;
						};
						return $line;
					}, $lyrics['lines'], \array_keys($lyrics['lines']))
				];
			}, $allLyrics) 
		]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function stream(string $id) {
		// We don't support transcoding, so 'stream' and 'download' act identically
		return $this->download($id);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function download(string $id) {
		list($type, $entityId) = self::parseEntityId($id);

		if ($type === 'track') {
			$track = $this->trackBusinessLayer->find($entityId, $this->user());
			$file = $this->getFilesystemNode($track->getFileId());

			if ($file instanceof File) {
				return new FileStreamResponse($file);
			} else {
				return $this->subsonicErrorResponse(70, 'file not found');
			}
		} elseif ($type === 'podcast_episode') {
			$episode = $this->podcastService->getEpisode($entityId, $this->user());
			if ($episode instanceof PodcastEpisode) {
				return new RedirectResponse($episode->getStreamUrl());
			} else {
				return $this->subsonicErrorResponse(70, 'episode not found');
			}
		} else {
			return $this->subsonicErrorResponse(0, "id of type $type not supported");
		}
	}

	/**
	 * @SubsonicAPI
	 */
	protected function search2(string $query, int $artistCount=20, int $artistOffset=0,
			int $albumCount=20, int $albumOffset=0, int $songCount=20, int $songOffset=0) {
		$results = $this->doSearch($query, $artistCount, $artistOffset, $albumCount, $albumOffset, $songCount, $songOffset);
		return $this->searchResponse('searchResult2', $results, /*$useNewApi=*/false);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function search3(string $query, int $artistCount=20, int $artistOffset=0,
			int $albumCount=20, int $albumOffset=0, int $songCount=20, int $songOffset=0) {
		$results = $this->doSearch($query, $artistCount, $artistOffset, $albumCount, $albumOffset, $songCount, $songOffset);
		return $this->searchResponse('searchResult3', $results, /*$useNewApi=*/true);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getGenres() {
		$genres = $this->genreBusinessLayer->findAll($this->user(), SortBy::Name);

		return $this->subsonicResponse(['genres' =>
			[
				'genre' => \array_map(fn($genre) => [
					'songCount' => $genre->getTrackCount(),
					'albumCount' => $genre->getAlbumCount(),
					'value' => $genre->getNameString($this->l10n)
				], $genres)
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getSongsByGenre(string $genre, int $count=10, int $offset=0) {
		$tracks = $this->findTracksByGenre($genre, $count, $offset);

		return $this->subsonicResponse(['songsByGenre' =>
			['song' => $this->tracksToApi($tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPlaylists() {
		$userId = $this->user();
		$playlists = $this->playlistBusinessLayer->findAll($userId);

		foreach ($playlists as &$playlist) {
			$playlist->setDuration($this->playlistBusinessLayer->getDuration($playlist->getId(), $userId));
		}

		return $this->subsonicResponse(['playlists' =>
			['playlist' => \array_map(fn($p) => $p->toSubsonicApi(), $playlists)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPlaylist(int $id) {
		$userId = $this->user();
		$playlist = $this->playlistBusinessLayer->find($id, $userId);
		$tracks = $this->playlistBusinessLayer->getPlaylistTracks($id, $userId);
		$playlist->setDuration(\array_reduce($tracks, function (?int $accuDuration, Track $track) : int {
			return (int)$accuDuration + (int)$track->getLength();
		}));

		$playlistNode = $playlist->toSubsonicApi();
		$playlistNode['entry'] = $this->tracksToApi($tracks);

		return $this->subsonicResponse(['playlist' => $playlistNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createPlaylist(?string $name, ?string $playlistId, array $songId) {
		$songIds = \array_map('self::ripIdPrefix', $songId);

		// If playlist ID has been passed, then this method actually updates an existing list instead of creating a new one.
		// The updating can't be used to rename the list, even if both ID and name are given (this is how the real Subsonic works, too).
		if (!empty($playlistId)) {
			$playlist = $this->playlistBusinessLayer->find((int)$playlistId, $this->user());
		} elseif (!empty($name)) {
			$playlist = $this->playlistBusinessLayer->create($name, $this->user());
		} else {
			throw new SubsonicException('Playlist ID or name must be specified.', 10);
		}

		$playlist->setTrackIdsFromArray($songIds);
		$this->playlistBusinessLayer->update($playlist);

		return $this->getPlaylist($playlist->getId());
	}

	/**
	 * @SubsonicAPI
	 */
	protected function updatePlaylist(int $playlistId, ?string $name, ?string $comment, array $songIdToAdd, array $songIndexToRemove) {
		$songIdsToAdd = \array_map('self::ripIdPrefix', $songIdToAdd);
		$songIndicesToRemove = \array_map('intval', $songIndexToRemove);
		$userId = $this->user();

		if (!empty($name)) {
			$this->playlistBusinessLayer->rename($name, $playlistId, $userId);
		}

		if ($comment !== null) {
			$this->playlistBusinessLayer->setComment($comment, $playlistId, $userId);
		}

		if (!empty($songIndicesToRemove)) {
			$this->playlistBusinessLayer->removeTracks($songIndicesToRemove, $playlistId, $userId);
		}

		if (!empty($songIdsToAdd)) {
			$this->playlistBusinessLayer->addTracks($songIdsToAdd, $playlistId, $userId);
		}

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deletePlaylist(int $id) {
		$this->playlistBusinessLayer->delete($id, $this->user());
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getInternetRadioStations() {
		$stations = $this->radioStationBusinessLayer->findAll($this->user());

		return $this->subsonicResponse(['internetRadioStations' => [
			'internetRadioStation' => \array_map(fn($station) => [
				'id' => $station->getId(),
				'name' => $station->getName() ?: $station->getStreamUrl(),
				'streamUrl' => $station->getStreamUrl(),
				'homePageUrl' => $station->getHomeUrl()
			], $stations)
		]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createInternetRadioStation(string $streamUrl, string $name, ?string $homepageUrl) {
		$this->radioStationBusinessLayer->create($this->user(), $name, $streamUrl, $homepageUrl);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function updateInternetRadioStation(int $id, string $streamUrl, string $name, ?string $homepageUrl) {
		$station = $this->radioStationBusinessLayer->find($id, $this->user());
		$station->setStreamUrl($streamUrl);
		$station->setName($name);
		$station->setHomeUrl($homepageUrl);
		$this->radioStationBusinessLayer->update($station);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deleteInternetRadioStation(int $id) {
		$this->radioStationBusinessLayer->delete($id, $this->user());
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getUser(string $username) {
		$userId = $this->user();
		if (\mb_strtolower($username) != \mb_strtolower($userId)) {
			throw new SubsonicException("$userId is not authorized to get details for other users.", 50);
		}

		$user = $this->userManager->get($userId);

		return $this->subsonicResponse([
			'user' => [
				'username' => $userId,
				'email' => $user->getEMailAddress(),
				'scrobblingEnabled' => true,
				'adminRole' => false,
				'settingsRole' => false,
				'downloadRole' => true,
				'uploadRole' => false,
				'playlistRole' => true,
				'coverArtRole' => false,
				'commentRole' => true,
				'podcastRole' => true,
				'streamRole' => true,
				'jukeboxRole' => false,
				'shareRole' => false,
				'videoConversionRole' => false,
				'folder' => [self::FOLDER_ID_ARTISTS, self::FOLDER_ID_FOLDERS],
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getUsers() {
		throw new SubsonicException("{$this->user()} is not authorized to get details for other users.", 50);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAvatar(string $username) {
		$userId = $this->user();
		if (\mb_strtolower($username) != \mb_strtolower($userId)) {
			throw new SubsonicException("$userId is not authorized to get avatar for other users.", 50);
		}

		$image = $this->userManager->get($userId)->getAvatarImage(150);

		if ($image !== null) {
			return new FileResponse(['content' => $image->data(), 'mimetype' => $image->mimeType()]);
		} else {
			return $this->subsonicErrorResponse(70, 'user has no avatar');
		}
	}

	/**
	 * OpenSubsonic extension
	 * @SubsonicAPI
	 */
	protected function tokenInfo() {
		// This method is intended to be used when API key is used for authentication and the user name is not
		// directly available for the client. But it shouldn't hurt to allow calling this regardless of the
		// authentication method.
		return $this->subsonicResponse(['tokenInfo' => ['username' => $this->user()]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function scrobble(array $id, array $time) {
		if (\count($id) === 0) {
			throw new SubsonicException("Required parameter 'id' missing", 10);
		}

		$userId = $this->user();
		foreach ($id as $index => $aId) {
			list($type, $trackId) = self::parseEntityId($aId);
			if ($type === 'track') {
				if (isset($time[$index])) {
					$timestamp = \substr($time[$index], 0, -3); // cut down from milliseconds to seconds
					$timeOfPlay = new \DateTime('@' . $timestamp);
				} else {
					$timeOfPlay = null;
				}
				$this->trackBusinessLayer->recordTrackPlayed((int)$trackId, $userId, $timeOfPlay);
			}
		}

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function star(array $id, array $albumId, array $artistId) {
		$targetIds = self::parseStarringParameters($id, $albumId, $artistId);
		$userId = $this->user();

		$this->trackBusinessLayer->setStarred($targetIds['tracks'], $userId);
		$this->albumBusinessLayer->setStarred($targetIds['albums'], $userId);
		$this->artistBusinessLayer->setStarred($targetIds['artists'], $userId);
		$this->podcastChannelBusinessLayer->setStarred($targetIds['podcast_channels'], $userId);
		$this->podcastEpisodeBusinessLayer->setStarred($targetIds['podcast_episodes'], $userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function unstar(array $id, array $albumId, array $artistId) {
		$targetIds = self::parseStarringParameters($id, $albumId, $artistId);
		$userId = $this->user();

		$this->trackBusinessLayer->unsetStarred($targetIds['tracks'], $userId);
		$this->albumBusinessLayer->unsetStarred($targetIds['albums'], $userId);
		$this->artistBusinessLayer->unsetStarred($targetIds['artists'], $userId);
		$this->podcastChannelBusinessLayer->unsetStarred($targetIds['podcast_channels'], $userId);
		$this->podcastEpisodeBusinessLayer->unsetStarred($targetIds['podcast_episodes'], $userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function setRating(string $id, int $rating) {
		$rating = (int)Util::limit($rating, 0, 5);
		list($type, $entityId) = self::parseEntityId($id);

		switch ($type) {
			case 'track':
				$bLayer = $this->trackBusinessLayer;
				break;
			case 'album':
				$bLayer = $this->albumBusinessLayer;
				break;
			case 'artist':
				$bLayer = $this->artistBusinessLayer;
				break;
			case 'podcast_episode':
				$bLayer = $this->podcastEpisodeBusinessLayer;
				break;
			case 'folder':
				throw new SubsonicException('Rating folders is not supported', 0);
			default:
				throw new SubsonicException("Unexpected ID format: $id", 0);
		}

		$entity = $bLayer->find($entityId, $this->user());
		$entity->setRating($rating);
		$bLayer->update($entity);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getStarred() {
		$starred = $this->doGetStarred();
		return $this->searchResponse('starred', $starred, /*$useNewApi=*/false);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getStarred2() {
		$starred = $this->doGetStarred();
		return $this->searchResponse('starred2', $starred, /*$useNewApi=*/true);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getVideos() {
		// Feature not supported, return an empty list
		return $this->subsonicResponse([
			'videos' => [
				'video' => []
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPodcasts(?string $id, bool $includeEpisodes = true) {
		if ($id !== null) {
			$id = self::ripIdPrefix($id);
			$channel = $this->podcastService->getChannel($id, $this->user(), $includeEpisodes);
			if ($channel === null) {
				throw new SubsonicException('Requested channel not found', 70);
			}
			$channels = [$channel];
		} else {
			$channels = $this->podcastService->getAllChannels($this->user(), $includeEpisodes);
		}

		return $this->subsonicResponse([
			'podcasts' => [
				'channel' => \array_map(fn($c) => $c->toSubsonicApi(), $channels)
			]
		]);
	}

	/**
	 * OpenSubsonic extension
	 * @SubsonicAPI
	 */
	protected function getPodcastEpisode(string $id) {
		$id = self::ripIdPrefix($id);
		$episode = $this->podcastService->getEpisode($id, $this->user());

		if ($episode === null) {
			throw new SubsonicException('Requested episode not found', 70);
		}

		return $this->subsonicResponse([
			'podcastEpisode' => $episode->toSubsonicApi()
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getNewestPodcasts(int $count=20) {
		$episodes = $this->podcastService->getLatestEpisodes($this->user(), $count);

		return $this->subsonicResponse([
			'newestPodcasts' => [
				'episode' => \array_map(fn($e) => $e->toSubsonicApi(), $episodes)
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function refreshPodcasts() {
		$this->podcastService->updateAllChannels($this->user());
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createPodcastChannel(string $url) {
		$result = $this->podcastService->subscribe($url, $this->user());

		switch ($result['status']) {
			case PodcastService::STATUS_OK:
				return $this->subsonicResponse([]);
			case PodcastService::STATUS_INVALID_URL:
				throw new SubsonicException("Invalid URL $url", 0);
			case PodcastService::STATUS_INVALID_RSS:
				throw new SubsonicException("The document at URL $url is not a valid podcast RSS feed", 0);
			case PodcastService::STATUS_ALREADY_EXISTS:
				throw new SubsonicException('User already has this podcast channel subscribed', 0);
			default:
				throw new SubsonicException("Unexpected status code {$result['status']}", 0);
		}
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deletePodcastChannel(string $id) {
		$id = self::ripIdPrefix($id);
		$status = $this->podcastService->unsubscribe($id, $this->user());

		switch ($status) {
			case PodcastService::STATUS_OK:
				return $this->subsonicResponse([]);
			case PodcastService::STATUS_NOT_FOUND:
				throw new SubsonicException('Channel to be deleted not found', 70);
			default:
				throw new SubsonicException("Unexpected status code $status", 0);
		}
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getBookmarks() {
		$userId = $this->user();
		$bookmarkNodes = [];
		$bookmarks = $this->bookmarkBusinessLayer->findAll($userId);

		foreach ($bookmarks as $bookmark) {
			$node = $bookmark->toSubsonicApi();
			$entryId = $bookmark->getEntryId();
			$type = $bookmark->getType();

			try {
				if ($type === Bookmark::TYPE_TRACK) {
					$track = $this->trackBusinessLayer->find($entryId, $userId);
					$node['entry'] = $this->trackToApi($track);
				} elseif ($type === Bookmark::TYPE_PODCAST_EPISODE) {
					$node['entry'] = $this->podcastEpisodeBusinessLayer->find($entryId, $userId)->toSubsonicApi();
				} else {
					$this->logger->log("Bookmark {$bookmark->getId()} had unexpected entry type $type", 'warn');
				}
				$bookmarkNodes[] = $node;
			} catch (BusinessLayerException $e) {
				$this->logger->log("Bookmarked entry with type $type and id $entryId not found", 'warn');
			}
		}

		return $this->subsonicResponse(['bookmarks' => ['bookmark' => $bookmarkNodes]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createBookmark(string $id, int $position, ?string $comment) {
		list($type, $entityId) = self::parseBookmarkIdParam($id);
		$this->bookmarkBusinessLayer->addOrUpdate($this->user(), $type, $entityId, $position, $comment);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deleteBookmark(string $id) {
		list($type, $entityId) = self::parseBookmarkIdParam($id);

		$bookmark = $this->bookmarkBusinessLayer->findByEntry($type, $entityId, $this->user());
		$this->bookmarkBusinessLayer->delete($bookmark->getId(), $this->user());

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPlayQueue() {
		// TODO: not supported yet
		return $this->subsonicResponse(['playQueue' => []]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function savePlayQueue() {
		// TODO: not supported yet
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getScanStatus() {
		return $this->subsonicResponse(['scanStatus' => [
				'scanning' => false,
				'count' => $this->trackBusinessLayer->count($this->user())
		]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getNowPlaying() {
		// TODO: not supported yet
		return $this->subsonicResponse(['nowPlaying' => ['entry' => []]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getOpenSubsonicExtensions() {
		return $this->subsonicResponse(['openSubsonicExtensions' => [
			[ 'name' => 'apiKeyAuthentication', 'versions' => [1] ],
			[ 'name' => 'formPost', 'versions' => [1] ],
			[ 'name' => 'getPodcastEpisode', 'versions' => [1] ],
			[ 'name' => 'songLyrics', 'versions' => [1] ],
		]]);
	}

	/* -------------------------------------------------------------------------
	 * Helper methods
	 * -------------------------------------------------------------------------
	 */

	private static function ensureParamHasValue(string $paramName, /*mixed*/ $paramValue) {
		if ($paramValue === null || $paramValue === '') {
			throw new SubsonicException("Required parameter '$paramName' missing", 10);
		}
	}

	private static function parseBookmarkIdParam(string $id) : array {
		list($typeName, $entityId) = self::parseEntityId($id);

		if ($typeName === 'track') {
			$type = Bookmark::TYPE_TRACK;
		} elseif ($typeName === 'podcast_episode') {
			$type = Bookmark::TYPE_PODCAST_EPISODE;
		} else {
			throw new SubsonicException("Unsupported ID format $id", 0);
		}

		return [$type, $entityId];
	}

	/**
	 * Parse parameters used in the `star` and `unstar` API methods
	 */
	private static function parseStarringParameters(array $ids, array $albumIds, array $artistIds) {
		// album IDs from newer clients
		$albumIds = \array_map('self::ripIdPrefix', $albumIds);

		// artist IDs from newer clients
		$artistIds = \array_map('self::ripIdPrefix', $artistIds);

		// Song IDs from newer clients and song/folder/album/artist IDs from older clients are all packed in $ids.
		// Also podcast IDs may come there; that is not documented part of the API but at least DSub does that.

		$trackIds = [];
		$channelIds = [];
		$episodeIds = [];

		foreach ($ids as $prefixedId) {
			list($type, $id) = self::parseEntityId($prefixedId);

			if ($type == 'track') {
				$trackIds[] = $id;
			} elseif ($type == 'album') {
				$albumIds[] = $id;
			} elseif ($type == 'artist') {
				$artistIds[] = $id;
			} elseif ($type == 'podcast_channel') {
				$channelIds[] = $id;
			} elseif ($type == 'podcast_episode') {
				$episodeIds[] = $id;
			} elseif ($type == 'folder') {
				throw new SubsonicException('Starring folders is not supported', 0);
			} else {
				throw new SubsonicException("Unexpected ID format: $prefixedId", 0);
			}
		}

		return [
			'tracks' => $trackIds,
			'albums' => $albumIds,
			'artists' => $artistIds,
			'podcast_channels' => $channelIds,
			'podcast_episodes' => $episodeIds
		];
	}

	private function user() : string {
		if ($this->userId === null) {
			throw new SubsonicException('User authentication required', 10);
		}
		return $this->userId;
	}

	private function getFilesystemNode($id) {
		$rootFolder = $this->librarySettings->getFolder($this->user());
		$nodes = $rootFolder->getById($id);

		if (\count($nodes) != 1) {
			throw new SubsonicException('file not found', 70);
		}

		return $nodes[0];
	}

	private function nameWithoutArticle(?string $name) : ?string {
		return Util::splitPrefixAndBasename($name, $this->ignoredArticles)['basename'];
	}

	private static function getIndexingChar(?string $name) {
		// For unknown artists, use '?'
		$char = '?';

		if (!empty($name)) {
			$char = \mb_convert_case(\mb_substr($name, 0, 1), MB_CASE_UPPER);
		}
		// Bundle all numeric characters together
		if (\is_numeric($char)) {
			$char = '#';
		}

		return $char;
	}

	private function getSubFoldersAndTracks(Folder $folder) : array {
		$nodes = $folder->getDirectoryListing();
		$subFolders = \array_filter($nodes, fn($n) =>
			($n instanceof Folder) && $this->librarySettings->pathBelongsToMusicLibrary($n->getPath(), $this->user())
		);

		$tracks = $this->trackBusinessLayer->findAllByFolder($folder->getId(), $this->user());

		return [$subFolders, $tracks];
	}

	private function getIndexesForFolders() {
		$rootFolder = $this->librarySettings->getFolder($this->user());

		list($subFolders, $tracks) = $this->getSubFoldersAndTracks($rootFolder);

		$indexes = [];
		foreach ($subFolders as $folder) {
			$sortName = $this->nameWithoutArticle($folder->getName());
			$indexes[self::getIndexingChar($sortName)][] = [
				'sortName' => $sortName,
				'artist' => [
					'name' => $folder->getName(),
					'id' => 'folder-' . $folder->getId()
				]
			];
		}
		\ksort($indexes, SORT_LOCALE_STRING);

		$folders = [];
		foreach ($indexes as $indexChar => $bucketArtists) {
			Util::arraySortByColumn($bucketArtists, 'sortName');
			$folders[] = ['name' => $indexChar, 'artist' => \array_column($bucketArtists, 'artist')];
		}

		return $this->subsonicResponse(['indexes' => [
			'ignoredArticles' => \implode(' ', $this->ignoredArticles),
			'index' => $folders,
			'child' => $this->tracksToApi($tracks)
		]]);
	}

	private function getMusicDirectoryForFolder($id) {
		$folderId = self::ripIdPrefix($id); // get rid of 'folder-' prefix
		$folder = $this->getFilesystemNode($folderId);

		if (!($folder instanceof Folder)) {
			throw new SubsonicException("$id is not a valid folder", 70);
		}

		list($subFolders, $tracks) = $this->getSubFoldersAndTracks($folder);

		$children = \array_merge(
			\array_map([$this, 'folderToApi'], $subFolders),
			$this->tracksToApi($tracks)
		);

		$content = [
			'directory' => [
				'id' => $id,
				'name' => $folder->getName(),
				'child' => $children
			]
		];

		// Parent folder ID is included if and only if the parent folder is not the top level
		$rootFolderId = $this->librarySettings->getFolder($this->user())->getId();
		$parentFolderId = $folder->getParent()->getId();
		if ($rootFolderId != $parentFolderId) {
			$content['parent'] = 'folder-' . $parentFolderId;
		}

		return $this->subsonicResponse($content);
	}

	private function getIndexesForArtists($rootElementName = 'indexes') {
		$artists = $this->artistBusinessLayer->findAllHavingAlbums($this->user(), SortBy::Name);

		$indexes = [];
		foreach ($artists as $artist) {
			$sortName = $this->nameWithoutArticle($artist->getName());
			$indexes[self::getIndexingChar($sortName)][] = ['sortName' => $sortName, 'artist' => $this->artistToApi($artist)];
		}
		\ksort($indexes, SORT_LOCALE_STRING);

		$result = [];
		foreach ($indexes as $indexChar => $bucketArtists) {
			Util::arraySortByColumn($bucketArtists, 'sortName');
			$result[] = ['name' => $indexChar, 'artist' => \array_column($bucketArtists, 'artist')];
		}

		return $this->subsonicResponse([$rootElementName => [
			'ignoredArticles' => \implode(' ', $this->ignoredArticles),
			'index' => $result
		]]);
	}

	private function getMusicDirectoryForArtist($id) {
		$artistId = self::ripIdPrefix($id); // get rid of 'artist-' prefix

		$artist = $this->artistBusinessLayer->find($artistId, $this->user());
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->user());

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'name' => $artist->getNameString($this->l10n),
				'child' => \array_map([$this, 'albumToOldApi'], $albums)
			]
		]);
	}

	private function getMusicDirectoryForAlbum($id) {
		$albumId = self::ripIdPrefix($id); // get rid of 'album-' prefix

		$album = $this->albumBusinessLayer->find($albumId, $this->user());
		$albumName = $album->getNameString($this->l10n);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->user());

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'artist-' . $album->getAlbumArtistId(),
				'name' => $albumName,
				'child' => $this->tracksToApi($tracks)
			]
		]);
	}

	private function getMusicDirectoryForPodcastChannel($id) {
		$channelId = self::ripIdPrefix($id); // get rid of 'podcast_channel-' prefix
		$channel = $this->podcastService->getChannel($channelId, $this->user(), /*$includeEpisodes=*/ true);

		if ($channel === null) {
			throw new SubsonicException("Podcast channel $channelId not found", 0);
		}

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'name' => $channel->getTitle(),
				'child' => \array_map(fn($e) => $e->toSubsonicApi(), $channel->getEpisodes() ?? [])
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
		$id = $artist->getId();
		$result = [
			'name' => $artist->getNameString($this->l10n),
			'id' => $id ? ('artist-' . $id) : '-1', // getArtistInfo may show artists without ID
			'albumCount' => $id ? $this->albumBusinessLayer->countByArtist($id) : 0,
			'starred' => Util::formatZuluDateTime($artist->getStarred()),
			'userRating' => $artist->getRating() ?: null,
			'averageRating' => $artist->getRating() ?: null,
			'sortName' => $this->nameWithoutArticle($artist->getName()) ?? '', // OpenSubsonic
		];

		if (!empty($artist->getCoverFileId())) {
			$result['coverArt'] = $result['id'];
			$result['artistImageUrl'] = $this->artistImageUrl($id);
		}

		return $result;
	}

	/**
	 * The "old API" format is used e.g. in getMusicDirectory and getAlbumList
	 */
	private function albumToOldApi(Album $album) : array {
		$result = $this->albumCommonApiFields($album);

		$result['parent'] = 'artist-' . $album->getAlbumArtistId();
		$result['title'] = $album->getNameString($this->l10n);
		$result['isDir'] = true;

		return $result;
	}

	/**
	 * The "new API" format is used e.g. in getAlbum and getAlbumList2
	 */
	private function albumToNewApi(Album $album) : array {
		$result = $this->albumCommonApiFields($album);

		$result['artistId'] = 'artist-' . $album->getAlbumArtistId();
		$result['name'] = $album->getNameString($this->l10n);
		$result['songCount'] = $this->trackBusinessLayer->countByAlbum($album->getId());
		$result['duration'] = $this->trackBusinessLayer->totalDurationOfAlbum($album->getId());

		return $result;
	}

	private function albumCommonApiFields(Album $album) : array {
		$genreString = \implode(', ', \array_map(
			fn(Genre $genre) => $genre->getNameString($this->l10n),
			$album->getGenres() ?? []
		));

		return [
			'id' => 'album-' . $album->getId(),
			'artist' => $album->getAlbumArtistNameString($this->l10n),
			'created' => Util::formatZuluDateTime($album->getCreated()),
			'coverArt' => empty($album->getCoverFileId()) ? null : 'album-' . $album->getId(),
			'starred' => Util::formatZuluDateTime($album->getStarred()),
			'userRating' => $album->getRating() ?: null,
			'averageRating' => $album->getRating() ?: null,
			'year' => $album->yearToAPI(),
			'genre' => $genreString ?: null,
			'sortName' => $this->nameWithoutArticle($album->getName()) ?? '', // OpenSubsonic
		];
	}

	/**
	 * @param Track[] $tracks
	 */
	private function tracksToApi(array $tracks) : array {
		$userId = $this->user();
		$musicFolder = $this->librarySettings->getFolder($userId);
		$this->trackBusinessLayer->injectFolderPathsToTracks($tracks, $userId, $musicFolder);
		$this->albumBusinessLayer->injectAlbumsToTracks($tracks, $userId);
		return \array_map(fn($t) => $t->toSubsonicApi($this->l10n, $this->ignoredArticles), $tracks);
	}

	private function trackToApi(Track $track) : array {
		return $this->tracksToApi([$track])[0];
	}

	/**
	 * Common logic for getAlbumList and getAlbumList2
	 * @return Album[]
	 */
	private function albumsForGetAlbumList(
			string $type, ?string $genre, ?int $fromYear, ?int $toYear, int $size, int $offset) : array {
		$size = \min($size, 500); // the API spec limits the maximum amount to 500
		$userId = $this->user();

		$albums = [];

		switch ($type) {
			case 'random':
				$allAlbums = $this->albumBusinessLayer->findAll($userId);
				$indices = $this->random->getIndices(\count($allAlbums), $offset, $size, $userId, 'subsonic_albums');
				$albums = Util::arrayMultiGet($allAlbums, $indices);
				break;
			case 'starred':
				$albums = $this->albumBusinessLayer->findAllStarred($userId, $size, $offset);
				break;
			case 'alphabeticalByName':
				$albums = $this->albumBusinessLayer->findAll($userId, SortBy::Name, $size, $offset);
				break;
			case 'alphabeticalByArtist':
				$albums = $this->albumBusinessLayer->findAll($userId, SortBy::Parent, $size, $offset);
				break;
			case 'byGenre':
				self::ensureParamHasValue('genre', $genre);
				$albums = $this->findAlbumsByGenre($genre, $size, $offset);
				break;
			case 'byYear':
				self::ensureParamHasValue('fromYear', $fromYear);
				self::ensureParamHasValue('toYear', $toYear);
				$albums = $this->albumBusinessLayer->findAllByYearRange($fromYear, $toYear, $userId, $size, $offset);
				break;
			case 'newest':
				$albums = $this->albumBusinessLayer->findAll($userId, SortBy::Newest, $size, $offset);
				break;
			case 'frequent':
				$albums = $this->albumBusinessLayer->findFrequentPlay($userId, $size, $offset);
				break;
			case 'recent':
				$albums = $this->albumBusinessLayer->findRecentPlay($userId, $size, $offset);
				break;
			case 'highest':
				$albums = $this->albumBusinessLayer->findAllRated($userId, $size, $offset);
				break;
			default:
				$this->logger->log("Album list type '$type' is not supported", 'debug');
				break;
		}

		return $albums;
	}

	/**
	 * Given any entity ID like 'track-123' or 'album-2' or 'artist-3' or 'folder-4', return the matching
	 * numeric artist identifier if possible (may be e.g. performer of the track or album, or an artist
	 * with a name matching the folder name)
	 */
	private function getArtistIdFromEntityId(string $entityId) : ?int {
		list($type, $id) = self::parseEntityId($entityId);
		$userId = $this->user();

		switch ($type) {
			case 'artist':
				return $id;
			case 'album':
				return $this->albumBusinessLayer->find($id, $userId)->getAlbumArtistId();
			case 'track':
				return $this->trackBusinessLayer->find($id, $userId)->getArtistId();
			case 'folder':
				$folder = $this->librarySettings->getFolder($userId)->getById($id)[0] ?? null;
				if ($folder !== null) {
					$artist = $this->artistBusinessLayer->findAllByName($folder->getName(), $userId)[0] ?? null;
					if ($artist !== null) {
						return $artist->getId();
					}
				}
				break;
		}

		return null;
	}

	/**
	 * Common logic for getArtistInfo and getArtistInfo2
	 */
	private function doGetArtistInfo(string $rootName, string $id, bool $includeNotPresent) {
		$content = [];

		$userId = $this->user();
		$artistId = $this->getArtistIdFromEntityId($id);
		if ($artistId !== null) {
			$info = $this->lastfmService->getArtistInfo($artistId, $userId);

			if (isset($info['artist'])) {
				$content = [
					'biography' => $info['artist']['bio']['summary'],
					'lastFmUrl' => $info['artist']['url'],
					'musicBrainzId' => $info['artist']['mbid'] ?? null
				];

				$similarArtists = $this->lastfmService->getSimilarArtists($artistId, $userId, $includeNotPresent);
				$content['similarArtist'] = \array_map([$this, 'artistToApi'], $similarArtists);
			}

			$artist = $this->artistBusinessLayer->find($artistId, $userId);
			if ($artist->getCoverFileId() !== null) {
				$content['largeImageUrl'] = [$this->artistImageUrl($artistId)];
			}
		}

		// This method is unusual in how it uses non-attribute elements in the response. On the other hand,
		// all the details of the <similarArtist> elements are rendered as attributes. List those separately.
		$attributeKeys = ['name', 'id', 'albumCount', 'coverArt', 'artistImageUrl', 'starred'];

		return $this->subsonicResponse([$rootName => $content], $attributeKeys);
	}

	/**
	 * Given any entity ID like 'track-123' or 'album-2' or 'folder-4', return the matching numeric
	 * album identifier if possible (may be e.g. host album of the track or album with a name
	 * matching the folder name)
	 */
	private function getAlbumIdFromEntityId(string $entityId) : ?int {
		list($type, $id) = self::parseEntityId($entityId);
		$userId = $this->user();

		switch ($type) {
			case 'album':
				return $id;
			case 'track':
				return $this->trackBusinessLayer->find($id, $userId)->getAlbumId();
			case 'folder':
				$folder = $this->librarySettings->getFolder($userId)->getById($id)[0] ?? null;
				if ($folder !== null) {
					$album = $this->albumBusinessLayer->findAllByName($folder->getName(), $userId)[0] ?? null;
					if ($album !== null) {
						return $album->getId();
					}
				}
				break;
		}

		return null;
	}

	/**
	 * Common logic for getAlbumInfo and getAlbumInfo2
	 */
	private function doGetAlbumInfo(string $id) {
		$content = [];

		$albumId = $this->getAlbumIdFromEntityId($id);
		if ($albumId !== null) {
			$info = $this->lastfmService->getAlbumInfo($albumId, $this->user());

			if (isset($info['album'])) {
				$content = [
					'notes' => $info['album']['wiki']['summary'] ?? null,
					'lastFmUrl' => $info['album']['url'],
					'musicBrainzId' => $info['album']['mbid'] ?? null
				];

				foreach ($info['album']['image'] ?? [] as $imageInfo) {
					if (!empty($imageInfo['size'])) {
						$content[$imageInfo['size'] . 'ImageUrl'] = $imageInfo['#text'];
					}
				}
			}
		}

		// This method is unusual in how it uses non-attribute elements in the response.
		return $this->subsonicResponse(['albumInfo' => $content], []);
	}

	/**
	 * Common logic for getSimilarSongs and getSimilarSongs2
	 */
	private function doGetSimilarSongs(string $rootName, string $id, int $count) {
		$userId = $this->user();

		if (Util::startsWith($id, 'artist')) {
			$artistId = self::ripIdPrefix($id);
		} elseif (Util::startsWith($id, 'album')) {
			$albumId = self::ripIdPrefix($id);
			$artistId = $this->albumBusinessLayer->find($albumId, $userId)->getAlbumArtistId();
		} elseif (Util::startsWith($id, 'track')) {
			$trackId = self::ripIdPrefix($id);
			$artistId = $this->trackBusinessLayer->find($trackId, $userId)->getArtistId();
		} else {
			throw new SubsonicException("Id $id has a type not supported on getSimilarSongs", 0);
		}

		$artists = $this->lastfmService->getSimilarArtists($artistId, $userId);
		$artists[] = $this->artistBusinessLayer->find($artistId, $userId);

		// Get all songs by the found artists
		$songs = [];
		foreach ($artists as $artist) {
			$songs = \array_merge($songs, $this->trackBusinessLayer->findAllByArtist($artist->getId(), $userId));
		}

		// Randomly select the desired number of songs
		$songs = $this->random->pickItems($songs, $count);

		return $this->subsonicResponse([$rootName => [
			'song' => $this->tracksToApi($songs)
		]]);
	}

	/**
	 * Common logic for search2 and search3
	 * @return array with keys 'artists', 'albums', and 'tracks'
	 */
	private function doSearch(string $query, int $artistCount, int $artistOffset,
			int $albumCount, int $albumOffset, int $songCount, int $songOffset) : array {

		$userId = $this->user();

		// The searches support '*' as a wildcard. Convert those to the SQL wildcard '%' as that's what the business layer searches support.
		$query = \str_replace('*', '%', $query);

		return [
			'artists' => $this->artistBusinessLayer->findAllByName($query, $userId, MatchMode::Substring, $artistCount, $artistOffset),
			'albums' => $this->albumBusinessLayer->findAllByNameRecursive($query, $userId, $albumCount, $albumOffset),
			'tracks' => $this->trackBusinessLayer->findAllByNameRecursive($query, $userId, $songCount, $songOffset)
		];
	}

	/**
	 * Common logic for getStarred and getStarred2
	 * @return array
	 */
	private function doGetStarred() {
		$userId = $this->user();
		return [
			'artists' => $this->artistBusinessLayer->findAllStarred($userId),
			'albums' => $this->albumBusinessLayer->findAllStarred($userId),
			'tracks' => $this->trackBusinessLayer->findAllStarred($userId)
		];
	}

	/**
	 * Common response building logic for search2, search3, getStarred, and getStarred2
	 * @param string $title Name of the main node in the response message
	 * @param array $results Search results with keys 'artists', 'albums', and 'tracks'
	 * @param boolean $useNewApi Set to true for search3 and getStarred2. There is a difference
	 *                           in the formatting of the album nodes.
	 * @return \OCP\AppFramework\Http\Response
	 */
	private function searchResponse($title, $results, $useNewApi) {
		$albumMapFunc = $useNewApi ? 'albumToNewApi' : 'albumToOldApi';

		return $this->subsonicResponse([$title => [
			'artist' => \array_map([$this, 'artistToApi'], $results['artists']),
			'album' => \array_map([$this, $albumMapFunc], $results['albums']),
			'song' => $this->tracksToApi($results['tracks'])
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
		$genre = $this->findGenreByName($genreName);

		if ($genre) {
			return $this->trackBusinessLayer->findAllByGenre($genre->getId(), $this->user(), $limit, $offset);
		} else {
			return [];
		}
	}

	/**
	 * Find albums by genre name
	 * @param string $genreName
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Album[]
	 */
	private function findAlbumsByGenre($genreName, $limit=null, $offset=null) {
		$genre = $this->findGenreByName($genreName);

		if ($genre) {
			return $this->albumBusinessLayer->findAllByGenre($genre->getId(), $this->user(), $limit, $offset);
		} else {
			return [];
		}
	}

	private function findGenreByName($name) {
		$genreArr = $this->genreBusinessLayer->findAllByName($name, $this->user());
		if (\count($genreArr) == 0 && $name == Genre::unknownNameString($this->l10n)) {
			$genreArr = $this->genreBusinessLayer->findAllByName('', $this->user());
		}
		return \count($genreArr) ? $genreArr[0] : null;
	}

	private function artistImageUrl(int $id) : string {
		$token = $this->imageService->getToken('artist', $id, $this->keyId);
		return $this->urlGenerator->linkToRouteAbsolute('music.ampacheImage.image',
			['object_type' => 'artist', 'object_id' => $id, 'token' => $token, 'size' => CoverHelper::DO_NOT_CROP_OR_SCALE]);
	}

	/**
	 * Given a prefixed ID like 'artist-123' or 'track-45', return the string part and the numeric part.
	 * @throws SubsonicException if the \a $id doesn't follow the expected pattern
	 */
	private static function parseEntityId(string $id) : array {
		$parts = \explode('-', $id);
		if (\count($parts) !== 2) {
			throw new SubsonicException("Unexpected ID format: $id", 0);
		}
		$parts[1] = (int)$parts[1];
		return $parts;
	}

	/**
	 * Given a prefixed ID like 'artist-123' or 'track-45', return just the numeric part.
	 */
	private static function ripIdPrefix(string $id) : int {
		return self::parseEntityId($id)[1];
	}

	private function subsonicResponse($content, $useAttributes=true, $status = 'ok') {
		$content['status'] = $status;
		$content['version'] = self::API_VERSION;
		$content['type'] = AppInfo::getFullName();
		$content['serverVersion'] = AppInfo::getVersion();
		$content['openSubsonic'] = true;
		$responseData = ['subsonic-response' => Util::arrayRejectRecursive($content, 'is_null')];

		if ($this->format == 'json') {
			$response = new JSONResponse($responseData);
		} elseif ($this->format == 'jsonp') {
			$responseData = \json_encode($responseData);
			$response = new DataDisplayResponse("{$this->callback}($responseData);");
			$response->addHeader('Content-Type', 'text/javascript; charset=UTF-8');
		} else {
			if (\is_array($useAttributes)) {
				$useAttributes = \array_merge($useAttributes, ['status', 'version', 'type', 'serverVersion', 'xmlns']);
			}
			$responseData['subsonic-response']['xmlns'] = 'http://subsonic.org/restapi';
			$response = new XmlResponse($responseData, $useAttributes);
		}

		return $response;
	}

	public function subsonicErrorResponse($errorCode, $errorMessage) {
		return $this->subsonicResponse([
				'error' => [
					'code' => $errorCode,
					'message' => $errorMessage
				]
			], true, 'failed');
	}
}
