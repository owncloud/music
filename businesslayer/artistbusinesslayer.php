<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\Db\Artist;
use \OCA\Music\Db\ArtistMapper;

use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;

class ArtistBusinessLayer extends BusinessLayer {

	public function __construct(ArtistMapper $artistMapper, API $api){
		parent::__construct($artistMapper, $api);
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
	 * @throws \OCA\Music\BusinessLayer\BusinessLayerException
	 */
	public function addArtistIfNotExist($name, $userId){
		try {
			$artist = $this->mapper->findByName($name, $userId);
			$this->api->log('addArtistIfNotExist - exists - ID: ' . $artist->getId(), 'debug');
		} catch(DoesNotExistException $ex){
			$artist = new Artist();
			$artist->setName($name);
			$artist->setUserId($userId);
			$artist = $this->mapper->insert($artist);
			$this->api->log('addArtistIfNotExist - added - ID: ' . $artist->getId(), 'debug');
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
