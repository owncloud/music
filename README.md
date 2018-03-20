# README

[![Build Status](https://secure.travis-ci.org/owncloud/music.png)](http://travis-ci.org/owncloud/music)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/music/badges/quality-score.png?s=ddb9090619b6bcf0bf381e87011322dd2514c884)](https://scrutinizer-ci.com/g/owncloud/music/)

## Overview

Music player and server for ownCloud and Nextcloud. Shows audio files stored in your cloud categorized by artists and albums. Supports mp3, and depending on the browser, many other audio formats too. Supports shuffle play and playlists. The application includes an experimental Ampache server.

The full-screen albums view:
![library view](https://user-images.githubusercontent.com/8565946/28543434-6eb12de0-70c8-11e7-966e-65f4c3a90531.png)

Music player embedded into the files view:
![files view](https://user-images.githubusercontent.com/8565946/30036348-2a173c38-91bb-11e7-89fb-39cab9180081.png)

## Supported formats

* FLAC (`audio/flac`)
* MP3 (`audio/mpeg`)
* Vorbis in OGG container (`audio/ogg`)
* Opus in OGG container (`audio/ogg` or `audio/opus`)
* WAV (`audio/wav`)
* M4A (`audio/mp4`)
* M4B (`audio/m4b`)

_Note: The audio formats supported vary depending on the browser. Chrome and Firefox should be able to play all the formats listed above. All browsers should be able to play at least the MP3 files._

_Note: It might be unable to play some particular files on some browsers._


### Detail

This app utilizes 2 backend players: Aurora.js and SoundManager2.

SoundManager2 utilizes the browser's built-in codecs. Aurora.js, on the other hand, uses Javascript and HTML5 Audio API to decode and play music and doesn't require codecs from browser. The Music app ships with FLAC and MP3 plugins for Aurora.js. Aurora.js does not work on any version of Internet Explorer and fails to play some MP3 files on other browsers, too.

The Music app uses SoundManager2 if the browser has a suitable codec available for the file in question and Aurora.js otherwise. In practice, Firefox and Chrome use SoundManager2 for all supported audio formats. Chromium uses Aurora.js for MP3 and FLAC and doesn't play any other formats. Edge uses Aurora.js for FLAC and SoundManager2 for everything else (ogg and m4b not supported). Internet Explorer plays MP3 with SoundManager2 and doesn's play any other formats.

## Usage hints

Normally, the Music app detects any new audio files in the filesystem on application start and scans metadata from those to its database tables when the user clicks the prompt. The Music app also detects file removals and modifications on the background and makes the required database changes automatically.

If the database would somehow get corrupted, the user can force it to be rebuilt by opening the settings (at the bottom of the left pane) and changing the option "Path to your music collection".

### Commands

If preferred, it is also possible to use the command line tool for the database maintenance as described below. This may be quicker than scanning via the web UI in case of large music library, and optionally allows targeting more than one user at once.

Following commands are available(see script occ in your ownCloud root folder):

#### Scan music files

Scan all audio files not already indexed in the database. Extract metadata from those and insert it to the database. Target either specified user(s) or all users.

	./occ music:scan USERNAME1 USERNAME2 ...
	./occ music:scan --all

Both of the above commands can be combined with the `--debug` switch, which enables debug output and shows the memory usage of each scan step.

#### Reset scanned metadata

Reset all data stored to the music database. Target either specified user(s) or all users.

**Warning:** This command will erase user-created data! It will remove all tracks from playlists as playlists are linked against the track metadata.

	./occ music:reset-database USERNAME1 USERNAME2 ...
	./occ music:reset-database --all

#### Reset cache

Music app caches some results for performance reasons. Normally, there should be no reason to reset this cache manually, but it might be desiredable e.g. when running performance tets. Target either specified user(s) or all users.

	./occ music:reset-cache USERNAME1 USERNAME2 ...
	./occ music:reset-cache --all

### Ampache

The URL you need for Ampache is listed in the settings and looks like this:

```
https://cloud.domain.org/index.php/apps/music/ampache/
```

This is the common path. Some clients append the last part (`server/xml.server.php`) automatically. If you have connection problems try the longer version of the URL with the `server/xml.server.php` appended.

#### Authentication

Ampache doesn't use your ownCloud password for authentication. Instead, you need to use a specifically generated APIKEY for Ampache.
The APIKEY is generated through the Music app settings accessible from the link at the bottom of the left pane within the app.
In your Ampache client, use your ownCloud username and the generated key as password.

You may use the `/api/settings/userkey/generate` endpoint to programatically generate a random password. The endpoint expects two parameters, `length` (optional) and `description` (mandatory) and returns a JSON response.
Please note that the minimum password length is 10 characters. The HTTP return codes represent also the status of the request.

```
POST /api/settings/userkey/generate
```

Parameters:

```
{
	"length": <length>,
	"description": <description>
}
```

Response (success):

```
HTTP/1.1 201 Created

{
	"id": <userkey_id>,
	"password": <random_password>,
	"description": <description>
}
```

Response (error - no description provided):

```
HTTP/1.1 400 Bad request

{
	"message": "Please provide a description"
}
```

Response (error - error while saving password):

```
HTTP/1.1 500 Internal Server Error

{
	"message": "Error while saving the credentials"
}
```

### Installation

The Music app can be installed using the App Management in ownCloud. Instructions can be found [here](https://doc.owncloud.org/server/8.1/admin_manual/installation/apps_management_installation.html).

After installation, you may want to select a specific sub-folder containing your music files through the settings of the application. This can be useful to prevent unwanted audio files to be included in the music library.

### Known issues

#### Unshare from self

When the recipient of a shared audio file unshares it, the file reference is left in the music database of the recipient. To get rid of it, the database has to be regenerated. The fix for this has been merged to ownCloud and Nextcloud cores, but it may not yet be included in your release of the cloud. #567

#### Huge music collections

The version 0.4.0 scales better for large music collections than the older versions. Still, if the collection is large enough, it may fail to load. The maximum number of tracks supported depends on your server but should be around 50'000. Also, when there are tens of thousands of tracks, loading the applicatin view takes pretty long time and the responsiveness of the UI may be poor. For the best performance on huge music collections, Firefox 57.0+ (aka "Quantum") is recommended. 

## Development

### L10n hints

Sometimes translatable strings aren't detected. Try to move the `translate` attribute
more to the beginning of the HTML element.

### Build frontend bundle

All the frontend javascript sources of the Music app, excluding the vendor libraries, are bundled into a single file for deployment. The bundle file is js/public/app.js. Generating it requires make and npm utilities, and happens by running:

	cd build
	make

To automatically regenerate the app.js bundle whenever the source .js files change, use

    make watch

### Build appstore package

	git archive HEAD --format=zip --prefix=music/ > build/music.zip

### Install test dependencies

	composer install

### Run tests

PHP unit tests

	vendor/bin/phpunit --coverage-html coverage-html-unit --configuration tests/php/unit/phpunit.xml tests/php/unit

PHP integration tests

	cd ../..          # owncloud core
	./occ maintenance:install --admin-user admin --admin-pass admin --database sqlite
	./occ app:enable music
	cd apps/music
	vendor/bin/phpunit --coverage-html coverage-html-integration --configuration tests/php/integration/phpunit.xml tests/php/integration

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

The Music app implements the [Shiva API](https://shiva.readthedocs.org/en/latest/resources/base.html) except the resources `/artists/<int:artist_id>/shows`, `/tracks/<int:track_id>/lyrics` and the meta resources. You can use this API under `https://own.cloud.example.org/index.php/apps/music/api/`.

Beside those mentioned resources following additional resources are implemented:

* `/api/log`
* `/api/collection`
* `/api/cover/{hash}`
* `/api/file/{fileId}`
* `/api/file/{fileId}/info`
* `/api/file/{fileId}/webdav`
* `/api/file/{fileId}/download`
* `/api/scan`
* `/api/scanstate`
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

Returns all artists with nested albums and each album with nested tracks. The tracks carry file IDs which can be used to obtain WebDAV link for playing with /api/file/{fileId}/webdav.

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
					"disk" : 1,
					"cover": "/index.php/apps/music/api/album/16/cover",
					"id": 16,
					"tracks": [
						{
							"title": "A Dark Passage",
							"number": 21,
							"artistName": "Blind Guardian",
							"artistId": 2,
							"files": {
								"audio/mpeg": 1001
							},
							"id": 202
						},
						{
							"title": "Battle of Sudden Flame",
							"number": 12,
							"artistName": "Blind Guardian",
							"artistId": 2,
							"files": {
								"audio/mpeg": 1002
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
					"disk" : 1,
					"cover": "/index.php/apps/music/api/album/22/cover",
					"id": 22,
					"tracks": [
						{
							"title": "Stay Together for the Kids",
							"number": 1,
							"artistName": "blink-182",
							"artistId": 3,
							"files": {
								"audio/ogg": 1051
							},
							"id": 243
						},
						{
							"title": "The Rock Show (live)",
							"number": 2,
							"artistName": "blink-182",
							"artistId": 3,
							"files": {
								"audio/ogg": 1052
							},
							"id": 244
						}
					]
				}
			]
		}
	]
