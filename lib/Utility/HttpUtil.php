<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022, 2023
 */

namespace OCA\Music\Utility;

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
				list($version, $status_code, $message) = \explode(' ', $http_response_header[0], 3);
				$status_code = (int)$status_code;
				$content_type = self::findHeader($http_response_header, 'Content-Type');
			} else {
				$message = 'The requested URL did not respond';
			}
		}

		return \compact('content', 'status_code', 'message', 'content_type');
	}

	/**
	 * @return resource
	 */
	public static function createContext(?int $timeout_s=null, array $extraHeaders = []) {
		$opts = self::contextOptions($extraHeaders);
		if ($timeout_s !== null) {
			$opts['http']['timeout'] = $timeout_s;
		}
		return \stream_context_create($opts);
	}

	/**
	 * @param resource $context
	 * @return ?array The headers from the URL, after any redirections. The header names will be array keys.
	 * 					In addition to the named headers from the server, the key 'status_code' will contain
	 * 					the status code number of the HTTP request (like 200, 302, 404).
	 */
	public static function getUrlHeaders(string $url, $context) : ?array {
		$result = null;
		if (self::isUrlSchemeOneOf($url, self::ALLOWED_SCHEMES)) {
			// the type of the second parameter of get_header has changed in PHP 8.0
			$associative = \version_compare(\phpversion(), '8.0', '<') ? 1 : true;
			$result = @\get_headers($url, $associative, $context);

			if ($result === false) {
				$result = null;
			} else {
				// Do some post-processing on the headers
				foreach ($result as $key => $value) {
					// Some of the headers got may be array-valued after a redirection or several, containing value
					// from each redirected jump. In such cases, preserve only the last value.
					if (\is_array($value)) {
						$result[$key] = \end($value);
					}

					// The status header like "HTTP/1.1 200 OK" can found from the index 0. If there were any redirects,
					// then the statuses after the redirections can be found from indices 1, 2, 3, ... That is, the status
					// after the last redirection can be found from the highest numerical index. We are interested about the
					// status code after the last redirection.
					if (\is_int($key)) {
						$result['status_code'] = (int)(\explode(' ', $value, 3)[1] ?? 500);
						unset($result[$key]);
					}
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
				'max_redirects' => 5
			]
		];

		foreach ($extraHeaders as $key => $value) {
			$opts['http']['header'] .= "\r\n$key: $value";
		}

		return $opts;
	}

	private static function findHeader(array $headers, string $headerKey) : ?string {
		// According to RFC 2616, HTTP headers are case-insensitive
		$headerKey = \mb_strtolower($headerKey);
		foreach ($headers as $header) {
			$header = \mb_strtolower($header); // note that this converts also the header value to lower case
			$find = \strstr($header, $headerKey . ':');
			if ($find !== false) {
				return \trim(\substr($find, \strlen($headerKey)+1));
			}
		}
		return null;
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
}
