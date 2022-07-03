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
	public static function loadFromUrl(string $url) : array {
		if (!Util::startsWith($url, 'http://', /*ignoreCase=*/true)
				&& !Util::startsWith($url, 'https://', /*ignoreCase=*/true)) {
			$content = false;
			$status_code = 0;
			$message = 'URL scheme must be HTTP or HTTPS';
		} else {
			$opts = [
				'http' => [
					'header' => self::userAgentHeader(),	// some servers don't allow requests without a user agent header
					'ignore_errors' => true 				// don't emit warnings for bad/unavailable URL, we handle errors manually
				]
			];
			$context = \stream_context_create($opts);
			$content = \file_get_contents($url, false, $context);

			// It's some PHP magic that calling file_get_contents creates and populates also a local
			// variable array $http_response_header, provided that the server could be reached.
			// PhpStan thinks that this varialbe would always exist, and doesn't like the isset.
			// @phpstan-ignore-next-line
			if (isset($http_response_header)) {
				list($version, $status_code, $message) = \explode(' ', $http_response_header[0], 3);
				$status_code = (int)$status_code;
			} else {
				$status_code = 0;
				$message = 'The requested URL did not respond';
			}
		}

		return \compact('content', 'status_code', 'message');
	}

	public static function userAgentHeader() : string {
		// Note: the following is deprecated since NC14 but the replacement
		// \OCP\App\IAppManager::getAppVersion is not available before NC14.
		$appVersion = \OCP\App::getAppVersion('music');

		return 'User-Agent: OCMusic/' . $appVersion;
	}
}
