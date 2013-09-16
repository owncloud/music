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

use \OCA\AppFramework\Core\API;
use \OCA\AppFramework\Http\Request;
use \OCA\AppFramework\Db\Mapper;
use \OCA\Music\Utility\Scanner;


class PageController extends Controller {

	private $scanner;
	private $status;

	public function __construct(API $api, Request $request, Scanner $scanner, Mapper $status){
		parent::__construct($api, $request);

		$this->scanner = $scanner;
		$this->status = $status;
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
		$userId = $this->api->getUserId();
		if(!$this->status->isScanned($userId)) {
			$this->api->log('Rescan triggered', 'debug');
			$this->scanner->rescan();
			$this->status->setScanned($userId);
			$this->api->log('Rescan finished', 'debug');
		}
		// during 5.80.05 the placeholder script was outsourced to core
		$version = join('.', $this->api->getVersion());
		if(version_compare($version, '5.80.05', '>')){
			return $this->render('stable6+');
		} else {
			return $this->render('stable5');
		}
	}
}
