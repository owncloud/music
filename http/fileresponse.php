<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
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
	 * @param \OC\Files\Node\File|array $file file
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($file, $statusCode=Http::STATUS_OK) {

		if (is_array($file)) {
			$this->file = $file['content'];
			$this->addHeader('Content-type', $file['mimetype'] .'; charset=utf-8');
		} else {
			$this->file = $file;
			$this->addHeader('Content-type', $file->getMimetype() .'; charset=utf-8');
		}
		if (isset($_SERVER['HTTP_RANGE'])) {
			$size = $file->getSize();
			// Note that we do not support Range Header of the type
			// bytes=x-x,y-y
			if (!preg_match('/^bytes=\d*-\d*$/', $_SERVER['HTTP_RANGE'])) {
				$this->addHeader('Content-Range: bytes */' . $size);
				$this->setStatus(Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
			} else {
				$parts = explode('-', substr($_SERVER['HTTP_RANGE'], 6));
				$this->start = $parts[0] != '' ? (int)$parts[0] : 0;
				$this->end = $parts[1] != '' ? (int)$parts[1] : $size;
				$this->end = $size < $this->end ? $size : $this->end;

				if ($this->start > $this->end) {
					$this->addHeader('Content-Range: bytes */' . $size);
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
	public function render(){
		if (is_string($this->file)) {
			if ($this->rangeRequest) {
				// Request Range Not Satisfiable
				if (!isset($this->start) && !isset($this->end) || $this->start > $this->end) {
					return null;
				}

				return substr($this->file, $this->start, $this->end - $this->start + 1);
			}
			return $this->file;
		}
		if ($this->rangeRequest) {
			// Request Range Not Satisfiable
			if (!isset($this->start) && !isset($this->end) || $this->start > $this->end) {
				return null;
			}

			$handle = $this->file->fopen('r');
			fseek($handle, $this->start);
			$content = '';
			$rangeSize = $this->end - $this->start + 1;
			while(!feof($handle)) {
				$content .= fread($handle, 8192); // 8k chunk
				if (strlen($content) > $rangeSize) {
					$content = substr($content, $rangeSize);
					break;
				}
			}
			fclose($handle);
			return $content;
		}
		return $this->file->getContent();
	}
}
