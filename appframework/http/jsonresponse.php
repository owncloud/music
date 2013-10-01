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


namespace OCA\Music\AppFramework\Http;


/**
 * A renderer for JSON calls
 */
class JSONResponse extends Response {

	protected $data;


	/**
	 * @param array|object $data the object or array that should be transformed
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($data=array(), $statusCode=Http::STATUS_OK) {
		$this->data = $data;
		$this->setStatus($statusCode);
		$this->addHeader('X-Content-Type-Options', 'nosniff');
		$this->addHeader('Content-type', 'application/json; charset=utf-8');
	}


	/**
	 * @deprecated use "not wrapping" method to add data
	 * @see setData
	 * Sets values in the data json array
	 * @param array|object $params an array or object which will be transformed
	 *                             to JSON
	 */
	public function setParams(array $params){
		$this->data['data'] = $params;
		$this->data['status'] = 'success';
	}


	/**
	 * @deprecated use "not wrapping" method to read data
	 * @see getData
	 * Used to get the set parameters
	 * @return array the params
	 */
	public function getParams(){
		return $this->data['data'];
	}


	/**
	 * @deprecated use "not wrapping" method to add data
	 * @see setData
	 * in case we want to render an error message, also logs into the owncloud log
	 * @param string $message the error message
	 */
	public function setErrorMessage($msg){
		$this->error = true;
		$this->data['msg'] = $msg;
		$this->data['status'] = 'error';
	}


	/**
	 * Returns the rendered json
	 * @return string the rendered json
	 */
	public function render(){
		return json_encode($this->data);
	}

	/**
	 * Sets values in the data json array
	 * @param array|object $params an array or object which will be transformed
	 *                             to JSON
	 */
	public function setData($data){
		$this->data = $data;
	}


	/**
	 * Used to get the set parameters
	 * @return array the data
	 */
	public function getData(){
		return $this->data;
	}

}
