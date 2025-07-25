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

use OCA\Music\Utility\ArrayUtil;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;

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
		$reqHeaders = [];
		if (isset($_SERVER['HTTP_ACCEPT'])) {
			$reqHeaders['Accept'] = $_SERVER['HTTP_ACCEPT'];
		}
		if (isset($_SERVER['HTTP_RANGE'])) {
			$reqHeaders['Range'] = $_SERVER['HTTP_RANGE'];
		}

		$this->context = HttpUtil::createContext(null, $reqHeaders);
		$resolved = HttpUtil::resolveRedirections($url, $this->context);

		$this->url = $resolved['url'];
		$this->setStatus($resolved['status_code']);
		$this->setHeaders($resolved['headers']);

		$length = ArrayUtil::getCaseInsensitive($resolved['headers'], 'content-length');
		$this->contentLength = ($length === null) ? null : (int)$length;
	}

	/**
	 * @return void
	 */
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
