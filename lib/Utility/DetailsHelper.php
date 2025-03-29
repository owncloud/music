<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Utility;

use OCP\Files\File;
use OCP\Files\Folder;

use OCA\Music\AppFramework\Core\Logger;

class DetailsHelper {
	private Extractor $extractor;
	private Logger $logger;

	public function __construct(
			Extractor $extractor,
			Logger $logger) {
		$this->extractor = $extractor;
		$this->logger = $logger;
	}

	public function getDetails(int $fileId, Folder $userFolder) : ?array {
		$file = $userFolder->getById($fileId)[0] ?? null;
		if ($file instanceof File) {
			$data = $this->extractor->extract($file);
			$audio = $data['audio'] ?? [];
			$comments = $data['comments'] ?? [];

			// remove intermediate arrays
			$comments = self::flattenComments($comments);

			// cleanup strings from invalid characters
			\array_walk($audio, [$this, 'sanitizeString']);
			\array_walk($comments, [$this, 'sanitizeString']);

			$result = [
				'fileinfo' => $audio,
				'tags' => $comments
			];

			// binary data has to be encoded
			if (\array_key_exists('picture', $result['tags'])) {
				$result['tags']['picture'] = self::encodePictureTag($result['tags']['picture']);
			}

			// 'streams' contains duplicate data
			unset($result['fileinfo']['streams']);

			// one track number is enough
			if (\array_key_exists('track', $result['tags'])
				&& \array_key_exists('track_number', $result['tags'])) {
				unset($result['tags']['track']);
			}

			// special handling for lyrics tags
			$lyricsNode = self::transformLyrics($result['tags']);
			if ($lyricsNode !== null) {
				$result['lyrics'] = $lyricsNode;
				unset(
					$result['tags']['LYRICS'],
					$result['tags']['lyrics'],
					$result['tags']['unsynchronised_lyric'],
					$result['tags']['unsynced lyrics'],
					$result['tags']['unsynced_lyrics'],
					$result['tags']['unsyncedlyrics']
				);
			}

			// add track length
			$result['length'] = $data['playtime_seconds'] ?? null;

			// add file path
			$result['path'] = $userFolder->getRelativePath($file->getPath());

			return $result;
		}
		return null;
	}

	/**
	 * Check if a file has embedded lyrics without parsing them
	 */
	public function hasLyrics(int $fileId, Folder $userFolder) : bool {
		$fileNode = $userFolder->getById($fileId)[0] ?? null;
		if ($fileNode instanceof File) {
			$data = $this->extractor->extract($fileNode);
			$lyrics = ExtractorGetID3::getFirstOfTags($data, ['unsynchronised_lyric', 'unsynced lyrics', 'unsynced_lyrics', 'unsyncedlyrics']);
			return ($lyrics !== null);
		}
		return false;
	}

	/**
	 * Get lyrics from the file metadata in plain-text format. If there's no unsynchronised lyrics available
	 * but there is synchronised lyrics, then the plain-text format is converted from the synchronised lyrics.
	 */
	public function getLyricsAsPlainText(int $fileId, Folder $userFolder) : ?string {
		$lyrics = null;
		$fileNode = $userFolder->getById($fileId)[0] ?? null;
		if ($fileNode instanceof File) {
			$data = $this->extractor->extract($fileNode);
			$lyrics = ExtractorGetID3::getFirstOfTags($data, ['unsynchronised_lyric', 'unsynced lyrics', 'unsynced_lyrics', 'unsyncedlyrics']);
			self::sanitizeString($lyrics);

			if ($lyrics === null) {
				// no unsynchronized lyrics, try to get and convert the potentially synchronized lyrics
				$lyrics = ExtractorGetID3::getFirstOfTags($data, ['LYRICS', 'lyrics']);
				self::sanitizeString($lyrics);
				$parsed = LyricsParser::parseSyncedLyrics($lyrics);
				if ($parsed) {
					// the lyrics were indeed time-synced, convert the parsed array to a plain string
					$lyrics = LyricsParser::syncedToUnsynced($parsed);
				}
			}
		}
		return $lyrics;
	}

	/**
	 * Get all lyrics from the file metadata, both synced and unsynced. For both lyrics types, a single instance
	 * of lyrics contains an array of lyrics lines. In case of synced lyrics, the key of this array is the offset
	 * in milliseconds.
	 * @return array of items like ['synced' => boolean, 'lines' => array]
	 */
	public function getLyricsAsStructured(int $fileId, Folder $userFolder) : array {
		$result = [];
		$fileNode = $userFolder->getById($fileId)[0] ?? null;
		if ($fileNode instanceof File) {
			$data = $this->extractor->extract($fileNode);
			$lyricsTags = ExtractorGetID3::getTags($data, ['LYRICS', 'lyrics', 'unsynchronised_lyric', 'unsynced lyrics', 'unsynced_lyrics', 'unsyncedlyrics']);

			foreach ($lyricsTags as $tagKey => $tagValue) {
				self::sanitizeString($tagValue);

				// Never try to parse synced lyrics from the "unsync*" tags. The "lyrics" tag, on the other hand,
				// may contain either synced or unsynced lyrics and a parse attempt is needed to find out.
				$mayBeSynced = !Util::startsWith($tagKey, 'unsync');
				$syncedLyrics = $mayBeSynced ? LyricsParser::parseSyncedLyrics($tagValue) : null;

				if ($syncedLyrics) {
					$result[] = [
						'synced' => true,
						'lines' => $syncedLyrics
					];
				} else {
					$result[] = [
						'synced' => false,
						'lines' => \explode("\n", $tagValue)
					];
				}
			}
		}
		return $result;
	}

	/**
	 * Read lyrics-related tags, and build a result array containing potentially
	 * both time-synced and unsynced lyrics. If no lyrics tags are found, the result will
	 * be null. In case the result is non-null, there is always at least the key 'unsynced'
	 * in the result which will hold a string representing the lyrics with no timestamps.
	 * If found and successfully parsed, there will be also another key 'synced', which will
	 * hold the time-synced lyrics. These are presented as an array of arrays of form
	 * ['time' => int (ms), 'text' => string].
	 */
	private static function transformLyrics(array $tags) : ?array {
		$lyrics = $tags['LYRICS'] ?? $tags['lyrics'] ?? null; // may be synced or unsynced
		$syncedLyrics = LyricsParser::parseSyncedLyrics($lyrics);
		$unsyncedLyrics = $tags['unsynchronised_lyric']
						?? $tags['unsynced lyrics']
						?? $tags['unsynced_lyrics']
						?? $tags['unsyncedlyrics']
						?? LyricsParser::syncedToUnsynced($syncedLyrics)
						?? $lyrics;

		if ($unsyncedLyrics !== null) {
			$result = ['unsynced' => $unsyncedLyrics];

			if ($syncedLyrics !== null) {
				$result['synced'] = \array_map(fn($timestamp, $text) => [
					'time' => \max(0, $timestamp), 'text' => $text
				], \array_keys($syncedLyrics), $syncedLyrics);
			}
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Base64 encode the picture binary data and wrap it so that it can be directly used as
	 * src of an HTML img element.
	 */
	private static function encodePictureTag(array $pic) : ?string {
		if ($pic['data']) {
			return 'data:' . $pic['image_mime'] . ';base64,' . \base64_encode($pic['data']);
		} else {
			return null;
		}
	}

	/**
	 * Remove potentially invalid characters from the string and normalize the line breaks to LF.
	 * @param mixed $item
	 */
	private static function sanitizeString(&$item) : void {
		if (\is_string($item)) {
			\mb_substitute_character(0xFFFD); // Use the Unicode REPLACEMENT CHARACTER (U+FFFD)
			$item = \mb_convert_encoding($item, 'UTF-8', 'UTF-8');
			// The tags could contain line breaks in formats LF, CRLF, or CR, but we want the output
			// to always use the LF style. Note that the order of the next two lines is important!
			$item = \str_replace("\r\n", "\n", $item);
			$item = \str_replace("\r", "\n", $item);
		}
	}

	/**
	 * In the 'comments' field from the extractor, the value for each key is a 1-element
	 * array containing the actual tag value. Remove these intermediate arrays.
	 */
	private static function flattenComments(array $array) : array {
		// key 'text' is an exception, its value is an associative array
		$textArray = null;

		foreach ($array as $key => $value) {
			if ($key === 'text') {
				$textArray = $value;
			} else {
				$array[$key] = $value[0];
			}
		}

		if (!empty($textArray)) {
			$array = \array_merge($array, $textArray);
			unset($array['text']);
		}

		return $array;
	}
}
