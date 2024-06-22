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
	private $url;

	public function __construct(string $url) {
		$this->url = $url;

		$this->addHeader('Content-type', "audio/mpeg");
	}

	public function callback(IOutput $output) {
		$opts = [
			'http' => [
				'header' => "Accept: audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,application/ogg;q=0.7,video/*;q=0.6,*/*;q=0.5\n" . HttpUtil::userAgentHeader(),
				'ignore_errors' => true, // don't emit warnings for bad/unavailable URL
				'max_redirects' => 5,
			]
		];
		$context = \stream_context_create($opts);

		$inStream = \fopen($this->url, 'rb', false, $context);
		$outStream = \fopen('php://output', 'wb');

		$bytesCopied = \stream_copy_to_stream($inStream, $outStream, null);
		\fclose($outStream);
		\fclose($inStream);
	}
}
