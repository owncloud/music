<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\Utility;

/**
 * Static utility functions to work with strings
 */
class StringUtil {

	/**
	 * Truncate the given string to maximum number of bytes, appending ellipsis character
	 * (or other given marker) if the truncation happened. Note that for multi-byte encoding (like utf8),
	 * the number of bytes may not be the same as the number of characters.
	 * Also null argument may be safely passed and it remains unaltered.
	 */
	public static function truncate(?string $string, int $maxBytes, string $trimMarker="\u{2026}") : ?string {
		if ($string === null) {
			return null;
		} else if (\strlen($string) > $maxBytes) {
			$string = \mb_strcut($string, 0, $maxBytes - \strlen($trimMarker));
			return $string . $trimMarker;
		} else {
			return $string;
		}
	}

	/**
	 * Test if given string starts with another given string
	 */
	public static function startsWith(string $string, string $potentialStart, bool $ignoreCase=false) : bool {
		$actualStart = \substr($string, 0, \strlen($potentialStart));
		if ($ignoreCase) {
			$actualStart = \mb_strtolower($actualStart);
			$potentialStart = \mb_strtolower($potentialStart);
		}
		return $actualStart === $potentialStart;
	}

	/**
	 * Test if given string ends with another given string
	 */
	public static function endsWith(string $string, string $potentialEnd, bool $ignoreCase=false) : bool {
		$actualEnd = \substr($string, -\strlen($potentialEnd));
		if ($ignoreCase) {
			$actualEnd = \mb_strtolower($actualEnd);
			$potentialEnd = \mb_strtolower($potentialEnd);
		}
		return $actualEnd === $potentialEnd;
	}

	/**
	 * Multi-byte safe case-insensitive string comparison
	 * @return int negative value if $a is less than $b, positive value if $a is greater than $b, and 0 if they are equal.
	 */
	public static function caselessCompare(?string $a, ?string $b) : int {
		return \strcmp(\mb_strtolower($a ?? ''), \mb_strtolower($b ?? ''));
	}

	public static function caselessEqual(?string $a, ?string $b) : bool {
		return (self::caselessCompare($a, $b) === 0);
	}

	/** 
	 * Convert snake case string (like_this) to camel case (likeThis).
	 */
	public static function snakeToCamelCase(string $input): string {
		return \lcfirst(\str_replace('_', '', \ucwords($input, '_')));
	}

	/**
	 * Test if $item is a string and not empty or only consisting of whitespace
	 */
	public static function isNonEmptyString(/*mixed*/ $item) : bool {
		return \is_string($item) && \trim($item) !== '';
	}

	/**
	 * Split given string to a prefix and a basename (=the remaining part after the prefix), considering the possible
	 * prefixes given as an array. If none of the prefixes match, the returned basename will be the original string
	 * and the prefix will be null.
	 * @param string[] $potentialPrefixes
	 */
	public static function splitPrefixAndBasename(?string $name, array $potentialPrefixes) : array {
		$parts = ['prefix' => null, 'basename' => $name];

		if ($name !== null) {
			foreach ($potentialPrefixes as $prefix) {
				if (self::startsWith($name, $prefix . ' ', /*ignoreCase=*/true)) {
					$parts['prefix'] = $prefix;
					$parts['basename'] = \substr($name, \strlen($prefix) + 1);
					break;
				}
			}
		}

		return $parts;
	}

}