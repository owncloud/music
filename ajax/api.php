<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

\OCP\JSON::checkAppEnabled('media');
\OCP\JSON::checkLoggedIn();

error_reporting(E_ALL); //no script error reporting because of getID3

session_write_close();

$arguments = $_POST;

if (!isset($_POST['action']) and isset($_GET['action'])) {
	$arguments = $_GET;
}

foreach ($arguments as &$argument) {
	$argument = stripslashes($argument);
}

\OC_Util::obEnd();

if (!isset($arguments['artist'])) {
	$arguments['artist'] = 0;
}
if (!isset($arguments['album'])) {
	$arguments['album'] = 0;
}
if (!isset($arguments['search'])) {
	$arguments['search'] = '';
}

$collection = new Collection(\OCP\USER::getUser());

if ($arguments['action']) {
	switch ($arguments['action']) {
		case 'delete':
			\OCP\JSON::callCheck();
			$path = $arguments['path'];
			$collection->deleteSongByPath($path);
			$paths = explode(PATH_SEPARATOR, \OCP\Config::getUserValue(\OCP\USER::getUser(), 'media', 'paths', ''));
			if (array_search($path, $paths) !== false) {
				unset($paths[array_search($path, $paths)]);
				\OCP\Config::setUserValue(\OCP\USER::getUser(), 'media', 'paths', implode(PATH_SEPARATOR, $paths));
			}
			break;
		case 'get_collection':
			$data = array();
			$data['artists'] = $collection->getArtists();
			$data['albums'] = $collection->getAlbums();
			$data['songs'] = $collection->getSongs();
			\OCP\JSON::encodedPrint($data);
			break;
		case 'scan':
			\OCP\DB::beginTransaction();
			set_time_limit(0); //recursive scan can take a while
			$eventSource = new \OC_EventSource();
			$watcher = new ScanWatcher($eventSource);
			$scanner = new Scanner($collection);
			\OC_Hook::connect('media', 'song_count', $watcher, 'count');
			\OC_Hook::connect('media', 'song_scanned', $watcher, 'scanned');
			$scanner->scanCollection();
			$watcher->done();
			$eventSource->close();
			\OCP\DB::commit();
			break;
		case 'scanFile':
			$scanner = new Scanner($collection);
			echo ($scanner->scanFile($arguments['path'])) ? 'true' : 'false';
			break;
		case 'get_artists':
			\OCP\JSON::encodedPrint($collection->getArtists($arguments['search']));
			break;
		case 'get_albums':
			\OCP\JSON::encodedPrint($collection->getAlbums($arguments['artist'], $arguments['search']));
			break;
		case 'get_songs':
			\OCP\JSON::encodedPrint($collection->getSongs($arguments['artist'], $arguments['album'], $arguments['search']));
			break;
		case 'get_path_info':
			if (\OC\Files\Filesystem::file_exists($arguments['path'])) {
				$songId = $collection->getSongByPath($arguments['path']);
				if ($songId == 0) {
					unset($_SESSION['collection']);
					$scanner = new Scanner($collection);
					$songId = $scanner->scanFile($arguments['path']);
				}
				if ($songId > 0) {
					$song = $collection->getSong($songId);
					$song['artist'] = $collection->getArtistName($song['song_artist']);
					$song['album'] = $collection->getAlbumName($song['song_album']);
					\OCP\JSON::encodedPrint($song);
				}
			}
			break;
		case 'play':
			$ftype = \OC\Files\Filesystem::getMimeType($arguments['path']);
			if (substr($ftype, 0, 5) != 'audio' and $ftype != 'application/ogg') {
				echo 'Not an audio file';
				exit();
			}

			$songId = $collection->getSongByPath($arguments['path']);
			$collection->registerPlay($songId);

			header('Content-Type:' . $ftype);
			\OCP\Response::enableCaching(3600 * 24); // 24 hour
			header('Accept-Ranges: bytes');
			header('Content-Length: ' . \OC\Files\Filesystem::filesize($arguments['path']));
			$mtime = \OC\Files\Filesystem::filemtime($arguments['path']);
			\OCP\Response::setLastModifiedHeader($mtime);

			\OC\Files\Filesystem::readfile($arguments['path']);
			exit;
		case 'find_music':
			$scanner = new Scanner($collection);
			$music = $scanner->getMusic();
			\OCP\JSON::encodedPrint($music);
			exit;
	}
}

class ScanWatcher {
	/**
	 * @var \OC_EventSource $eventSource;
	 */
	private $eventSource;
	private $scannedCount = 0;

	public function __construct($eventSource) {
		$this->eventSource = $eventSource;
	}

	public function count($params) {
		$this->eventSource->send('count', $params['count']);
	}

	public function scanned($params) {
		$this->eventSource->send('scanned', array('file' => $params['path'], 'count' => $params['count']));
		$this->scannedCount = $params['count'];
	}

	public function done() {
		$this->eventSource->send('done', $this->scannedCount);
	}
}
