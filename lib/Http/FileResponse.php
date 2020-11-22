<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;

/**
 * A renderer for files
 */
class FileResponse extends Response {
	protected $file;
	protected $start;
	protected $end;
	protected $rangeRequest;

	/**
	 * @param \OCP\Files\File|array $file file
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($file, int $statusCode=Http::STATUS_OK) {
		if (\is_array($file)) {
			$this->file = $file['content'];
			$mime = $file['mimetype'];
			$size = \strlen($file['content']);
		} else {
			$this->file = $file;
			$mime = $file->getMimetype();
			$size = $file->getSize();
		}
		$this->addHeader('Content-type', "$mime; charset=utf-8");

		if (isset($_SERVER['HTTP_RANGE'])) {
			// Note that we do not support Range Header of the type
			// bytes=x-x,y-y
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
					$this->rangeRequest = true;
				}
			}
		} else {
			$this->setStatus($statusCode);
		}
	}

	/**
	 * Returns the rendered json
	 * @return string the file
	 */
	public function render() : ?string {
		if ($this->rangeRequest) {
			// Request Range Not Satisfiable
			if (!isset($this->start) || !isset($this->end) || $this->start > $this->end) {
				return null;
			}
		}

		return \is_string($this->file)
			? $this->renderFromString()
			: $this->renderFromFile();
	}

	private function renderFromString() {
		return $this->rangeRequest
			? \substr($this->file, $this->start, $this->partialSize())
			: $this->file;
	}

	private function renderFromFile() {
		if ($this->rangeRequest) {
			$handle = $this->file->fopen('r');
			\fseek($handle, $this->start);
			$content = '';
			$rangeSize = $this->partialSize();
			while (!\feof($handle)) {
				$content .= \fread($handle, 8192); // 8k chunk
				if (\strlen($content) > $rangeSize) {
					$content = \substr($content, 0, $rangeSize);
					break;
				}
			}
			\fclose($handle);
			return $content;
		}
		return $this->file->getContent();
	}

	private function partialSize() {
		return $this->end - $this->start + 1;
	}
}
