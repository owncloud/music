<?php

/**
 * @author Victor Dubiniuk
 * @copyright 2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Media;
 
interface Extractable {
	public function getArtist();
	public function getAlbum();
	public function getTitle();
	public function getTrackNumber();
	public function getFileSize();
	public function getLength();
}



class Track implements Extractable {
	
	/**
	* Path to the track
	*/
	protected $trackPath;
	
	/**
	* Array with raw getid3 data
	*/
	protected $trackData;
	
	/**
	* Preferred tags
	*/
	protected $tagType = 'id3v2';
	
	/**
	 * Constructor
	 *
	 * @param $trackData array
	 * @param $trackPath string
	 */
	public function __construct($trackPath) {
		$this->trackPath = $trackPath;
		$extractor = new Extractor_GetID3();
		$this->trackData = $extractor->extract($trackPath);
	}
	
	/**
	 * Get all tags as array
	 *
	 * @param none
	 * @return array
	 */
	public function getTags() {
		$tags = array(
			'artist' => $this->getArtist(),
			'album' => $this->getAlbum(),
			'title' => $this->getTitle(),
			'size' => $this->getFileSize(),
			'track' => $this->getTrackNumber(),
			'length' => $this->getLength(),
		);
		
		return $tags;
	}
	
	/**
	 * Get artist title
	 *
	 * @param none
	 * @return string
	 */
	public function getArtist() {
		$value = $this->getTagValue('artist');
		if (is_array($value)) {
			$value = $value[0];
		}
		if (!$value) {
			$value = 'Unknown';
		}
		return stripslashes($value);
	}
	
	/**
	 * Get album title
	 *
	 * @param none
	 * @return string
	 */
	public function getAlbum(){
		$value = $this->getTagValue('album');
		if (is_array($value)) {
			$value = $value[0];
		}
		if (!$value) {
			$value = 'Unknown';
		}
		return stripslashes($value);
	}
	
	/**
	 * Get track title
	 *
	 * @param none
	 * @return string
	 */
	public function getTitle(){
		$value = $this->getTagValue('title');
		if (is_array($value)) {
			$value = $value[0];
		}
		if (!$value) {
			$value = basename($this->trackPath);
		}
		return stripslashes($value);
	}
	
	/**
	 * Get track number
	 *
	 * @param none
	 * @return int
	 */
	public function getTrackNumber(){
		$value = $this->getTagValue('track');
		if (is_array($value)) {
			$value = $value[0];
		}
		if ($value !== false) {
			$value = $this->getTagValue('track_number');
			if (is_array($value)) {
				$value = $value[0];
			}
			if ($value !== false && preg_match('|\d+/|', $value)) {
				$value = preg_replace('|/.*$|', '', $value);
			}
		}

		return (int) $value;
	}
	
	/**
	 * Get file size
	 *
	 * @param none
	 * @return int
	 */
	public function getFileSize(){
		$value = (int) $this->getTagValue('filesize');
		return $value;
	}
	
	/**
	 * Get track length in seconds
	 *
	 * @param none
	 * @return int
	 */
	public function getLength(){
		$value = (int) $this->getTagValue('playtime_seconds');
		return round($value);
	}
	
	/**
	 * Get tag value by it's name
	 *
	 * @param string
	 * @return string
	 */
	protected function getTagValue($tagname) {
		$value = false;
		if ($this->tagType) {
			if (isset($this->trackData[$this->tagType]['comments'][$tagname])) {
				$value = $this->trackData[$this->tagType]['comments'][$tagname];
			}
		}
		if ($value===false) {
			$value = $this->getDefaultTagValue($tagname);
		}
		return $value;
	}
	
	/**
	 * Get default tag value by it's name
	 *
	 * @param string
	 * @return string
	 */
	protected function getDefaultTagValue($tagname) {
		return (isset($this->trackData['comments'][$tagname]) ? $this->trackData['comments'][$tagname] : false);
	}
}
