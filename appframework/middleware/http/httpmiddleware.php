<?php

/**
 * ownCloud - App Framework
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke morris.jobke@gmail.com
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


namespace OCA\Music\AppFramework\Middleware\Http;

use OCA\Music\AppFramework\Middleware\Middleware;
use OCA\Music\AppFramework\Http\Request;
use OCA\Music\AppFramework\Core\API;

/**
 * Used to do the user authentication and exception handling for rest methods
 */
class HttpMiddleware extends Middleware {

	private $api;
	private $request;
	private $isRESTAuth = false;

	/**
	 * @param API $api an instance of the api
	 */
	public function __construct(API $api, Request $request){
		$this->api = $api;
		$this->request = $request;
	}

	/**
	 * This authenticate a user before a method call, if the credentials are given
	 * @param string/Controller $controller the controllername or string
	 * @param string $methodName the name of the method
	 */
	public function beforeController($controller, $methodName){
		if(isset($this->request->server['PHP_AUTH_USER']) && isset($this->request->server['PHP_AUTH_PW'])) {
			$loggedIn = $this->api->login(
				$this->request->server['PHP_AUTH_USER'],
				$this->request->server['PHP_AUTH_PW']
			);

			$this->isRESTAuth = true;
		}
	}

	/**
	 * This logs the user out, if he was automatically logged in
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param string $output the generated output from a response
	 * @return string the output that should be printed
	 */
	public function beforeOutput($controller, $methodName, $output){
		if($this->isRESTAuth === true) {
			$this->api->logout();
		}
		return $output;
	}
}
