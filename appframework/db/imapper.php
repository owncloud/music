<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 * @copyright Morris Jobke 2014
 */

namespace OCA\Music\AppFramework\Db;

interface IMapper {

	/**
	 * @param string $userId
	 *
	 * @return Entity
	 */
	public function find($id, $userId);

	/**
	 * @param string $userId
	 */
	public function findAll($userId);
}
