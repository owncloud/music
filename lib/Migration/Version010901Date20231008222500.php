<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migrate the DB schema to Music v1.9.1 level from the v1.4.0 level
 */
class Version010901Date20231008222500 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$this->fixInconsistentIdTypes($schema);
		$this->allowNegativeYear($schema);
		$this->ampacheSessionChanges($schema);
		$this->addRatingFields($schema);
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * Some of the foreign keys referring to entity IDs have been previously defined as signed
	 * although the referred primary key has always been unsigned.
	 */
	private function fixInconsistentIdTypes(ISchemaWrapper $schema) {
		$schema->getTable('music_albums')->changeColumn('album_artist_id', ['unsigned' => true]);
		$schema->getTable('music_tracks')->changeColumn('artist_id', ['unsigned' => true])
										->changeColumn('album_id', ['unsigned' => true]);
		$schema->getTable('music_bookmarks')->changeColumn('entry_id', ['unsigned' => true]);
		$schema->getTable('music_ampache_users')->changeColumn('id', ['unsigned' => true]);
	}

	/**
	 * Although untypical, it's not totally impossible that some historical piece of music would
	 * be tagged with a negative year indicating a year BCE.
	 */
	private function allowNegativeYear(ISchemaWrapper $schema) {
		$schema->getTable('music_tracks')->changeColumn('year', ['unsigned' => false]);
	}

	/**
	 * Add the new fields to the `music_ampache_sessions` table
	 */
	private function ampacheSessionChanges(ISchemaWrapper $schema) {
		// On SQLite, it's not possible to add notnull columns to an existing table without a default value, see
		// https://stackoverflow.com/questions/3170634/cannot-add-a-not-null-column-with-default-value-null-in-sqlite3.

		// The problem mentioned above was tried to be worked around in the commit f9a95569 by dropping and recreating
		// the table and it did help in some environments but not everywhere. Next, it was attempted to do the dropping
		// and recreating in separate migration steps; this worked in some more environments but the problem still
		// remained in others. It was not figured out, what was the important difference between the working and not
		// working environments but the problem was seen for example on NC 21.0.2 + SQLite 3.8.7.1.

		// In the end, the only systematically working solution seemed to be to make the `ampache_user_id` column
		// nullable (sigh).
		$table = $schema->getTable('music_ampache_sessions');
		$this->setColumn($table, 'api_version', 'string', ['notnull' => false, 'length' => 16]);
		$this->setColumn($table, 'ampache_user_id', 'integer', ['notnull' => false, 'unsigned' => true]);
	}

	/**
	 * Add the new field 'rating' to applicable tables
	 */
	private function addRatingFields(ISchemaWrapper $schema) {
		$tableNames = [
			'music_artists',
			'music_albums',
			'music_tracks',
			'music_playlists',
			'music_podcast_channels',
			'music_podcast_episodes'
		];

		foreach ($tableNames as $tableName) {
			$table = $schema->getTable($tableName);
			$this->setColumn($table, 'rating', 'integer', ['notnull' => true, 'default' => 0]);
		}

		// Also, add 'starred' field for playlists
		$this->setColumn($schema->getTable('music_playlists'), 'starred', 'datetime', ['notnull' => false]);
	}

	private function setColumn($table, string $name, string $type, array $args) {
		if (!$table->hasColumn($name)) {
			$table->addColumn($name, $type, $args);
		}
	}
}
