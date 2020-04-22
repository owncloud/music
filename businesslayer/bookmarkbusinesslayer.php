<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gavin E <no.emai@address.for.me>
 * @copyright Gavin E 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\BookmarkMapper;
use \OCA\Music\Db\Bookmark;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Utility\Util;

class BookmarkBusinessLayer extends BusinessLayer {
	private $logger;

	public function __construct(
			BookmarkMapper $bookmarkMapper,
			Logger $logger) {
		parent::__construct($bookmarkMapper);
		$this->logger = $logger;
	}

  public function findAll($userId, $sortBy = SortBy::None, $limit = null, $offset = null) {
    $bookmarks = $this->mapper->findAll($userId, $sortBy, $limit, $offset);

    foreach ($bookmarks as $key => $bookmark) {
      if ($bookmark->getTrackId() < 0) {
        unset($bookmarks[$key]);
        break;
      }
    }

    return $bookmarks;
  }

  public function createPlayQueueBookmark($userId, $trackId, $position) {
    // first remove any existing playqueue bookmarks
    $this->mapper->removePlayQueueBookmarks($userId);

    // create bookmark and store update time in comment field
    return $this->create($userId, -$trackId, $position, gmdate('Y-m-d\TH:i:s\Z', time()));
  }

  public function findPlayQueueBookmark($userId) {
    return $this->mapper->findPlayQueueBookmark($userId);
  }

	public function create($userId, $trackId, $position, $comment) {
    // ensure no existing record exists
    $this->mapper->remove($userId, $trackId);

    $bookmark = new Bookmark();
    $bookmark->setUserId($userId);
    $bookmark->setTrackId($trackId);
    $bookmark->setPosition($position);
    $bookmark->setComment(Util::truncate($comment, 256));

		return $this->mapper->insert($bookmark);
	}

	public function remove($userId, $trackId) {
    return $this->mapper->remove($userId, $trackId);
	}
}
