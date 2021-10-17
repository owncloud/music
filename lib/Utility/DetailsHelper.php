<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
 */

namespace OCA\Music\Utility;

use OCP\Files\Folder;

use OCA\Music\AppFramework\Core\Logger;

class DetailsHelper {
	private $extractor;
	private $logger;

	public function __construct(
			Extractor $extractor,
			Logger $logger) {
		$this->extractor = $extractor;
		$this->logger = $logger;
	}

	/**
	 * @param integer $fileId
	 * @param Folder $userFolder
	 * $return array|null
	 */
	public function getDetails(int $fileId, Folder $userFolder) : ?array {
		$file = $userFolder->getById($fileId)[0] ?? null;
		if ($file !== null) {
			$data = $this->extractor->extract($file);
			$audio = $data['audio'] ?: [];
			$comments = $data['comments'] ?: [];

			// remove intermediate arrays
			$comments = self::flattenComments($comments);

			// cleanup strings from invalid characters
			\array_walk($audio, ['self', 'sanitizeString']);
			\array_walk($comments, ['self', 'sanitizeString']);

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
					$result['tags']['unsynchronised_lyric'],
					$result['tags']['unsynced lyrics']
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
	 * @param integer $fileId
	 * @param Folder $userFolder
	 * $return string|null
	 */
	public function getLyrics(int $fileId, Folder $userFolder) {
		$lyrics = null;
		$fileNodes = $userFolder->getById($fileId);
		if (\count($fileNodes) > 0) {
			$data = $this->extractor->extract($fileNodes[0]);
			$lyrics = ExtractorGetID3::getFirstOfTags($data, ['unsynchronised_lyric', 'unsynced lyrics']);
			self::sanitizeString($lyrics);

			if ($lyrics === null) {
				// no unsynchronized lyrics, try to get and convert the potentially syncronized lyrics
				$lyrics = ExtractorGetID3::getTag($data, 'LYRICS');
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
	 * Read lyrics-related tags, and build a result array containing potentially
	 * both time-synced and unsynced lyrics. If no lyrics tags are found, the result will
	 * be null. In case the result is non-null, there is always at least the key 'unsynced'
	 * in the result which will hold a string representing the lyrics with no timestamps.
	 * If found and successfully parsed, there will be also another key 'synced', which will
	 * hold the time-synced lyrics. These are presented as an array of arrays of form
	 * ['time' => int (ms), 'text' => string].
	 *
	 * @param array $tags
	 * @return array|null
	 */
	private static function transformLyrics(array $tags) : ?array {
		$lyrics = $tags['LYRICS'] ?? null; // may be synced or unsynced
		$syncedLyrics = LyricsParser::parseSyncedLyrics($lyrics);
		$unsyncedLyrics = $tags['unsynchronised_lyric']
						?? $tags['unsynced lyrics']
						?? LyricsParser::syncedToUnsynced($syncedLyrics)
						?? $lyrics;

		if ($unsyncedLyrics !== null) {
			$result = ['unsynced' => $unsyncedLyrics];

			if ($syncedLyrics !== null) {
				$result['synced'] = \array_map(function ($timestamp, $text) {
					return ['time' => \max(0, $timestamp), 'text' => $text];
				}, \array_keys($syncedLyrics), $syncedLyrics);
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
	 * @param string|array $item
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
	 * @param array $array
	 * @return array
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
