<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
 */

namespace OCA\Music\Utility;

/**
 * This class is used to convert bytes to a human readable format
 */
class FileSize {

	private $bytes;
	private $units = 'BKMGTP';

	public function __construct($bytes) {
		$this->bytes = $bytes;
	}

	public function getHumanReadable($decimals = 1) {
		$factor = floor((strlen($this->bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $this->bytes / pow(1024, $factor)) . @$this->units[(int)$factor];
	}
}
