<?php

declare(strict_types=1);

namespace OCA\Music\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migrate the DB schema to Music v1.3.0 level from any previous version starting from v0.4.0
 */
class Version010300Date20210906093000 extends SimpleMigrationStep {

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

		$this->migrateMusicArtists($schema);
		$this->migrateMusicAlbums($schema);
		$this->migrateMusicTracks($schema);
		$this->migrateMusicPlaylists($schema);
		$this->migrateMusicGenres($schema);
		$this->migrateMusicRadioStations($schema);
		$this->migrateMusicAmpacheSessions($schema);
		$this->migrateAmpacheUsers($schema);
		$this->migrateMusicCache($schema);
		$this->migrateMusicBookmarks($schema);
		$this->migratePodcastChannels($schema);
		$this->migratePodcastEpisodes($schema);

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	private function migrateMusicArtists(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_artists');
		$this->setColumns($table, [
			[ 'id',				'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'name',			'string',	['notnull' => false, 'length' => 256] ],
			[ 'cover_file_id',	'bigint',	['notnull' => false,'length' => 10] ],
			[ 'mbid',			'string',	['notnull' => false, 'length' => 36] ],
			[ 'hash',			'string',	['notnull' => true, 'length' => 32] ],
			[ 'starred',		'datetime',	['notnull' => false] ],
			[ 'created',		'datetime',	['notnull' => false] ],
			[ 'updated',		'datetime',	['notnull' => false] ]
		]);
		$this->dropObsoleteColumn($table, 'image');

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'user_id_hash_idx', ['user_id', 'hash']);
	}

	private function migrateMusicAlbums(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_albums');
		$this->setColumns($table, [
			[ 'id',					'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',			'string',	['notnull' => true, 'length' => 64] ],
			[ 'name',				'string',	['notnull' => false, 'length' => 256] ],
			[ 'cover_file_id',		'bigint',	['notnull' => false, 'length' => 10] ],
			[ 'mbid',				'string',	['notnull' => false, 'length' => 36] ],
			[ 'disk',				'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'mbid_group',			'string',	['notnull' => false, 'length' => 36] ],
			[ 'album_artist_id',	'integer',	['notnull' => false] ],
			[ 'hash',				'string',	['notnull' => true, 'length' => 32] ],
			[ 'starred',			'datetime',	['notnull' => false] ],
			[ 'created',			'datetime',	['notnull' => false] ],
			[ 'updated',			'datetime',	['notnull' => false] ]
		]);
		$this->dropObsoleteColumn($table, 'year');

		$this->setPrimaryKey($table, ['id']);
		$this->setIndex($table, 'ma_cover_file_id_idx', ['cover_file_id']);
		$this->setIndex($table, 'ma_album_artist_id_idx', ['album_artist_id']);
		$this->setUniqueIndex($table, 'ma_user_id_hash_idx', ['user_id', 'hash']);
	}

	private function migrateMusicTracks(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_tracks');
		$this->setColumns($table, [
			[ 'id',			'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true, ] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64, ] ],
			[ 'title',		'string',	['notnull' => true, 'length' => 256, ] ],
			[ 'number',		'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'disk',		'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'year',		'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'artist_id',	'integer',	['notnull' => false] ],
			[ 'album_id',	'integer',	['notnull' => false] ],
			[ 'length',		'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'file_id',	'bigint',	['notnull' => true, 'length' => 10] ],
			[ 'bitrate',	'integer',	['notnull' => false, 'unsigned' => true] ],
			[ 'mimetype',	'string',	['notnull' => true, 'length' => 256] ],
			[ 'mbid',		'string',	['notnull' => false, 'length' => 36] ],
			[ 'starred',	'datetime',	['notnull' => false] ],
			[ 'genre_id',	'integer',	['notnull' => false, 'unsigned' => true, ] ],
			[ 'created',	'datetime', ['notnull' => false] ],
			[ 'updated',	'datetime', ['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setIndex($table, 'music_tracks_artist_id_idx', ['artist_id']);
		$this->setIndex($table, 'music_tracks_album_id_idx', ['album_id']);
		$this->setIndex($table, 'music_tracks_user_id_idx', ['user_id']);
		$this->setUniqueIndex($table, 'music_tracks_file_user_id_idx', ['file_id', 'user_id']);
	}

	private function migrateMusicPlaylists(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_playlists');
		$this->setColumns($table, [
			[ 'id',			'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64] ],
			[ 'name',		'string',	['notnull' => false, 'length' => 256] ],
			[ 'track_ids',	'text',		['notnull' => false] ],
			[ 'created',	'datetime',	['notnull' => false] ],
			[ 'updated',	'datetime',	['notnull' => false] ],
			[ 'comment',	'string',	['notnull' => false, 'length' => 256] ]
		]);

		$this->setPrimaryKey($table, ['id']);
	}

	private function migrateMusicGenres(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_genres');
		$this->setColumns($table, [
			[ 'id',			'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64] ],
			[ 'name',		'string',	['notnull' => false, 'length' => 64] ],
			[ 'lower_name',	'string',	['notnull' => false, 'length' => 64] ],
			[ 'created',	'datetime',	['notnull' => false] ],
			[ 'updated',	'datetime',	['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'mg_lower_name_user_id_idx', ['lower_name', 'user_id']);
	}

	private function migrateMusicRadioStations(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_radio_stations');
		$this->setColumns($table, [
			[ 'id', 		'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64] ],
			[ 'name',		'string',	['notnull' => false, 'length' => 256] ],
			[ 'stream_url',	'string',	['notnull' => true, 'length' => 2048] ],
			[ 'home_url',	'string',	['notnull' => false, 'length' => 2048] ],
			[ 'created',	'datetime',	['notnull' => false] ],
			[ 'updated',	'datetime',	['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
	}

	private function migrateMusicAmpacheSessions(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_ampache_sessions');
		$this->setColumns($table, [
			[ 'id',			'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64] ],
			[ 'token',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'expiry',		'integer',	['notnull' => true,'unsigned' => true] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_ampache_sessions_index', ['token']);
	}

	private function migrateAmpacheUsers(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_ampache_users');
		$this->setColumns($table, [
			[ 'id',				'integer',	['autoincrement' => true, 'notnull' => true] ],
			[ 'user_id',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'description',	'string',	['notnull' => false, 'length' => 64] ],
			[ 'hash',			'string',	['notnull' => true, 'length' => 64] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_ampache_users_index', ['hash', 'user_id']);
	}

	private function migrateMusicCache(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_cache');
		$this->setColumns($table, [
			[ 'id',			'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'key',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'user_id',	'string',	['notnull' => true, 'length' => 64] ],
			[ 'data',		'text',		['notnull' => false] ],
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_cache_index', ['user_id', 'key']);
	}

	private function migrateMusicBookmarks(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_bookmarks');
		$this->setColumns($table, [
			[ 'id',			'integer',		['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',	'string',		['notnull' => true, 'length' => 64] ],
			[ 'type',		'integer',		['notnull' => true] ],
			[ 'entry_id',	'integer',		['notnull' => true] ],
			[ 'position',	'integer',		['notnull' => true] ],
			[ 'comment',	'string',		['notnull' => false, 'length' => 256] ],
			[ 'created',	'datetime',		['notnull' => false] ],
			[ 'updated',	'datetime',		['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_bookmarks_index', ['user_id', 'type', 'entry_id']);

		$this->dropObsoleteIndex($table, 'music_bookmarks_user_track');
		$this->dropObsoleteColumn($table, 'track_id');
	}

	private function migratePodcastChannels(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_podcast_channels');
		$this->setColumns($table, [
			[ 'id',					'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',			'string',	['notnull' => true, 'length' => 64] ],
			[ 'rss_url',			'string',	['notnull' => true, 'length' => 2048] ],
			[ 'rss_hash',			'string',	['notnull' => true, 'length' => 64] ],
			[ 'content_hash',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'update_checked',		'datetime',	['notnull' => true] ],
			[ 'published',			'datetime',	['notnull' => false] ],
			[ 'last_build_date',	'datetime',	['notnull' => false] ],
			[ 'title',				'string',	['notnull' => false, 'length' => 256] ],
			[ 'link_url',			'string',	['notnull' => false, 'length' => 2048] ],
			[ 'language',			'string',	['notnull' => false, 'length' => 32] ],
			[ 'copyright',			'string',	['notnull' => false, 'length' => 256] ],
			[ 'author',				'string',	['notnull' => false, 'length' => 256] ],
			[ 'description',		'text',		['notnull' => false] ],
			[ 'image_url',			'string',	['notnull' => false, 'length' => 2048] ],
			[ 'category',			'string',	['notnull' => false, 'length' => 256] ],
			[ 'starred',			'datetime',	['notnull' => false] ],
			[ 'created',			'datetime',	['notnull' => false] ],
			[ 'updated',			'datetime',	['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_podcast_channels_index', ['rss_hash', 'user_id']);
	}

	private function migratePodcastEpisodes(ISchemaWrapper $schema) {
		$table = $this->getOrCreateTable($schema, 'music_podcast_episodes');
		$this->setColumns($table, [
			[ 'id',				'integer',	['autoincrement' => true, 'notnull' => true, 'unsigned' => true] ],
			[ 'user_id',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'channel_id',		'integer',	['notnull' => true, 'unsigned' => true] ],
			[ 'stream_url',		'string',	['notnull' => false, 'length' => 2048] ],
			[ 'mimetype',		'string',	['notnull' => false, 'length' => 256] ],
			[ 'size',			'integer',	['notnull' => false] ],
			[ 'duration',		'integer',	['notnull' => false] ],
			[ 'guid',			'string',	['notnull' => true, 'length' => 2048] ],
			[ 'guid_hash',		'string',	['notnull' => true, 'length' => 64] ],
			[ 'title',			'string',	['notnull' => false, 'length' => 256] ],
			[ 'episode',		'integer',	['notnull' => false] ],
			[ 'season',			'integer',	['notnull' => false] ],
			[ 'link_url',		'string',	['notnull' => false, 'length' => 2048] ],
			[ 'published',		'datetime',	['notnull' => false] ],
			[ 'keywords',		'string',	['notnull' => false, 'length' => 256] ],
			[ 'copyright',		'string',	['notnull' => false, 'length' => 256] ],
			[ 'author',			'string',	['notnull' => false, 'length' => 256] ],
			[ 'description',	'text',		['notnull' => false] ],
			[ 'starred',		'datetime',	['notnull' => false] ],
			[ 'created',		'datetime',	['notnull' => false] ],
			[ 'updated',		'datetime',	['notnull' => false] ]
		]);

		$this->setPrimaryKey($table, ['id']);
		$this->setUniqueIndex($table, 'music_podcast_episodes_index', ['guid_hash', 'channel_id', 'user_id']);
	}

	private function getOrCreateTable(ISchemaWrapper $schema, string $name) {
		if (!$schema->hasTable($name)) {
			return $schema->createTable($name);
		} else {
			return $schema->getTable($name);
		}
	}

	private function setColumn($table, string $name, string $type, array $args) {
		if (!$table->hasColumn($name)) {
			$table->addColumn($name, $type, $args);
		}
	}

	private function setColumns($table, array $nameTypeArgsPerCol) {
		foreach ($nameTypeArgsPerCol as $nameTypeArgs) {
			list($name, $type, $args) = $nameTypeArgs;
			$this->setColumn($table, $name, $type, $args);
		}
	}

	private function dropObsoleteColumn($table, string $name) {
		if ($table->hasColumn($name)) {
			$table->dropColumn($name);
		}
	}

	private function dropObsoleteIndex($table, string $name) {
		if ($table->hasIndex($name)) {
			$table->dropIndex($name);
		}
	}

	private function setIndex($table, string $name, array $columns) {
		if (!$table->hasIndex($name)) {
			$table->addIndex($columns, $name);
		}
	}

	private function setUniqueIndex($table, string $name, array $columns) {
		if (!$table->hasIndex($name)) {
			$table->addUniqueIndex($columns, $name);
		}
	}

	private function setPrimaryKey($table, array $columns) {
		if (!$table->hasPrimaryKey()) {
			$table->setPrimaryKey($columns);
		}
	}
}
