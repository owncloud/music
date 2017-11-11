<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http;

/**
 * A renderer for files
 */
class ErrorResponse extends JSONResponse {

	/**
	 * @param int $statusCode the Http status code
	 * @param string $message Error message, defaults to empty
	 */
	public function __construct($statusCode, $message=null) {
		parent::__construct(
				empty($message) ? [] : ['message' => $message],
				$statusCode
		);
	}

}
