<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

require_once 'getid3/getid3.php';

interface Extractor {
	/**
	 * get metadata info for a media file
	 *
	 * @param $path
	 * @return array
	 */
	public function extract($path);
}

/**
 * pure php id3 extractor
 */
class Extractor_GetID3 implements Extractor {
	/**
	 * @var \getID3 $getID3;
	 */
	private $getID3;
	
	public function __construct() {
		$this->getID3 = @new \getID3();
		$this->getID3->encoding = 'UTF-8';
	}

	/**
	 * get metadata info for a media file
	 *
	 * @param $path
	 * @return array
	 */
	public function extract($path) {
		$file = \OC\Files\Filesystem::getView()->getAbsolutePath($path);
		$data = @$this->getID3->analyze('oc://' . $file);
		\getid3_lib::CopyTagsToComments($data);
		
		$track = new Track($data, $path);
		return $track->getTags();
	}

}
