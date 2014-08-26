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
use \OCP\IRequest;

use \OCA\Music\Utility\Scanner;


class PageController extends Controller {

	private $l10n;
	private $scanner;
	private $status;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								Scanner $scanner){
		parent::__construct($appname, $request);

		$this->l10n = $l10n;
		$this->scanner = $scanner;
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$userLang = $this->l10n->findLanguage();
		return $this->render('main', array('lang' => $userLang));
	}
}
