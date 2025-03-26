<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022 - 2025
 */

namespace OCA\Music\Utility;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/**
 * Static utility functions to work with HTTP requests
 */
class HttpUtil {

	private const ALLOWED_SCHEMES = ['http', 'https', 'feed', 'podcast', 'pcast', 'podcasts', 'itms-pcast', 'itms-pcasts', 'itms-podcast', 'itms-podcasts'];

	/**
	 * Use HTTP GET to load the requested URL
	 * @return array with three keys: ['content' => string|false, 'status_code' => int, 'message' => string, 'content_type' => string]
	 */
	public static function loadFromUrl(string $url, ?int $maxLength=null, ?int $timeout_s=null) : array {
		$status_code = 0;
		$content_type = null;

		if (!self::isUrlSchemeOneOf($url, self::ALLOWED_SCHEMES)) {
			$content = false;
			$message = 'URL scheme must be one of ' . \json_encode(self::ALLOWED_SCHEMES);
		} else {
			$context = self::createContext($timeout_s);

			// The length parameter of file_get_contents isn't nullable prior to PHP8.0
			if ($maxLength === null) {
				$content = @\file_get_contents($url, false, $context);
			} else {
				$content = @\file_get_contents($url, false, $context, 0, $maxLength);
			}

			// It's some PHP magic that calling file_get_contents creates and populates also a local
			// variable array $http_response_header, provided that the server could be reached.
			if (!empty($http_response_header)) {
				$parsedHeaders = self::parseHeaders($http_response_header, true);
				$status_code = $parsedHeaders['status_code'];
				$message = $parsedHeaders['status_msg'];
				$content_type = $parsedHeaders['content-type'] ?? null;
			} else {
				$message = 'The requested URL did not respond';
			}
		}

		return \compact('content', 'status_code', 'message', 'content_type');
	}

	/**
	 * @return resource
	 */
	public static function createContext(?int $timeout_s = null, array $extraHeaders = [], ?int $maxRedirects = null) {
		$opts = self::contextOptions($extraHeaders);
		if ($timeout_s !== null) {
			$opts['http']['timeout'] = $timeout_s;
		}
		if ($maxRedirects !== null) {
			$opts['http']['max_redirects'] = $maxRedirects;
		}
		return \stream_context_create($opts);
	}

	/**
	 * @param resource $context
	 * @param bool $convertKeysToLower When true, the header names used as keys of the result array are
	 * 				converted to lower case. According to RFC 2616, HTTP headers are case-insensitive.
	 * @return array The headers from the URL, after any redirections. The header names will be array keys.
	 * 					In addition to the named headers from the server, the key 'status_code' will contain
	 * 					the status code number of the HTTP request (like 200, 302, 404) and 'status_msg'
	 * 					the textual status following the code (like 'OK' or 'Not Found').
	 */
	public static function getUrlHeaders(string $url, $context, bool $convertKeysToLower=false) : array {
		$result = null;
		if (self::isUrlSchemeOneOf($url, self::ALLOWED_SCHEMES)) {
			// Don't use the built-in associative mode of get_headers because it mixes up the headers from the redirection
			// responses with those of the last response after all the redirections, making it impossible to know,
			// what is the source of each header. Hence, we roll out our own parsing logic which discards all the
			// headers from the intermediate redirection responses.

			// the type of the second parameter of get_header has changed in PHP 8.0
			$associative = \version_compare(\phpversion(), '8.0', '<') ? 0 : false;
			$rawHeaders = @\get_headers($url, /** @scrutinizer ignore-type */ $associative, $context);

			if ($rawHeaders !== false) {
				$result = self::parseHeaders($rawHeaders, $convertKeysToLower);
			} else {
				$result = ['status_code' => Http::STATUS_SERVICE_UNAVAILABLE, 'status_msg' => 'Error connecting the URL', 'content-length' => '0'];
			}
		} else {
			$result = ['status_code' => Http::STATUS_FORBIDDEN, 'status_msg' => 'URL scheme not allowed', 'content-length' => '0'];
		}
		return $result;
	}

	private static function parseHeaders(array $rawHeaders, bool $convertKeysToLower) : array {
		$result = [];

		foreach ($rawHeaders as $row) {
			// The response usually starts with a header like "HTTP/1.1 200 OK". However, some shoutcast streams
			// may instead use "ICY 200 OK".
			if (Util::startsWith($row, 'HTTP/', /*ignoreCase=*/true) || Util::startsWith($row, 'ICY ', /*ignoreCase=*/true)) {
				// Start of new response. If we have already parsed some headers, then those are from some
				// intermediate redirect response and those should be discarded.
				$parts = \explode(' ', $row, 3);
				if (\count($parts) == 3) {
					list(, $status_code, $status_msg) = $parts;
				} else {
					$status_code = Http::STATUS_INTERNAL_SERVER_ERROR;
					$status_msg = 'Bad response status header';
				}
				$result = ['status_code' => (int)$status_code, 'status_msg' => $status_msg];
			} else {
				// All other lines besides the initial status line should have the format "key: value"
				$parts = \explode(':', $row, 2);
				if (\count($parts) == 2) {
					list($key, $value) = $parts;
					if ($convertKeysToLower) {
						$key = \mb_strtolower($key);
					}
					$result[\trim($key)] = \trim($value);
				}
			}
		}

		return $result;
	}

	public static function userAgentHeader() : string {
		return 'User-Agent: OCMusic/' . AppInfo::getVersion();
	}

	private static function contextOptions(array $extraHeaders = []) : array {
		$opts = [
			'http' => [
				'header' => self::userAgentHeader(),	// some servers don't allow requests without a user agent header
				'ignore_errors' => true,				// don't emit warnings for bad/unavailable URL, we handle errors manually
				'max_redirects' => 20
			]
		];

		foreach ($extraHeaders as $key => $value) {
			$opts['http']['header'] .= "\r\n$key: $value";
		}

		return $opts;
	}

	private static function isUrlSchemeOneOf(string $url, array $schemes) : bool {
		$url = \mb_strtolower($url);

		foreach ($schemes as $scheme) {
			if (Util::startsWith($url, $scheme . '://')) {
				return true;
			}
		}

		return false;
	}

	public static function setClientCachingDays(Response &$httpResponse, int $days) : void {
		$httpResponse->cacheFor($days * 24 * 60 * 60);
		$httpResponse->addHeader('Pragma', 'cache');
	}
}
