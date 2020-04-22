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

namespace OCA\Music\Db;

use OCP\IDBConnection;

class BookmarkMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_bookmarks', '\OCA\Music\Db\Bookmark', 'comment');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null) {
		return 'SELECT * ' .
			'FROM `*PREFIX*music_bookmarks` ' .
			'WHERE `user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 * @param integer $limit
	 * @param integer $offset
	 * @return Bookmark[]
	 */
	public function findAll($userId, $limit=null, $offset=null) {
		return $this->findEntities($this->makeSelectQuery(), [ $userId ], $limit, $offset);
	}

	/**
	 * @param string $userId
	 * @param string $trackId
	 * @param string $position
	 * @param string $comment
	 * @return Bookmark[]
	 */
	public function create($userId, $trackId, $position, $comment) {
    // ensure no existing record exists
    $this->remove($userId, $trackId);

    $bookmark = new Bookmark();
    $bookmark->setUserId($userId);
    $bookmark->setTrackId($trackId);
    $bookmark->setPosition($position);
    $bookmark->setComment($comment);

    return $this->insertOrUpdate($bookmark);
	}

	/**
	 * @param string $userId
	 * @param string $trackId
	 * @return Bookmark[]
	 */
	public function remove($userId, $trackId) {
		$sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `user_id` = ? AND `track_id` = ?';
		$this->execute($sql, [ $userId, $trackId ]);
	}

  public function findPlayQueueBookmark($userId) {
    $retVal = \array_shift($this->findEntities(
        'SELECT * FROM `' . $this->getTableName() . '` WHERE `user_id` = ? AND `track_id` < 0',
        [ $userId ]
    ));

    // remove negative value
    if ($retVal !== null) {
      $retVal->trackId = -$retVal->trackId;
    }

    return $retVal;
  }

  public function removePlayQueueBookmarks($userId) {
    return $this->execute(
        'DELETE FROM `' . $this->getTableName() . '` WHERE `user_id` = ? AND `track_id` < 0',
        [ $userId ]);
  }

  protected function findUniqueEntity($entity) {
    return $this->findEntity(
        'SELECT * FROM `' . $this->getTableName() . '` WHERE `user_id` = ? AND `track_id` < 0',
        [ $entity->getUserId(), $entity->getTrackId() ]
    );
  }
}
