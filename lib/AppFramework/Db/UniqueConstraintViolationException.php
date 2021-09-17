<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\AppFramework\Db;

/**
 * Own exception type for the Music app to be used on DB unique constraint violations.
 * The exceptions used for this by the core vary by the version of Nexcloud or ownCloud used.
 * Mapping these all to a common type enables unified handling.
 */
class UniqueConstraintViolationException extends \Exception {

	public function __construct(string $message = "", int $code = 0, \Throwable $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
