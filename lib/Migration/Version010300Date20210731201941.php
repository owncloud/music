<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migration from 1.2.x to 1.3.0
 */
class Version010300Date20210731201941 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('music_podcast_channels')) {
			$table = $schema->createTable('music_podcast_channels');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('rss_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('rss_hash', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('content_hash', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('update_checked', 'datetime', [
				'notnull' => true,
			]);
			$table->addColumn('published', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('link_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('language', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('copyright', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('author', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('image_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('category', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('starred', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['rss_hash', 'user_id'], 'music_podcast_channels_index');
		}

		if (!$schema->hasTable('music_podcast_episodes')) {
			$table = $schema->createTable('music_podcast_episodes');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('channel_id', 'integer', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('stream_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('mimetype', 'string', [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('size', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('duration', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('guid', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('guid_hash', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('episode', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('link_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('published', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('keywords', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('copyright', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('author', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('starred', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['guid_hash', 'channel_id', 'user_id'], 'music_podcast_episodes_index');
		}

		$table = $schema->getTable('music_bookmarks');
		if (!$table->hasColumn('type')) {
			$table->addColumn('type', 'integer', [
				'notnull' => true,
			]);
			$table->dropIndex('music_bookmarks_user_track');
			$table->dropColumn('track_id');
			$table->addColumn('entry_id', 'integer', [
				'notnull' => true,
			]);
			$table->addUniqueIndex(['user_id', 'type', 'entry_id'], 'music_bookmarks_index');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
