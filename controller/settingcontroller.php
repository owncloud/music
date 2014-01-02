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


class SettingController extends Controller {

	public function __construct(API $api, Request $request){
		parent::__construct($api, $request);
	}

	/**
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function adminSetting() {
		$success = false;
		$ampacheEnabled = $this->params('ampacheEnabled');
		if($ampacheEnabled !== null) {
			$this->api->setAppValue('ampacheEnabled', filter_var($ampacheEnabled, FILTER_VALIDATE_BOOLEAN));
			$success = true;
		}
		return $this->renderPlainJSON(array('success' => $success));
	}
}
