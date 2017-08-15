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

require_once __DIR__ . '/../3rdparty/getID3/getid3/getid3.php';

/**
 * an extractor class for getID3
 */
class ExtractorGetID3 implements Extractor {

	private $getID3;
	private $logger;

	public function __construct(Logger $logger){
		$this->logger = $logger;

		$this->getID3 = new \getID3();
		$this->getID3->encoding = 'UTF-8';
		// On 32-bit systems, getid3 tries to make a 2GB size check,
		// which does not work with fopen. Disable it.
		// Therefore the filesize (determined by getID3) could be wrong
		// (for files over ~2 GB) but this isn't used in any way.
		$this->getID3->option_max_2gb_check = false;
	}

	/**
	 * get metadata info for a media file
	 *
	 * @param \OCP\Files\File $file the file
	 * @return array extracted data
	 */
	public function extract($file) {
		$metadata = $this->getID3->analyze($file->getPath(), $file->fopen('r'), $file->getSize());

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

	/**
	 * extract embedded cover art image from media file
	 * 
	 * @param \OCP\Files\File $file the media file
	 * @return array with keys 'mimetype' and 'content'
	 */
	public function parseEmbeddedCoverArt($file){
		$fileInfo = $this->extract($file);
		return self::getTag($fileInfo, 'picture', true);
	}

	public static function getTag($fileInfo, $tag, $binaryValued = false) {
		if(array_key_exists('comments', $fileInfo)) {
			$comments = $fileInfo['comments'];
			if(array_key_exists($tag, $comments)) {
				$value = $comments[$tag][0];
				if (!$binaryValued) {
					// Ensure that the tag contains only valid utf-8 characters.
					// Illegal characters may result, if the file metadata has a mismatch
					// between claimed and actual encoding. Invalid characters could break
					// the database update.
					mb_substitute_character(0xFFFD); // Use the Unicode REPLACEMENT CHARACTER (U+FFFD)
					$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
				}
				return $value;
			}
		}
		return null;
	}

	public static function getFirstOfTags($fileInfo, array $tags, $defaultValue = null) {
		foreach ($tags as $tag) {
			$value = self::getTag($fileInfo, $tag);
			if ($value !== null && $value !== '') {
				return $value;
			}
		}
		return $defaultValue;
	}

}
