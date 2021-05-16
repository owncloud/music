<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010200Date20210513171803 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('music_artists')) {
			$table = $schema->createTable('music_artists');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('cover_file_id', 'bigint', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('mbid', 'string', [
				'notnull' => false,
				'length' => 36,
			]);
			$table->addColumn('hash', 'string', [
				'notnull' => true,
				'length' => 32,
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
			$table->addUniqueIndex(['user_id', 'hash'], 'user_id_hash_idx');
		}

		if (!$schema->hasTable('music_albums')) {
			$table = $schema->createTable('music_albums');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('cover_file_id', 'bigint', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('mbid', 'string', [
				'notnull' => false,
				'length' => 36,
			]);
			$table->addColumn('disk', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('mbid_group', 'string', [
				'notnull' => false,
				'length' => 36,
			]);
			$table->addColumn('album_artist_id', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('hash', 'string', [
				'notnull' => true,
				'length' => 32,
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
			$table->addIndex(['cover_file_id'], 'ma_cover_file_id_idx');
			$table->addIndex(['album_artist_id'], 'ma_album_artist_id_idx');
			$table->addUniqueIndex(['user_id', 'hash'], 'ma_user_id_hash_idx');
		}

		if (!$schema->hasTable('music_tracks')) {
			$table = $schema->createTable('music_tracks');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('number', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('disk', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('year', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('artist_id', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('album_id', 'integer', [
				'notnull' => false,
			]);
			$table->addColumn('length', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('bitrate', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('mimetype', 'string', [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('mbid', 'string', [
				'notnull' => false,
				'length' => 36,
			]);
			$table->addColumn('starred', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('genre_id', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['artist_id'], 'music_tracks_artist_id_idx');
			$table->addIndex(['album_id'], 'music_tracks_album_id_idx');
			$table->addIndex(['user_id'], 'music_tracks_user_id_idx');
			$table->addUniqueIndex(['file_id', 'user_id'], 'music_tracks_file_user_id_idx');
		}

		if (!$schema->hasTable('music_playlists')) {
			$table = $schema->createTable('music_playlists');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('track_ids', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('comment', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('music_genres')) {
			$table = $schema->createTable('music_genres');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('lower_name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['lower_name', 'user_id'], 'mg_lower_name_user_id_idx');
		}

		if (!$schema->hasTable('music_radio_stations')) {
			$table = $schema->createTable('music_radio_stations');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('stream_url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('home_url', 'string', [
				'notnull' => false,
				'length' => 2048,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('music_ampache_sessions')) {
			$table = $schema->createTable('music_ampache_sessions');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('expiry', 'integer', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['token'], 'music_ampache_sessions_index');
		}

		if (!$schema->hasTable('music_ampache_users')) {
			$table = $schema->createTable('music_ampache_users');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('description', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('hash', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['hash', 'user_id'], 'music_ampache_users_index');
		}

		if (!$schema->hasTable('music_cache')) {
			$table = $schema->createTable('music_cache');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('key', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('data', 'text', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'key'], 'music_cache_index');
		}

		if (!$schema->hasTable('music_bookmarks')) {
			$table = $schema->createTable('music_bookmarks');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('track_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('position', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('comment', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('updated', 'datetime', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'track_id'], 'music_bookmarks_user_track');
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
