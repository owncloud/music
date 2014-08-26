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

/**
 * This class is used to share data about the user between the AmpacheMiddleware and
 * the AmpacheController
 */
class AmpacheUser {

	private $userId;

	public function getUserId() {
		return $this->userId;
	}

	public function setUserId($id) {
		$this->userId = $id;
	}
}
