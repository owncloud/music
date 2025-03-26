<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024, 2025
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;

use OCA\Music\Utility\HttpUtil;

/**
 * Response which relays a radio stream or similar from an original source URL
 */
class RelayStreamResponse extends Response implements ICallbackResponse {
	private string $url;
	private ?int $contentLength;
	/** @var resource $context */
	private $context;

	public function __construct(string $url) {
		// Base constructor parent::__construct() cannot be called because it exists on Nextcloud but not on ownCloud.
		// Still, we need to properly initialize the headers on NC which would normally happen in the base constructor.
		$this->setHeaders([]);

		$this->url = $url;
		$this->contentLength = null;

		$reqHeaders = [];
		if (isset($_SERVER['HTTP_ACCEPT'])) {
			$reqHeaders['Accept'] = $_SERVER['HTTP_ACCEPT'];
		}
		if (isset($_SERVER['HTTP_RANGE'])) {
			$reqHeaders['Range'] = $_SERVER['HTTP_RANGE'];
		}

		$this->context = HttpUtil::createContext(null, $reqHeaders, /*maxRedirects=*/0);

		// Get headers from the source and relay the important ones to our client. Handle the redirection manually
		// since the platform redirection didn't seem to work in all cases, see https://github.com/owncloud/music/issues/1209.
		$redirectsAllowed = 20;
		do {
			$sourceHeaders = HttpUtil::getUrlHeaders($this->url, $this->context, /*convertKeysToLower=*/true);
			$status = $sourceHeaders['status_code'];
			$redirect = ($status >= 300 && $status < 400 && isset($sourceHeaders['location']));
			if ($redirect) {
				if ($redirectsAllowed-- > 0) {
					$this->url = $sourceHeaders['location'];
				} else {
					$redirect = false;
					$status = Http::STATUS_LOOP_DETECTED;
				}
			}
		} while ($redirect);

		$this->setStatus($status);

		if (isset($sourceHeaders['content-type'])) {
			$this->addHeader('Content-Type', $sourceHeaders['content-type']);
		}
		if (isset($sourceHeaders['accept-ranges'])) {
			$this->addHeader('Accept-Ranges', $sourceHeaders['accept-ranges']);
		}
		if (isset($sourceHeaders['content-range'])) {
			$this->addHeader('Content-Range', $sourceHeaders['content-range']);
		}
		if (isset($sourceHeaders['content-length'])) {
			$this->addHeader('Content-Length', $sourceHeaders['content-length']);
			$this->contentLength = (int)$sourceHeaders['content-length'];
		}
	}

	public function callback(IOutput $output) {
		// The content length is absent for stream-like sources. 0-length indicates that
		// there is no body to transfer.
		if ($this->contentLength === null || $this->contentLength > 0) {
			$inStream = \fopen($this->url, 'rb', false, $this->context);
			if ($inStream !== false) {
				$outStream = \fopen('php://output', 'wb');

				if ($outStream !== false) {
					\stream_copy_to_stream($inStream, $outStream);
					\fclose($outStream);
				}
				\fclose($inStream);
			}
		}
	}
}
