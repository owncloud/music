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

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Artist;
use \OCA\Music\Db\ArtistMapper;


class ArtistBusinessLayer extends BusinessLayer {

	private $logger;

	public function __construct(ArtistMapper $artistMapper, Logger $logger){
		parent::__construct($artistMapper);
		$this->logger = $logger;
	}

	/**
	 * Returns all artists with the given ids
	 * @param array $artistIds the ids of the artists
	 * @param string $userId the name of the user
	 * @return array of artists
	 */
	public function findMultipleById($artistIds, $userId){
		return $this->mapper->findMultipleById($artistIds, $userId);
	}

	/**
	 * Adds an artist (if it does not exist already) and returns the new artist
	 * @param string $name the name of the artist
	 * @return \OCA\Music\Db\Artist
	 * @throws \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException
	 */
	public function addArtistIfNotExist($name, $userId){
		try {
			$artist = $this->mapper->findByName($name, $userId);
			$this->logger->log('addArtistIfNotExist - exists - ID: ' . $artist->getId(), 'debug');
		} catch(DoesNotExistException $ex){
			$artist = new Artist();
			$artist->setName($name);
			$artist->setUserId($userId);
			$artist = $this->mapper->insert($artist);
			$this->logger->log('addArtistIfNotExist - added - ID: ' . $artist->getId(), 'debug');
		} catch(MultipleObjectsReturnedException $ex){
			throw new BusinessLayerException($ex->getMessage());
		}
		return $artist;
	}

	/**
	 * Deletes artists
	 * @param array $artistIds the ids of the artist which should be deleted
	 */
	public function deleteById($artistIds){
		$this->mapper->deleteById($artistIds);
	}
}
