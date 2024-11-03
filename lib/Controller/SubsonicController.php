<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2024
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

use OCA\Music\Utility\AppInfo;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\DetailsHelper;
use OCA\Music\Utility\LastfmService;
use OCA\Music\Utility\LibrarySettings;
use OCA\Music\Utility\PodcastService;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Util;

class SubsonicController extends Controller {
	const API_VERSION = '1.16.1';
	const FOLDER_ID_ARTISTS = -1;
	const FOLDER_ID_FOLDERS = -2;

	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $bookmarkBusinessLayer;
	private $genreBusinessLayer;
	private $playlistBusinessLayer;
	private $podcastChannelBusinessLayer;
	private $podcastEpisodeBusinessLayer;
	private $radioStationBusinessLayer;
	private $trackBusinessLayer;
	private $urlGenerator;
	private $userManager;
	private $librarySettings;
	private $l10n;
	private $coverHelper;
	private $detailsHelper;
	private $lastfmService;
	private $podcastService;
	private $random;
	private $logger;
	private $userId;
	private $ignoredArticles;
	private $format;
	private $callback;

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
		$this->random = $random;
		$this->logger = $logger;
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
	 * @param string $userId
	 */
	public function setAuthenticatedUser(string $userId) {
		$this->userId = $userId;
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

		// There's only one method allowed wihout a logged-in user
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
				'valid' => 'true'
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

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);

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
		$tracks = $this->lastfmService->getTopTracks($artist, $this->userId, $count);
		return $this->subsonicResponse(['topSongs' =>
			['song' => $this->tracksToApi($tracks)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAlbum(string $id) {
		$albumId = self::ripIdPrefix($id); // get rid of 'album-' prefix

		$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);

		$albumNode = $this->albumToNewApi($album);
		$albumNode['song'] = \array_map(function ($track) use ($album) {
			$track->setAlbum($album);
			return $track->toSubsonicApi($this->l10n, $this->ignoredArticles);
		}, $tracks);
		return $this->subsonicResponse(['album' => $albumNode]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getSong(string $id) {
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix
		$track = $this->trackBusinessLayer->find($trackId, $this->userId);
		$track->setAlbum($this->albumBusinessLayer->find($track->getAlbumId(), $this->userId));

		return $this->subsonicResponse(['song' => $track->toSubsonicApi($this->l10n, $this->ignoredArticles)]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getRandomSongs(?string $genre, ?string $fromYear, ?string $toYear, int $size=10) {
		$size = \min($size, 500); // the API spec limits the maximum amount to 500

		if ($genre !== null) {
			$trackPool = $this->findTracksByGenre($genre);
		} else {
			$trackPool = $this->trackBusinessLayer->findAll($this->userId);
		}

		if ($fromYear !== null) {
			$trackPool = \array_filter($trackPool, function ($track) use ($fromYear) {
				return ($track->getYear() !== null && $track->getYear() >= $fromYear);
			});
		}

		if ($toYear !== null) {
			$trackPool = \array_filter($trackPool, function ($track) use ($toYear) {
				return ($track->getYear() !== null && $track->getYear() <= $toYear);
			});
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

		if ($type == 'album') {
			$entity = $this->albumBusinessLayer->find($entityId, $this->userId);
		} elseif ($type == 'artist') {
			$entity = $this->artistBusinessLayer->find($entityId, $this->userId);
		} elseif ($type == 'podcast_channel') {
			$entity = $this->podcastService->getChannel($entityId, $this->userId, /*$includeEpisodes=*/ false);
		} elseif ($type == 'pl') {
			$entity = $this->playlistBusinessLayer->find($entityId, $this->userId);
		}

		if (!empty($entity)) {
			$rootFolder = $this->librarySettings->getFolder($this->userId);
			$coverData = $this->coverHelper->getCover($entity, $this->userId, $rootFolder, $size);
			return new FileResponse($coverData);
		}

		return $this->subsonicErrorResponse(70, "entity $id has no cover");
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getLyrics(?string $artist, ?string $title) {
		$matches = $this->trackBusinessLayer->findAllByNameArtistOrAlbum($title, $artist, null, $this->userId);
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

			$artistObj = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
			$rootFolder = $this->librarySettings->getFolder($this->userId);
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
		$trackId = self::ripIdPrefix($id); // get rid of 'track-' prefix
		$track = $this->trackBusinessLayer->find($trackId, $this->userId);
		$artist = $this->artistBusinessLayer->find($track->getArtistId(), $this->userId);
		$rootFolder = $this->librarySettings->getFolder($this->userId);
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
			$track = $this->trackBusinessLayer->find($entityId, $this->userId);
			$file = $this->getFilesystemNode($track->getFileId());

			if ($file instanceof File) {
				return new FileStreamResponse($file);
			} else {
				return $this->subsonicErrorResponse(70, 'file not found');
			}
		} elseif ($type === 'podcast_episode') {
			$episode = $this->podcastService->getEpisode($entityId, $this->userId);
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
		$genres = $this->genreBusinessLayer->findAll($this->userId, SortBy::Name);

		return $this->subsonicResponse(['genres' =>
			[
				'genre' => \array_map(function ($genre) {
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
		$playlists = $this->playlistBusinessLayer->findAll($this->userId);

		foreach ($playlists as &$playlist) {
			$playlist->setDuration($this->playlistBusinessLayer->getDuration($playlist->getId(), $this->userId));
		}

		return $this->subsonicResponse(['playlists' =>
			['playlist' => Util::arrayMapMethod($playlists, 'toSubsonicApi')]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPlaylist(int $id) {
		$playlist = $this->playlistBusinessLayer->find($id, $this->userId);
		$tracks = $this->playlistBusinessLayer->getPlaylistTracks($id, $this->userId);
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
			$playlist = $this->playlistBusinessLayer->find((int)$playlistId, $this->userId);
		} elseif (!empty($name)) {
			$playlist = $this->playlistBusinessLayer->create($name, $this->userId);
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

		if (!empty($name)) {
			$this->playlistBusinessLayer->rename($name, $playlistId, $this->userId);
		}

		if ($comment !== null) {
			$this->playlistBusinessLayer->setComment($comment, $playlistId, $this->userId);
		}

		if (!empty($songIndicesToRemove)) {
			$this->playlistBusinessLayer->removeTracks($songIndicesToRemove, $playlistId, $this->userId);
		}

		if (!empty($songIdsToAdd)) {
			$this->playlistBusinessLayer->addTracks($songIdsToAdd, $playlistId, $this->userId);
		}

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deletePlaylist(int $id) {
		$this->playlistBusinessLayer->delete($id, $this->userId);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getInternetRadioStations() {
		$stations = $this->radioStationBusinessLayer->findAll($this->userId);

		return $this->subsonicResponse(['internetRadioStations' =>
				['internetRadioStation' => \array_map(function($station) {
					return [
						'id' => $station->getId(),
						'name' => $station->getName() ?: $station->getStreamUrl(),
						'streamUrl' => $station->getStreamUrl(),
						'homePageUrl' => $station->getHomeUrl()
					];
				}, $stations)]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createInternetRadioStation(string $streamUrl, string $name, ?string $homepageUrl) {
		$this->radioStationBusinessLayer->create($this->userId, $name, $streamUrl, $homepageUrl);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function updateInternetRadioStation(int $id, string $streamUrl, string $name, ?string $homepageUrl) {
		$station = $this->radioStationBusinessLayer->find($id, $this->userId);
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
		$this->radioStationBusinessLayer->delete($id, $this->userId);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getUser(string $username) {
		if (\mb_strtolower($username) != \mb_strtolower($this->userId)) {
			throw new SubsonicException("{$this->userId} is not authorized to get details for other users.", 50);
		}

		$user = $this->userManager->get($this->userId);

		return $this->subsonicResponse([
			'user' => [
				'username' => $this->userId,
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
		throw new SubsonicException("{$this->userId} is not authorized to get details for other users.", 50);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getAvatar(string $username) {
		if (\mb_strtolower($username) != \mb_strtolower($this->userId)) {
			throw new SubsonicException("{$this->userId} is not authorized to get avatar for other users.", 50);
		}

		$image = $this->userManager->get($this->userId)->getAvatarImage(150);

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
		return $this->subsonicResponse(['tokenInfo' => ['username' => $this->userId]]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function scrobble(array $id, array $time) {
		if (\count($id) === 0) {
			throw new SubsonicException("Required parameter 'id' missing", 10);
		}

		foreach ($id as $index => $aId) {
			list($type, $trackId) = self::parseEntityId($aId);
			if ($type === 'track') {
				if (isset($time[$index])) {
					$timestamp = \substr($time[$index], 0, -3); // cut down from milliseconds to seconds
					$timeOfPlay = new \DateTime('@' . $timestamp);
				} else {
					$timeOfPlay = null;
				}
				$this->trackBusinessLayer->recordTrackPlayed((int)$trackId, $this->userId, $timeOfPlay);
			}
		}

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function star(array $id, array $albumId, array $artistId) {
		$targetIds = self::parseStarringParameters($id, $albumId, $artistId);

		$this->trackBusinessLayer->setStarred($targetIds['tracks'], $this->userId);
		$this->albumBusinessLayer->setStarred($targetIds['albums'], $this->userId);
		$this->artistBusinessLayer->setStarred($targetIds['artists'], $this->userId);
		$this->podcastChannelBusinessLayer->setStarred($targetIds['podcast_channels'], $this->userId);
		$this->podcastEpisodeBusinessLayer->setStarred($targetIds['podcast_episodes'], $this->userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function unstar(array $id, array $albumId, array $artistId) {
		$targetIds = self::parseStarringParameters($id, $albumId, $artistId);

		$this->trackBusinessLayer->unsetStarred($targetIds['tracks'], $this->userId);
		$this->albumBusinessLayer->unsetStarred($targetIds['albums'], $this->userId);
		$this->artistBusinessLayer->unsetStarred($targetIds['artists'], $this->userId);
		$this->podcastChannelBusinessLayer->unsetStarred($targetIds['podcast_channels'], $this->userId);
		$this->podcastEpisodeBusinessLayer->unsetStarred($targetIds['podcast_episodes'], $this->userId);

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

		$entity = $bLayer->find($entityId, $this->userId);
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
			$channel = $this->podcastService->getChannel($id, $this->userId, $includeEpisodes);
			if ($channel === null) {
				throw new SubsonicException('Requested channel not found', 70);
			}
			$channels = [$channel];
		} else {
			$channels = $this->podcastService->getAllChannels($this->userId, $includeEpisodes);
		}

		return $this->subsonicResponse([
			'podcasts' => [
				'channel' => Util::arrayMapMethod($channels, 'toSubsonicApi')
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getNewestPodcasts(int $count=20) {
		$episodes = $this->podcastService->getLatestEpisodes($this->userId, $count);

		return $this->subsonicResponse([
			'newestPodcasts' => [
				'episode' => Util::arrayMapMethod($episodes, 'toSubsonicApi')
			]
		]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function refreshPodcasts() {
		$this->podcastService->updateAllChannels($this->userId);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function createPodcastChannel(string $url) {
		$result = $this->podcastService->subscribe($url, $this->userId);

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
		$status = $this->podcastService->unsubscribe($id, $this->userId);

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
		$bookmarkNodes = [];
		$bookmarks = $this->bookmarkBusinessLayer->findAll($this->userId);

		foreach ($bookmarks as $bookmark) {
			$node = $bookmark->toSubsonicApi();
			$entryId = $bookmark->getEntryId();
			$type = $bookmark->getType();

			try {
				if ($type === Bookmark::TYPE_TRACK) {
					$track = $this->trackBusinessLayer->find($entryId, $this->userId);
					$track->setAlbum($this->albumBusinessLayer->find($track->getAlbumId(), $this->userId));
					$node['entry'] = $track->toSubsonicApi($this->l10n, $this->ignoredArticles);
				} elseif ($type === Bookmark::TYPE_PODCAST_EPISODE) {
					$node['entry'] = $this->podcastEpisodeBusinessLayer->find($entryId, $this->userId)->toSubsonicApi();
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
		$this->bookmarkBusinessLayer->addOrUpdate($this->userId, $type, $entityId, $position, $comment);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function deleteBookmark(string $id) {
		list($type, $entityId) = self::parseBookmarkIdParam($id);

		$bookmark = $this->bookmarkBusinessLayer->findByEntry($type, $entityId, $this->userId);
		$this->bookmarkBusinessLayer->delete($bookmark->getId(), $this->userId);

		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getPlayQueue() {
		$playQueue = ['entry' => [], 'changedBy' => $this->userId];
		$playlists = $this->playlistBusinessLayer->findAllByName('Queue', $this->userId);
		if (\count($playlists) > 1) {
			return $this->subsonicErrorResponse(10, 'Found multiple play queues');
		} elseif (\count($playlists) == 1) {
			$playlist = $playlists[0];
			$tracks = $this->playlistBusinessLayer->getPlaylistTracks($playlist->id, $this->userId);
			$playQueue['entry'] = $this->tracksToApi($tracks);
			$playQueue['changed'] = Util::formatZuluDateTime($playlist->getUpdated());
			$comment = $playlist->getComment();
			if (!empty($comment) && $additional = \json_decode($comment, true)) {
				$playQueue = \array_merge($playQueue, $additional);
			}
		}
		return $this->subsonicResponse(['playQueue' => $playQueue]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function savePlayQueue(array $id, ?string $current = null, ?int $position = null) {
		$playlists = $this->playlistBusinessLayer->findAllByName('Queue', $this->userId);
		if (\count($playlists) == 0) {
			$playlist = $this->playlistBusinessLayer->create('Queue', $this->userId);
		} elseif (\count($playlists) > 1) {
			return $this->subsonicErrorResponse(10, 'Found multiple play queues');
		} else {
			$playlist = $playlists[0];
		}

		$this->playlistBusinessLayer->setTracks(
			\array_map('self::ripIdPrefix', $id),
			$playlist->id,
			$this->userId
		);
		$additional = [];
		if (isset($current))
			$additional['current'] = $current;
		if (isset($position))
			$additional['position'] = $position;
		$this->playlistBusinessLayer->setComment(\json_encode($additional), $playlist->id, $this->userId);
		return $this->subsonicResponse([]);
	}

	/**
	 * @SubsonicAPI
	 */
	protected function getScanStatus() {
		return $this->subsonicResponse(['scanStatus' => [
				'scanning' => false,
				'count' => $this->trackBusinessLayer->count($this->userId)
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

	private function getFilesystemNode($id) {
		$rootFolder = $this->librarySettings->getFolder($this->userId);
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

	private function getSubfoldersAndTracks(Folder $folder) : array {
		$nodes = $folder->getDirectoryListing();
		$subFolders = \array_filter($nodes, function ($n) {
			return ($n instanceof Folder) && $this->librarySettings->pathBelongsToMusicLibrary($n->getPath(), $this->userId);
		});

		$tracks = $this->trackBusinessLayer->findAllByFolder($folder->getId(), $this->userId);

		return [$subFolders, $tracks];
	}

	private function getIndexesForFolders() {
		$rootFolder = $this->librarySettings->getFolder($this->userId);

		list($subFolders, $tracks) = $this->getSubfoldersAndTracks($rootFolder);

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

		list($subFolders, $tracks) = $this->getSubfoldersAndTracks($folder);

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
		$rootFolderId = $this->librarySettings->getFolder($this->userId)->getId();
		$parentFolderId = $folder->getParent()->getId();
		if ($rootFolderId != $parentFolderId) {
			$content['parent'] = 'folder-' . $parentFolderId;
		}

		return $this->subsonicResponse($content);
	}

	private function getIndexesForArtists($rootElementName = 'indexes') {
		$artists = $this->artistBusinessLayer->findAllHavingAlbums($this->userId, SortBy::Name);

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

		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);

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

		$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		$albumName = $album->getNameString($this->l10n);
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'parent' => 'artist-' . $album->getAlbumArtistId(),
				'name' => $albumName,
				'child' => \array_map(function ($track) use ($album) {
					$track->setAlbum($album);
					return $track->toSubsonicApi($this->l10n, $this->ignoredArticles);
				}, $tracks)
			]
		]);
	}

	private function getMusicDirectoryForPodcastChannel($id) {
		$channelId = self::ripIdPrefix($id); // get rid of 'podcast_channel-' prefix
		$channel = $this->podcastService->getChannel($channelId, $this->userId, /*$includeEpisodes=*/ true);

		if ($channel === null) {
			throw new SubsonicException("Podcast channel $channelId not found", 0);
		}

		return $this->subsonicResponse([
			'directory' => [
				'id' => $id,
				'name' => $channel->getTitle(),
				'child' => Util::arrayMapMethod($channel->getEpisodes() ?? [], 'toSubsonicApi')
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
			$result['artistImageUrl'] = $this->artistImageUrl($result['id']);
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
		$genreString = \implode(', ', \array_map(function (Genre $genre) {
			return $genre->getNameString($this->l10n);
		}, $album->getGenres() ?? []));

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
	 * @return array
	 */
	private function tracksToApi(array $tracks) : array {
		$this->albumBusinessLayer->injectAlbumsToTracks($tracks, $this->userId);
		return Util::arrayMapMethod($tracks, 'toSubsonicApi', [$this->l10n, $this->ignoredArticles]);
	}

	/**
	 * Common logic for getAlbumList and getAlbumList2
	 * @return Album[]
	 */
	private function albumsForGetAlbumList(
			string $type, ?string $genre, ?int $fromYear, ?int $toYear, int $size, int $offset) : array {
		$size = \min($size, 500); // the API spec limits the maximum amount to 500

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
				$albums = $this->albumBusinessLayer->findAll($this->userId, SortBy::Parent, $size, $offset);
				break;
			case 'byGenre':
				self::ensureParamHasValue('genre', $genre);
				$albums = $this->findAlbumsByGenre($genre, $size, $offset);
				break;
			case 'byYear':
				self::ensureParamHasValue('fromYear', $fromYear);
				self::ensureParamHasValue('toYear', $toYear);
				$albums = $this->albumBusinessLayer->findAllByYearRange($fromYear, $toYear, $this->userId, $size, $offset);
				break;
			case 'newest':
				$albums = $this->albumBusinessLayer->findAll($this->userId, SortBy::Newest, $size, $offset);
				break;
			case 'frequent':
				$albums = $this->albumBusinessLayer->findFrequentPlay($this->userId, $size, $offset);
				break;
			case 'recent':
				$albums = $this->albumBusinessLayer->findRecentPlay($this->userId, $size, $offset);
				break;
			case 'highest':
				$albums = $this->albumBusinessLayer->findAllRated($this->userId, $size, $offset);
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

		switch ($type) {
			case 'artist':
				return $id;
			case 'album':
				return $this->albumBusinessLayer->find($id, $this->userId)->getAlbumArtistId();
			case 'track':
				return $this->trackBusinessLayer->find($id, $this->userId)->getArtistId();
			case 'folder':
				$folder = $this->librarySettings->getFolder($this->userId)->getById($id)[0] ?? null;
				if ($folder !== null) {
					$artist = $this->artistBusinessLayer->findAllByName($folder->getName(), $this->userId)[0] ?? null;
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

		$artistId = $this->getArtistIdFromEntityId($id);
		if ($artistId !== null) {
			$info = $this->lastfmService->getArtistInfo($artistId, $this->userId);

			if (isset($info['artist'])) {
				$content = [
					'biography' => $info['artist']['bio']['summary'],
					'lastFmUrl' => $info['artist']['url'],
					'musicBrainzId' => $info['artist']['mbid'] ?? null
				];

				$similarArtists = $this->lastfmService->getSimilarArtists($artistId, $this->userId, $includeNotPresent);
				$content['similarArtist'] = \array_map([$this, 'artistToApi'], $similarArtists);
			}

			$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
			if ($artist->getCoverFileId() !== null) {
				$content['largeImageUrl'] = [$this->artistImageUrl('artist-' . $artistId)];
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

		switch ($type) {
			case 'album':
				return $id;
			case 'track':
				return $this->trackBusinessLayer->find($id, $this->userId)->getAlbumId();
			case 'folder':
				$folder = $this->librarySettings->getFolder($this->userId)->getById($id)[0] ?? null;
				if ($folder !== null) {
					$album = $this->albumBusinessLayer->findAllByName($folder->getName(), $this->userId)[0] ?? null;
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
			$info = $this->lastfmService->getAlbumInfo($albumId, $this->userId);

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
		if (Util::startsWith($id, 'artist')) {
			$artistId = self::ripIdPrefix($id);
		} elseif (Util::startsWith($id, 'album')) {
			$albumId = self::ripIdPrefix($id);
			$artistId = $this->albumBusinessLayer->find($albumId, $this->userId)->getAlbumArtistId();
		} elseif (Util::startsWith($id, 'track')) {
			$trackId = self::ripIdPrefix($id);
			$artistId = $this->trackBusinessLayer->find($trackId, $this->userId)->getArtistId();
		} else {
			throw new SubsonicException("Id $id has a type not supported on getSimilarSongs", 0);
		}

		$artists = $this->lastfmService->getSimilarArtists($artistId, $this->userId);
		$artists[] = $this->artistBusinessLayer->find($artistId, $this->userId);

		// Get all songs by the found artists
		$songs = [];
		foreach ($artists as $artist) {
			$songs = \array_merge($songs, $this->trackBusinessLayer->findAllByArtist($artist->getId(), $this->userId));
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

		// The searches support '*' as a wildcard. Convert those to the SQL wildcard '%' as that's what the business layer searches support.
		$query = \str_replace('*', '%', $query);

		return [
			'artists' => $this->artistBusinessLayer->findAllByName($query, $this->userId, MatchMode::Substring, $artistCount, $artistOffset),
			'albums' => $this->albumBusinessLayer->findAllByNameRecursive($query, $this->userId, $albumCount, $albumOffset),
			'tracks' => $this->trackBusinessLayer->findAllByNameRecursive($query, $this->userId, $songCount, $songOffset)
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
			return $this->trackBusinessLayer->findAllByGenre($genre->getId(), $this->userId, $limit, $offset);
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
			return $this->albumBusinessLayer->findAllByGenre($genre->getId(), $this->userId, $limit, $offset);
		} else {
			return [];
		}
	}

	private function findGenreByName($name) {
		$genreArr = $this->genreBusinessLayer->findAllByName($name, $this->userId);
		if (\count($genreArr) == 0 && $name == Genre::unknownNameString($this->l10n)) {
			$genreArr = $this->genreBusinessLayer->findAllByName('', $this->userId);
		}
		return \count($genreArr) ? $genreArr[0] : null;
	}

	private function artistImageUrl(string $id) : string {
		$par = $this->request->getParams();
		return $this->urlGenerator->linkToRouteAbsolute('music.subsonic.handleRequest', ['method' => 'getCoverArt'])
			. "?u={$par['u']}&p={$par['p']}&v={$par['v']}&c={$par['c']}&id=$id&size=" . CoverHelper::DO_NOT_CROP_OR_SCALE;
		// Note: Using DO_NOT_CROP_OR_SCALE (-1) as size is our proprietary extension and not part of the Subsonic API
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
