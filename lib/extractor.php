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
		
		// Trying to enable stream support
		if(ini_get('allow_url_fopen') != 1) {
			\OCP\Util::writeLog('Media', 'allow_url_fopen is disabled. It is strongly advised to enable it in your php.ini', \OCP\Util::WARN);
			@ini_set('allow_url_fopen', '1');
		}
	}

	/**
	 * get metadata info for a media file
	 *
	 * @param $path
	 * @return array
	 */
	public function extract($path) {
		if(ini_get('allow_url_fopen')) {
			$file = \OC\Files\Filesystem::getView()->getAbsolutePath($path);
			$data = @$this->getID3->analyze('oc://' . $file);
		} else {
			// Fallback to the local FS
			$file = \OC\Files\Filesystem::getLocalFile($path);
		}
		\getid3_lib::CopyTagsToComments($data);
		
		return $data;
	}

}
