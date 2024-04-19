<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023, 2024
 */

namespace OCA\Music\Utility;

/**
 * Minimal mocked user preferences needed to support the Ample client
 */
class AmpachePreferences {

	private const SETTINGS = [
		[
			'id' => 1,
			'name' => 'download',
			'level' => 100,
			'description' => 'Allow Downloads',
			'value' => '1',
			'type' => 'boolean',
			'category' => 'options',
			'subcategory' => 'feature'
		],
		[
			'id' => 122,
			'name' => 'album_release_type',
			'level' => 100,
			'description' => 'Album - Group per release type',
			'value' => '0',
			'type' => 'boolean',
			'category' => 'interface',
			'subcategory' => 'library'
		],
		[
			'id' => 130,
			'name' => 'album_release_type_sort',
			'level' => 100,
			'description' => 'Album - Group per release type sort',
			'value' => 'album,ep,live,single',
			'type' => 'string',
			'category' => 'interface',
			'subcategory' => 'library'
		]
	];

	public static function getAll() : array {
		return self::SETTINGS;
	}

	public static function get(string $name) : ?array {
		return \array_column(self::SETTINGS, null, 'name')[$name] ?? null;
	}
}