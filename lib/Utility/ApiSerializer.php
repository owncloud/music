<?php declare(strict_types=1);

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
 * This class is used to serialize any data object to an array that can be
 * returned as any response (JSON, XML, ...)
 */
class ApiSerializer {
	public function serialize($data) {
		$result = [];

		// wrap response in an array if its just a single item
		if (!\is_array($data)) {
			$data = [$data];
		}

		foreach ($data as $item) {
			$result[] = $item->toApi();
		}

		return $result;
	}
}
