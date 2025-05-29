<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;
use OCP\Files\File;

/**
 * A renderer for files
 */
class FileStreamResponse extends Response implements ICallbackResponse {
	private File $file;
	private int $start;
	private int $end;

	public function __construct(File $file) {

		$this->file = $file;
		$mime = $file->getMimetype();
		$size = $file->getSize();
		$this->start = 0;
		$this->end = $size - 1;
	
		$this->addHeader('Content-type', "$mime; charset=utf-8");

		if (isset($_SERVER['HTTP_RANGE'])) {
			// Note that we do not support Range Header of the type
			// bytes=x-y,z-w
			if (!\preg_match('/^bytes=\d*-\d*$/', $_SERVER['HTTP_RANGE'])) {
				$this->addHeader('Content-Range', 'bytes */' . $size);
				$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
			} else {
				$parts = \explode('-', \substr($_SERVER['HTTP_RANGE'], 6));
				$this->start = ($parts[0] != '') ? (int)$parts[0] : 0;
				$this->end = ($parts[1] != '') ? (int)$parts[1] : $size - 1;
				$this->end = \min($this->end, $size - 1);

				if ($this->start > $this->end) {
					$this->addHeader('Content-Range', "bytes */$size");
					$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
				} else {
					$this->addHeader('Accept-Ranges', 'bytes');
					$this->addHeader('Content-Range', "bytes {$this->start}-{$this->end}/$size");
					$this->addHeader('Content-Length', (string)($this->end - $this->start + 1));
					$this->setStatus(Http::STATUS_PARTIAL_CONTENT);
				}
			}
		} else {
			$this->addHeader('Content-Length', (string)$size);
			$this->setStatus(Http::STATUS_OK);
		}
	}

	public function callback(IOutput $output) {
		$status = $this->getStatus();

		if ($status === Http::STATUS_OK || $status === Http::STATUS_PARTIAL_CONTENT) {
			$fp = $this->file->fopen('r');

			if (!is_resource($fp)) {
				$output->setHttpResponseCode(Http::STATUS_NOT_FOUND);
			} else {
				if ($this->streamDataToOutput($fp) === false) {
					$output->setHttpResponseCode(Http::STATUS_BAD_REQUEST);
				}
				\fclose($fp);
			}
		}
	}

	private function streamDataToOutput($fp) {
		// Request Range Not Satisfiable
		if ($this->start > $this->end) {
			return false;
		} else {
			$outputStream = \fopen('php://output', 'w');
			$length = $this->end - $this->start + 1;
			$bytesCopied = \stream_copy_to_stream($fp, $outputStream, $length, $this->start);
			\fclose($outputStream);
			return ($bytesCopied > 0);
		}
	}

}
