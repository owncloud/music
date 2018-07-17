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
	 * @param string $fileId
	 * @param string $userId
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
			$result['tags']['picture']['data'] = \base64_encode($result['tags']['picture']['data']);

			// 'streams' contains duplicate data
			unset($result['fileinfo']['streams']);

			// one track number is enough
			if (\array_key_exists('track', $result['tags'])
				&& \array_key_exists('track_number', $result['tags'])) {
				unset($result['tags']['track']);
			}

			// add file path
			$result['path'] = $userFolder->getRelativePath($fileNodes[0]->getPath());

			return $result;
		}
		return null;
	}

	// In the 'comments' field from the extractor, the value for each key is a 1-element
	// array containing the actual tag value. Remove these intermediate arrays.
	private static function flattenComments($array) {
		foreach ($array as $key => $value) {
			$array[$key] = $value[0];
		}
		return $array;
	}

}
