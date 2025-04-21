<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\AppFramework\Utility;

class FileExistsException extends \RuntimeException {

	private $path;
	private $altName;

	public function __construct(string $path, string $altName) {
		$this->path = $path;
		$this->altName = $altName;
	}

	/**
	 * Get conflicting file path
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Get suggested alternative file name to avoid the conflict
	 */
	public function getAltName() {
		return $this->altName;
	}
}
