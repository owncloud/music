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


namespace OCA\Music\Controller;

use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Http\Request;
use \OCA\Music\Utility\Scanner;


class PageController extends Controller {

	private $scanner;
	private $status;

	public function __construct(API $api, Request $request, Scanner $scanner){
		parent::__construct($api, $request);

		$this->scanner = $scanner;
	}


	/**
	 * ATTENTION!!!
	 * The following comment turns off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @CSRFExemption
	 */
	public function index() {
		$userLang = $this->api->getTrans()->findLanguage();
		// during 5.80.05 the placeholder script was outsourced to core
		$version = join('.', $this->api->getVersion());
		if(version_compare($version, '5.80.05', '>')){
			return $this->render('stable6+', array('lang' => $userLang));
		} else {
			return $this->render('stable5', array('lang' => $userLang));
		}
	}
}
