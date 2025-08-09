<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Search;

/**
 * A found track/album/artist
 */
class Result extends \OCP\Search\Result {
	public function __construct(int $id, string $name, string $link, string $type) {
		parent::__construct((string)$id, $name, $link); // TODO: base class doc says that $id should contain app name
		$this->type = $type; // defined by the parent class
	}
}
