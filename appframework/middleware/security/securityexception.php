<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt dev@bernhard-posselt.com
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


namespace OCA\Music\AppFramework\Middleware\Security;


/**
 * Thrown when the security middleware encounters a security problem
 */
class SecurityException extends \Exception {

	private $ajax;

	/**
	 * @param string $msg the security error message
	 * @param bool $ajax true if it resulted because of an ajax request
	 */
	public function __construct($msg, $ajax, $code = 0) {
		parent::__construct($msg, $code);
		$this->ajax = $ajax;
	}


	/**
	 * Used to check if a security exception occured in an ajax request
	 * @return bool true if exception resulted because of an ajax request
	 */
	public function isAjax(){
		return $this->ajax;
	}


}