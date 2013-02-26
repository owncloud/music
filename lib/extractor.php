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
	 * @var \getID3 $id3;
	 */
	private $id3;

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

		if (!isset($data['comments'])) {
			return array();
		}
		$meta = array();

		$meta['artist'] = (isset($data['comments']['artist'])) ? stripslashes($data['comments']['artist'][0]) : '';
		$meta['album'] = (isset($data['comments']['album'])) ? stripslashes($data['comments']['album'][0]) : '';
		$meta['title'] = (isset($data['comments']['title'])) ? stripslashes($data['comments']['title'][0]) : '';
		$meta['size'] = (int)($data['filesize']);
		if (isset($data['comments']['track'])) {
			$meta['track'] = $data['comments']['track'][0];
		} else if (isset($data['comments']['track_number'])) {
			$track = $data['comments']['track_number'][0];
			$track = explode('/', $track);
			$meta['track'] = (int)$track[0];
		} else {
			$meta['track'] = 0;
		}
		$meta['length'] = isset($data['playtime_seconds']) ? round($data['playtime_seconds']) : 0;

		return $meta;
	}
}
