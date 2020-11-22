<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Search;

/**
 * A found track/album/artist
 */
class Result extends \OCP\Search\Result {
	public function __construct($id, $name, $link, $type) {
		parent::__construct($id, $name, $link);
		$this->type = $type; // defined by the parent class
	}
}
