<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016 - 2025
 */

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\MatchMode;
use OCA\Music\Db\Playlist;
use OCA\Music\Db\PlaylistMapper;
use OCA\Music\Db\SortBy;
use OCA\Music\Db\Track;
use OCA\Music\Db\TrackMapper;
use OCA\Music\Utility\ArrayUtil;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\StringUtil;

/**
 * Base class functions with actually used inherited types to help IDE and Scrutinizer:
 * @method Playlist find(int $playlistId, string $userId)
 * @method Playlist[] findAll(string $userId, int $sortBy=SortBy::Name, int $limit=null, int $offset=null)
 * @method Playlist[] findAllByName(string $name, string $userId, int $matchMode=MatchMode::Exact, int $limit=null, int $offset=null)
 * @property PlaylistMapper $mapper
 * @phpstan-extends BusinessLayer<Playlist>
 */
class PlaylistBusinessLayer extends BusinessLayer {
	private TrackMapper $trackMapper;
	private Logger $logger;

	public function __construct(
			PlaylistMapper $playlistMapper,
			TrackMapper $trackMapper,
			Logger $logger) {
		parent::__construct($playlistMapper);
		$this->trackMapper = $trackMapper;
		$this->logger = $logger;
	}

	public function setTracks(array $trackIds, int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function addTracks(array $trackIds, int $playlistId, string $userId, ?int $insertIndex = null) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$allTrackIds = $playlist->getTrackIdsAsArray();
		if ($insertIndex === null) {
			$allTrackIds = \array_merge($allTrackIds, $trackIds);
		} else {
			\array_splice($allTrackIds, $insertIndex, 0, $trackIds);
		}
		$playlist->setTrackIdsFromArray($allTrackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function removeTracks(array $trackIndices, int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$trackIds = \array_diff_key($trackIds, \array_flip($trackIndices));
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function removeAllTracks(int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setTrackIdsFromArray([]);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function moveTrack(int $fromIndex, int $toIndex, int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$movedTrack = \array_splice($trackIds, $fromIndex, 1);
		\array_splice($trackIds, $toIndex, 0, $movedTrack);
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function create(string $name, string $userId) : Playlist {
		$playlist = new Playlist();
		$playlist->setName(StringUtil::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$playlist->setUserId($userId);

		return $this->mapper->insert($playlist);
	}

	public function rename(string $name, int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setName(StringUtil::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function setComment(string $comment, int $playlistId, string $userId) : Playlist {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setComment(StringUtil::truncate($comment, 256)); // some DB setups can't truncate automatically to column max size
		$this->mapper->update($playlist);
		return $playlist;
	}

	/**
	 * removes tracks from all available playlists
	 * @param int[] $trackIds array of all track IDs to remove
	 */
	public function removeTracksFromAllLists(array $trackIds) : void {
		foreach ($trackIds as $trackId) {
			$affectedLists = $this->mapper->findListsContainingTrack($trackId);

			foreach ($affectedLists as $playlist) {
				$prevTrackIds = $playlist->getTrackIdsAsArray();
				$playlist->setTrackIdsFromArray(\array_diff($prevTrackIds, [$trackId]));
				$this->mapper->update($playlist);
			}
		}
	}

	/**
	 * get list of Track objects belonging to a given playlist
	 * @return Track[]
	 */
	public function getPlaylistTracks(int $playlistId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();

		$trackIds = \array_slice($trackIds, \intval($offset), $limit);

		$tracks = empty($trackIds) ? [] : $this->trackMapper->findById($trackIds, $userId);

		// The $tracks contains the songs in unspecified order and with no duplicates.
		// Build a new array where the tracks are in the same order as in $trackIds.
		$tracksById = ArrayUtil::createIdLookupTable($tracks);

		$playlistTracks = [];
		foreach ($trackIds as $index => $trackId) {
			$track = $tracksById[$trackId] ?? null;
			if ($track !== null) {
				// in case the same track comes up again in the list, clone the track object
				// to have different numbers on the instances
				if ($track->getNumberOnPlaylist() !== null) {
					$track = clone $track;
				}
				$track->setNumberOnPlaylist(\intval($offset) + $index + 1);
			} else {
				$this->logger->log("Invalid track ID $trackId found on playlist $playlistId", 'debug');
				$track = Track::emptyInstance();
				$track->setId($trackId);
			}
			$playlistTracks[] = $track;
		}

		return $playlistTracks;
	}

	/**
	 * get the total duration of all the tracks on a playlist
	 *
	 * @return int duration in seconds
	 */
	public function getDuration(int $playlistId, string $userId) : int {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$durations = $this->trackMapper->getDurations($trackIds);

		// We can't simply sum up the values of $durations array, because the playlist may
		// contain duplicate entries, and those are not reflected in $durations.
		// Be also prepared to invalid playlist entries where corresponding track length does not exist.
		$sum = 0;
		foreach ($trackIds as $trackId) {
			$sum += $durations[$trackId] ?? 0;
		}

		return $sum;
	}

	/**
	 * Generate and return a playlist matching the given criteria. The playlist is not persisted.
	 *
	 * @param string|null $history One of: 'recently-played', 'not-recently-played', 'often-played', 'rarely-played', 'recently-added', 'not-recently-added'
	 * @param bool $historyStrict In the "strict" mode, there's no element of randomness when applying the history filter and e.g.
	 *             'recently-played' meas "The most recently played" instead of "Among the most recently played"
	 * @param int[] $genres Array of genre IDs
	 * @param int[] $artists Array of artist IDs
	 * @param int|null $fromYear Earliest release year to include
	 * @param int|null $toYear Latest release year to include
	 * @param string|null $favorite One of: 'track', 'album', 'artists', 'track_album_artist', null
	 * @param int $size Size of the playlist to generate, provided that there are enough matching tracks
	 * @param string $userId the name of the user
	 */
	public function generate(
			?string $history, bool $historyStrict, array $genres, array $artists,
			?int $fromYear, ?int $toYear, ?string $favorite, int $size, string $userId) : Playlist {

		$now = new \DateTime();
		$nowStr = $now->format(PlaylistMapper::SQL_DATE_FORMAT);

		$playlist = new Playlist();
		$playlist->setCreated($nowStr);
		$playlist->setUpdated($nowStr);
		$playlist->setName('Generated ' . $nowStr);
		$playlist->setUserId($userId);

		list('sortBy' => $sortBy, 'invert' => $invertSort) = self::sortRulesForHistory($history);
		$limit = ($sortBy === SortBy::None) ? null : ($historyStrict ? $size : $size * 4);

		$favoriteMask = self::favoriteMask($favorite);

		$tracks = $this->trackMapper->findAllByCriteria($genres, $artists, $fromYear, $toYear, $favoriteMask, $sortBy, $invertSort, $userId, $limit);

		if ($sortBy !== SortBy::None && !$historyStrict) {
			// When generating by non-strict history, use a pool of tracks at maximum twice the size of final list.
			// However, don't use more than half of the matching tracks unless that is required to satisfy the required list size.
			$poolSize = max($size, \count($tracks) / 2);
			$tracks = \array_slice($tracks, 0, $poolSize);
		}

		// Pick the final random set of tracks
		$tracks = Random::pickItems($tracks, $size);

		$playlist->setTrackIdsFromArray(ArrayUtil::extractIds($tracks));

		return $playlist;
	}

	private static function sortRulesForHistory(?string $history) : array {
		switch ($history) {
			case 'recently-played':
				return ['sortBy' => SortBy::LastPlayed, 'invert' => false];
			case 'not-recently-played':
				return ['sortBy' => SortBy::LastPlayed, 'invert' => true];
			case 'often-played':
				return ['sortBy' => SortBy::PlayCount, 'invert' => false];
			case 'rarely-played':
				return ['sortBy' => SortBy::PlayCount, 'invert' => true];
			case 'recently-added':
				return ['sortBy' => SortBy::Newest, 'invert' => false];
			case 'not-recently-added':
				return ['sortBy' => SortBy::Newest, 'invert' => true];
			default:
				return ['sortBy' => SortBy::None, 'invert' => false];
		}
	}

	private static function favoriteMask(?string $mode) : ?int {
		switch ($mode) {
			case 'track':				return TrackMapper::FAVORITE_TRACK;
			case 'album':				return TrackMapper::FAVORITE_ALBUM;
			case 'artist':				return TrackMapper::FAVORITE_ARTIST;
			case 'track_album_artist':	return TrackMapper::FAVORITE_TRACK | TrackMapper::FAVORITE_ALBUM | TrackMapper::FAVORITE_ARTIST;
			default:					return null;
		}
	}
}
