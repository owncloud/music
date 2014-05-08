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

namespace OCA\Music\Utility;

interface Extractor {

	/**
	 * get metadata info for a media file
	 *
	 * @param $path the path to the file
	 * @return array extracted data
	 */
	public function extract($path);
}
