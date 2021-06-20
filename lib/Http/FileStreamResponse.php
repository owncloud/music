<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;

/**
 * A renderer for files
 */
class FileStreamResponse extends Response implements ICallbackResponse {
	private $file;
	private $start;
	private $end;

	/**
	 * @param \OCP\Files\File $file file
	 */
	public function __construct($file) {

		$this->file = $file;
		$mime = $file->getMimetype();
		$size = $file->getSize();
		
		$this->addHeader('Content-type', "$mime; charset=utf-8");

		if (isset($_SERVER['HTTP_RANGE'])) {
			// Note that we do not support Range Header of the type
			// bytes=x-y,z-w
			if (!\preg_match('/^bytes=\d*-\d*$/', $_SERVER['HTTP_RANGE'])) {
				$this->addHeader('Content-Range', 'bytes */' . $size);
				$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
			} else {
				$parts = \explode('-', \substr($_SERVER['HTTP_RANGE'], 6));
				$this->start = $parts[0] != '' ? (int)$parts[0] : 0;
				$this->end = $parts[1] != '' ? (int)$parts[1] : $size - 1;
				$this->end = \min($this->end, $size - 1);

				if ($this->start > $this->end) {
					$this->addHeader('Content-Range', 'bytes */' . $size);
					$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
				} else {
					$this->addHeader('Accept-Ranges', 'bytes');
					$this->addHeader(
						'Content-Range', 'bytes ' .
						$this->start . '-' .
						$this->end . '/' . $size
					);
					$this->setStatus(Http::STATUS_PARTIAL_CONTENT);
				}
			}
		} else {
			$this->start = 0;
			$this->end = $size - 1;
			$this->setStatus(Http::STATUS_OK);
		}
	}

	public function callback(IOutput $output) {
		$status = $this->getStatus();

		if ($status === Http::STATUS_OK || $status === Http::STATUS_PARTIAL_CONTENT) {
			$fp = $this->file->fopen('r') ?? null;

			if (!is_resource($fp)) {
				$status = Http::STATUS_NOT_FOUND;
			} else {
				if ($this->streamDataToOutput($fp) === false) {
					$status = Http::STATUS_BAD_REQUEST;
				}
				\fclose($fp);
			}
		}

		$output->setHttpResponseCode($status);
	}

	private function streamDataToOutput($fp) {
		// Request Range Not Satisfiable
		if (!isset($this->start) || !isset($this->end) || $this->start > $this->end) {
			return false;
		} else {
			$outputStream = \fopen('php://output', 'w');
			$length = $this->end - $this->start + 1;
			$bytesCopied = stream_copy_to_stream($fp, $outputStream, $length, $this->start);
			\fclose($outputStream);
			return ($bytesCopied > 0);
		}
	}

}
