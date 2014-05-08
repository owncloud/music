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

use OC\Files\View;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http;

/**
 * A renderer for files
 */
class FileResponse extends Response {

	protected $file;

	/**
	 * @param \OC\Files\Node\File|array $file file
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($file, $statusCode=Http::STATUS_OK) {
		$this->setStatus($statusCode);

		if (is_array($file)) {
			$this->file = $file['content'];
			$this->addHeader('Content-type', $file['mimetype'] .'; charset=utf-8');
		} else {
			$this->file = $file;
			$this->addHeader('Content-type', $file->getMimetype() .'; charset=utf-8');
		}
	}

	/**
	 * Returns the rendered json
	 * @return string the file
	 */
	public function render(){
		if (is_string($this->file)) {
			return $this->file;
		}
		return $this->file->getContent();
	}
}
