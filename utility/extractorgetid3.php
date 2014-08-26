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

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;

/**
 * an extractor class for getID3
 */
class ExtractorGetID3 implements Extractor {

	private $getID3;
	private $logger;

	public function __construct(\getID3 $getID3,
								Logger $logger){
		$this->getID3 = $getID3;
		$this->logger = $logger;
	}

	/**
	 * get metadata info for a media file
	 *
	 * @param $path the path to the file
	 * @return array extracted data
	 */
	public function extract($path) {
		$metadata = $this->getID3->analyze($path);

		// TODO make non static
		\getid3_lib::CopyTagsToComments($metadata);

		if(array_key_exists('error', $metadata)) {
			foreach ($metadata['error'] as $error) {
				// TODO $error is base64 encoded but it wasn't possible to add the decoded part to the log message
				$this->logger->log('getID3 error occured', 'debug');
				// sometimes $error is string but can't be concatenated to another string and weirdly just hide the log message
				$this->logger->log('getID3 error message: '. $error, 'debug');
			}
		}

		return $metadata;
	}
}
