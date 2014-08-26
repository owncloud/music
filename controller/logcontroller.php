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

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\IRequest;

use \OCA\Music\AppFramework\Core\Logger;

class LogController extends Controller {

	private $logger;

	public function __construct($appname,
								IRequest $request,
								Logger $logger){
		parent::__construct($appname, $request);
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function log() {
		$message = $this->params('message');
		$this->logger->log('JS: ' . $message, 'debug');
		return new JSONResponse(array('success' => true));
	}
}
