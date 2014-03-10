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


namespace OCA\Music\Core;

use \OCA\Music\AppFramework\Core\API as BaseAPI;

class API extends BaseAPI {
	/**
	 * get version of ownCloud instance
	 *
	 * @return string version of ownCloud
	 */
	public function getVersion() {
		return \OCP\Util::getVersion();
	}

	/**
	 * Register a backgroundjob
	 * @param \OC\BackgroundJob\Job|string $job the job instance
	 * @param mixed $argument the argument passed to the run() method of the job
	 * called
	 */
	public function registerJob($job, $argument = null) {
		\OCP\Backgroundjob::registerJob($job, $argument);
	}

	/**
	 * Tells ownCloud to include a template in the personal overview
	 * @param string $mainPath the path to the main php file without the php
	 * suffix, relative to your apps directory! not the template directory
	 * @param string $appName the name of the app, defaults to the current one
	 */
	public function registerPersonal($mainPath, $appName=null) {
		if($appName === null){
			$appName = $this->appName;
		}

		\OCP\App::registerPersonal($appName, $mainPath);
	}
}
