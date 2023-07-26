<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migrate the DB schema to Music v1.9.0 level from the v1.4.0 level
 */
class Version010900Date20230726141500 extends SimpleMigrationStep {

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
		$table = $schema->getTable('music_ampache_sessions');

		if (!$table->hasColumn('api_version')) {
			$table->addColumn('api_version', 'string', ['notnull' => false, 'length' => 16]);
		}

		if (!$table->hasColumn('ampache_user_id')) {
			$table->addColumn('ampache_user_id', 'int', ['notnull' => true, 'unsigned' => true]);
		}
	}
}
