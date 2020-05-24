<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Utility;


class LyricsParser {

	/**
	 * Parse timestamped lyrics from the given string, and return the parsed data.
	 * Return false if the string does not appear to be timestamped lyric in the LRC format.
	 *
	 * @param string $data
	 * @return array|false The keys of the array are timestamps and values are corresponding
	 *                     lines of lyrics.
	 */
	public static function parseSyncedLyrics($data) {
		$parsedLyrics = [];

		$fp = \fopen("php://temp", 'r+');
		\fputs($fp, $data);
		\rewind($fp);
		while ($line = \fgets($fp)) {
			$lineParseResult = self::parseTimestampedLrcLine($line);
			$parsedLyrics = \array_merge($parsedLyrics, $lineParseResult);
		}
		\fclose($fp);

		// sort the parsed lyric lines according the timestamps (which are keys of the array)
		\ksort($parsedLyrics);

		return \count($parsedLyrics) > 0 ? $parsedLyrics : false;
	}

	/**
	 * Parse a single line of LRC formatted data. The result is array where keys are
	 * timestamps and values are corresponding lyrics. A single line of LRC may span out to
	 * a) 0 actual timestamp lines, if the line is empty, or contains just metadata, or contains no tags
	 * b) 1 actual timestamp line (the "normal" case)
	 * c) several actual timestamp lines, in case the line contains several timestamps,
	 *    meaning that the same line of text is repeated multiple times during the song
	 * 
	 * @param string $line
	 * @return array
	 */
	private static function parseTimestampedLrcLine($line) {
		$result = [];
		$line = \trim($line);

		$matches = [];
		if (\preg_match('/(\[.+\])(.*)/', $line, $matches)) {
			// 1st group captures tag(s), 2nd group anything after the tag(s).
			$tags = $matches[1];
			$text = $matches[2];

			// Extract timestamp tags and discard the metadata tags.
			$timestampMatches = [];
			if (\preg_match_all('/\[(\d\d:\d\d\.\d\d)\]/', $tags, $timestampMatches)) {
				// some timestamp(s) were found
				$timestamps = $timestampMatches[1];

				// add the line text to the result set on each found timestamp
				foreach ($timestamps as $timestamp) {
					$result[$timestamp] = $text;
				}
			}
		}

		return $result;
	}
}
