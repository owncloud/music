<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022
 */

namespace OCA\Music\Utility;

/**
 * Static utility functions to work with HTTP requests
 */
class HttpUtil {

	/**
	 * Use HTTP GET to load the requested URL
	 * @return array with three keys: ['content' => string|false, 'status_code' => int, 'message' => string]
	 */
	public static function loadFromUrl(string $url, ?int $maxLength=null, ?int $timeout_s=null) : array {
		$status_code = 0;
		$content_type = null;

		if (!Util::startsWith($url, 'http://', /*ignoreCase=*/true)
				&& !Util::startsWith($url, 'https://', /*ignoreCase=*/true)) {
			$content = false;
			$message = 'URL scheme must be HTTP or HTTPS';
		} else {
			$opts = [
				'http' => [
					'header' => self::userAgentHeader(),	// some servers don't allow requests without a user agent header
					'ignore_errors' => true,				// don't emit warnings for bad/unavailable URL, we handle errors manually
					'max_redirects' => 5
				]
			];
			if ($timeout_s !== null) {
				$opts['http']['timeout'] = $timeout_s;
			}
			$context = \stream_context_create($opts);

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

	public static function userAgentHeader() : string {
		return 'User-Agent: OCMusic/' . AppInfo::getVersion();
	}

	private static function findHeader(array $headers, string $headerKey) : ?string {
		foreach ($headers as $header) {
			$find = \strstr($header, $headerKey . ':');
			if ($find !== false) {
				return \trim(\substr($find, \strlen($headerKey)+1));
			}
		}
		return null;
	}
}
