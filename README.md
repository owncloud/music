# README

[![Build Status](https://secure.travis-ci.org/owncloud/music.png)](http://travis-ci.org/owncloud/music)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/music/badges/quality-score.png?s=ddb9090619b6bcf0bf381e87011322dd2514c884)](https://scrutinizer-ci.com/g/owncloud/music/)

## Usage hints

### Commands

Following commands are available(see script occ in your ownCloud root folder):

#### Scan music files

	./occ music:scan USERNAME1 USERNAME2 ...

This scans all not scanned music files of the user USERNAME and saves the extracted metadata into the music tables in the database. This is also done if you browse the music app web interface. There the scan is done in steps of 20 tracks and the current state is visible at the bottom of the interface.

	./occ music:scan --all

This scans music files for all users.

Both of the above commands can be combined with the `--debug` switch, which enables debug output and shows the memory usage of each scan step.

#### Reset scanned metadata

**Warning:** This command will delete data! It will remove unavailable tracks from playlists as playlists are linked against the track metadata.

	./occ music:reset-database USERNAME1 USERNAME2 ...

This will reset the scanned metadata of the provided users.

	./occ music:reset-database --all

This will reset the scanned metadata of all users.

### Ampache

In the settings the URL you need for Ampache is listed and looks like this:

```
https://cloud.domain.org/index.php/apps/music/ampache/
```

This is the common path. Some clients append the last part (`server/xml.server.php`) automatically. If you have connection problems try the longer version of the URL with the `server/xml.server.php` appended.

#### Authentication

To use Ampache you can't use your ownCloud password. Instead, you need to generate APIKEY for Ampache.
Go to "Your username" → "Personal", and check section Music/Ampache, where you can generate your key. Enter your ownCloud username and the generated key as password to your client.

### Installation

Music App can be installed from [Appstore](http://apps.owncloud.com/) by following the instructions [here](http://doc.owncloud.org/server/8.0/user_manual/installing_apps.html) or using App Management in ownCloud with instructions written [here](http://doc.owncloud.org/server/8.0/admin_manual/configuration/configuration_apps.html).

### Known issues

#### Huge music collections

The current version doesn't scale well for huge music collections. There are plans for a kind of paginated version, which hides the pagination and should be useable as known before. #78

#### Application can not be activated because of illegal code

The current music app can't be installed and ownCloud prints following error message:
"Application can not be activated because of illegal code". This is due to the appcodechecker in core (which is kind of broken), but you can do the installation if the appcodechecker is deactivated:

* set `appcodechecker` to `false` in `config.php` (see the [config.sample.php](https://github.com/owncloud/core/blob/a8861c70c8e5876a961f00e49db88843432bf7ba/config/config.sample.php#L164) )
* now you can install the app
* afterwards re-enable the appcodechecker

## Development

### L10n hints

Sometimes translatable strings aren't detected. Try to move the `translate` attribute
more to the beginning of the HTML element.

### Build appstore package

	git archive HEAD --format=zip --prefix=music/ > build/music.zip

### Install test dependencies

	composer install

### Run tests

PHP tests

	phpunit tests/php
	phpunit --coverage-html coverage-html tests/php

Behat acceptance tests

	cd tests
	cp behat.yml.dist behat.yml
	# add credentials for Ampache API to behat.yml
	../vendor/bin/behat

For the acceptance tests you need to upload all tracks of the following 3 artists:

* https://www.jamendo.com/de/artist/435725/simon-bowman
* https://www.jamendo.com/de/artist/351716/diablo-swing-orchestra
* https://www.jamendo.com/de/artist/3573/pascalb-pascal-boiseau

### 3rdparty libs

update JavaScript libraries

	cd js
	bower update

## API

The music app implements the [Shiva API](https://shiva.readthedocs.org/en/latest/resources/base.html) except the resources `/artists/<int:artist_id>/shows`, `/tracks/<int:track_id>/lyrics` and the meta resources. You can use this API under `https://own.cloud.example.org/index.php/apps/music/api/`.

Beside those mentioned resources following additional resources are implemented:

* `/api/log`
* `/api/collection`
* `/api/file/{fileId}`
* `/api/scan`
* Playlist API at `/api/playlist/`
* Settings API at `/api/settings`
* [Ampache API](https://github.com/ampache/ampache/wiki/XML-API) at `/ampache/server/xml.server.php`

### `/api/log`

Allows to log a message to ownCloud defined log system.

	POST /api/log

Parameters:

	{
		"message": "The message to log"
	}

Response:

	{
		"success": true
	}


### `/api/collection`

Returns all artists with nested albums and each album with nested tracks.

	GET /api/collection

Response:

	[
		{
			"id": 2,
			"name": "Blind Guardian",
			"albums": [
				{
					"name": "Nightfall in Middle-Earth",
					"year": 1998,
					"cover": "/index.php/apps/music/api/album/16/cover",
					"id": 16,
					"tracks": [
						{
							"title": "A Dark Passage",
							"number": 21,
							"artistId": 2,
							"albumId": 16,
							"files": {
								"audio/mpeg": "https://own.cloud.example.org/remote.php/webdav/Blind Guardian/1998 - Nightfall in Middle-Earth/21 - A Dark Passage.mp3"
							},
							"id": 202
						},
						{
							"title": "Battle of Sudden Flame",
							"number": 12,
							"artistId": 2,
							"albumId": 16,
							"files": {
								"audio/mpeg": "https://own.cloud.example.org/remote.php/webdav/Blind Guardian/1998 - Nightfall in Middle-Earth/12 - Battle of Sudden Flame.mp3"
							},
							"id": 212
						}
					]
				}
			]
		},
		{
			"id": 3,
			"name": "blink-182",
			"albums": [
				{
					"name": "Stay Together for the Kids",
					"year": 2002,
					"cover": "/index.php/apps/music/api/album/22/cover",
					"id": 22,
					"tracks": [
						{
							"title": "Stay Together for the Kids",
							"number": 1,
							"artistId": 3,
							"albumId": 22,
							"files": {
								"audio/mpeg": "https://own.cloud.example.org/remote.php/webdav/blink-182/2002 - Stay Together for the Kids/01 - Stay Together for the Kids.mp3"
							},
							"id": 243
						},
						{
							"title": "The Rock Show (live)",
							"number": 2,
							"artistId": 3,
							"albumId": 22,
							"files": {
								"audio/mpeg": "https://own.cloud.example.org/remote.php/webdav/blink-182/2002 - Stay Together for the Kids/02 - The Rock Show (live).mp3"
							},
							"id": 244
						}
					]
				}
			]
		}
	]
