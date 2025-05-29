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
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;
use OCP\Files\File;

/**
 * A renderer for files
 */
class FileResponse extends Response {
	/* @var File|string $file */
	protected $file;
	protected int $start;
	protected int $end;
	protected bool $rangeRequest;

	/**
	 * @param File|array $file file
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
		$this->start = 0;
		$this->end = $size - 1;

		$this->addHeader('Content-type', "$mime; charset=utf-8");

		$this->rangeRequest = isset($_SERVER['HTTP_RANGE']);
		if ($this->rangeRequest) {
			// Note that we do not support Range Header of the type
			// bytes=x-x,y-y
			if (!\preg_match('/^bytes=\d*-\d*$/', $_SERVER['HTTP_RANGE'])) {
				$this->addHeader('Content-Range', 'bytes */' . $size);
				$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
			} else {
				$parts = \explode('-', \substr($_SERVER['HTTP_RANGE'], 6));
				$this->start = ($parts[0] != '') ? (int)$parts[0] : 0;
				$this->end = ($parts[1] != '') ? (int)$parts[1] : $size - 1;
				$this->end = \min($this->end, $size - 1);

				if ($this->start > $this->end) {
					$this->addHeader('Content-Range', 'bytes */' . $size);
					$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
				} else {
					$this->addHeader('Accept-Ranges', 'bytes');
					$this->addHeader('Content-Range', "bytes {$this->start}-{$this->end}/$size");
					$this->setStatus(Http::STATUS_PARTIAL_CONTENT);
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
		if ($this->rangeRequest && $this->start > $this->end) {
			// Request Range Not Satisfiable
			return null;
		}

		return \is_string($this->file)
			? $this->renderFromString()
			: $this->renderFromFile();
	}

	private function renderFromString() {
		assert(\is_string($this->file));
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
