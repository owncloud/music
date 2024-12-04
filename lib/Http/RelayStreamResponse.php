<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
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
	/** @var resource $context */
	private $context;

	public function __construct(string $url) {
		$this->url = $url;

		$reqHeaders = [];
		if (isset($_SERVER['HTTP_ACCEPT'])) {
			$reqHeaders['Accept'] = $_SERVER['HTTP_ACCEPT'];
		}
		if (isset($_SERVER['HTTP_RANGE'])) {
			$reqHeaders['Range'] = $_SERVER['HTTP_RANGE'];
		}

		$this->context = HttpUtil::createContext(null, $reqHeaders);

		// Get headers from the source and relay the important ones to our client
		$sourceHeaders = HttpUtil::getUrlHeaders($url, $this->context);
	
		if ($sourceHeaders !== null) {
			// According to RFC 2616, HTTP headers are case-insensitive but we need predictable keys
			$sourceHeaders = \array_change_key_case($sourceHeaders, CASE_LOWER);

			if (isset($sourceHeaders['content-type'])) {
				$this->addHeader('Content-Type', $sourceHeaders['content-type']);
			}
			if (isset($sourceHeaders['content-length'])) {
				$this->addHeader('Content-Length', $sourceHeaders['content-length']);
			}
			if (isset($sourceHeaders['accept-ranges'])) {
				$this->addHeader('Accept-Ranges', $sourceHeaders['accept-ranges']);
			}
			if (isset($sourceHeaders['content-range'])) {
				$this->addHeader('Content-Range', $sourceHeaders['content-range']);
			}

			$this->setStatus($sourceHeaders['status_code']);
		}
		else {
			$this->setStatus(Http::STATUS_FORBIDDEN);
		}
	}

	public function callback(IOutput $output) {
		$inStream = \fopen($this->url, 'rb', false, $this->context);
		$outStream = \fopen('php://output', 'wb');

		$bytesCopied = \stream_copy_to_stream($inStream, $outStream);
		\fclose($outStream);
		\fclose($inStream);
	}
}
