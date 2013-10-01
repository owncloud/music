<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\API;

/**
 * an extractor class for getID3
 */
class ExtractorGetID3 implements Extractor {

	private $api;
	private $getID3;

	public function __construct(API $api, \getID3 $getID3){
		$this->api = $api;
		$this->getID3 = $getID3;
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
				$this->api->log('getID3 error occured', 'debug');
				// sometimes $error is string but can't be concatenated to another string and weirdly just hide the log message
				$this->api->log('getID3 error message: '. $error, 'debug');
			}
		}

		return $metadata;
	}
}