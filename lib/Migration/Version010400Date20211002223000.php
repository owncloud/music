<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migrate the DB schema to Music v1.4.0 level from the v1.3.x level
 */
class Version010400Date20211002223000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return void
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
		$this->migrateMusicTracks($schema);
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	private function migrateMusicTracks(ISchemaWrapper $schema) : void {
		$table = $schema->getTable('music_tracks');
		$this->setColumns($table, [
			[ 'play_count',		'integer',	['notnull' => true, 'unsigned' => true, 'default' => 0] ],
			[ 'last_played',	'datetime', ['notnull' => false] ]
		]);
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 */
	private function setColumn($table, string $name, string $type, array $args) : void {
		if (!$table->hasColumn($name)) {
			$table->addColumn($name, $type, $args);
		}
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 */
	private function setColumns($table, array $nameTypeArgsPerCol) : void {
		foreach ($nameTypeArgsPerCol as $nameTypeArgs) {
			list($name, $type, $args) = $nameTypeArgs;
			$this->setColumn($table, $name, $type, $args);
		}
	}

}
