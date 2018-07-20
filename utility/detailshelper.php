<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;



class DetailsHelper {
	private $extractor;
	private $logger;

	public function __construct(
			Extractor $extractor,
			Logger $logger) {
		$this->extractor = $extractor;
		$this->logger = $logger;
	}

	/**
	 * @param integer $fileId
	 * @param Folder $userFolder
	 * $return array|null
	 */
	public function getDetails($fileId, $userFolder) {
		$fileNodes = $userFolder->getById($fileId);
		if (\count($fileNodes) > 0) {
			$data = $this->extractor->extract($fileNodes[0]);

			$result = [
				'fileinfo' => $data['audio'],
				'tags' => self::flattenComments($data['comments'])
			];

			// binary data has to be encoded
			$result['tags']['picture'] = self::encodePictureTag($result['tags']['picture']);

			// 'streams' contains duplicate data
			unset($result['fileinfo']['streams']);

			// one track number is enough
			if (\array_key_exists('track', $result['tags'])
				&& \array_key_exists('track_number', $result['tags'])) {
				unset($result['tags']['track']);
			}

			// add track length
			if (\array_key_exists('playtime_seconds', $data)) {
				$result['length'] = \ceil($data['playtime_seconds']);
			} else {
				$result['length'] = null;
			}

			// add file path
			$result['path'] = $userFolder->getRelativePath($fileNodes[0]->getPath());

			return $result;
		}
		return null;
	}

	/**
	 * Base64 encode the picture binary data and wrap it so that it can be directly used as
	 * src of an HTML img element.
	 * @param string $pic
	 * @return string|null
	 */
	private static function encodePictureTag($pic) {
		if ($pic['data']) {
			return 'data:' . $pic['image_mime'] . ';base64,' . \base64_encode($pic['data']);
		} else {
			return null;
		}
	}

	/**
	 * In the 'comments' field from the extractor, the value for each key is a 1-element
	 * array containing the actual tag value. Remove these intermediate arrays.
	 * @param array $array
	 * @return array
	 */
	private static function flattenComments($array) {
		// key 'text' is an exception, its value is an associative array
		$textArray = null;

		foreach ($array as $key => $value) {
			if ($key === 'text') {
				$textArray = $value;
			} else {
				$array[$key] = $value[0];
			}
		}

		if (!empty($textArray)) {
			$array = \array_merge($array, $textArray);
			unset($array['text']);
		} 

		return $array;
	}

}
