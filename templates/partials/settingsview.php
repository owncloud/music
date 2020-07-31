<div class="view-container" id="music-user" ng-show="!loading">
	<h1 translate>Settings</h1>
	<div>
		<div class="label-container">
			<label for="music-path" translate>Path to your music collection</label>:
		</div>
		<div class="icon-loading-small" ng-show="pathChangeOngoing" id="path-change-in-progress"></div>
		<input type="text" id="music-path" ng-class="{ 'invisible': pathChangeOngoing }"
			ng-model="settings.path" ng-click="selectPath()"/>
		<span style="color:red" ng-show="errorPath" translate>Failed to save music path</span>
		<p><em translate>This setting specifies the folder which will be scanned for music.</em></p>
		<p><em translate>Note: When the path is changed, any previously scanned files outside the new path are removed from the collection and any playlists.</em></p>
	</div>
	<div>
		<div class="label-container">
			<label for="reset-collection" translate>Reset music collection</label>
		</div>
		<div class="icon-loading-small" ng-show="resetOngoing" id="reset-in-progress"></div>
		<input type="button" ng-class="{ 'invisible': resetOngoing }" class="icon-delete"
			id="reset-collection" ng-click="resetCollection()"/>
		<p><em translate>This action resets all the scanned tracks and all the user-created playlists. After this, the collection can be scanned again from scratch.</em></p>
		<p><em translate>There should usually be no need to do this. In case you find it necessary, you have probably found a bug which should be reported to the <a href="{{issueTrackerUrl}}" target="_blank">issues</a>.</em></p>
	</div>

	<h2 translate>Ampache and Subsonic</h2>
	<div translate>You can browse and play your music collection from external applications which support either Ampache or Subsonic API.</div>
	<div class="warning" translate>
		Note that Music may not be compatible with all Ampache/Subsonic clients. Check the verified <a href="{{ampacheClientsUrl}}" target="_blank">Ampache clients</a> and <a href="{{subsonicClientsUrl}}" target="_blank">Subsonic clients</a>.
	</div>
	<div>
		<code id="ampache-url" ng-bind="settings.ampacheUrl"></code>
		<a class="clipboardButton icon icon-clippy" ng-click="copyToClipboard('ampache-url')"></a><br />
		<em translate>Use this address to browse your music collection from an Ampache compatible player.</em> <em translate>If this URL doesn't work try to append '/server/xml.server.php'.</em>
	</div>
	<div>
		<code id="subsonic-url" ng-bind="settings.subsonicUrl"></code>
		<a class="clipboardButton icon icon-clippy" ng-click="copyToClipboard('subsonic-url')"></a><br />
		<em translate>Use this address to browse your music collection from a Subsonic compatible player.</em>
	</div>
	<div translate>
		Here you can generate passwords to use with the Ampache or Subsonic API. Separate passwords are used because they can't be stored in a really secure way due to the design of the APIs. You can generate as many passwords as you want and revoke them at anytime.
	</div>
	<table id="music-ampache-keys" class="grid" ng-show="settings.ampacheKeys.length">
		<tr class="head">
			<th translate>Description</th>
			<th class="key-action" translate>Revoke API password</th>
		</tr>
		<tr ng-repeat="key in settings.ampacheKeys">
			<td>{{key.description}}</td>
			<td class="key-action"><a class="icon" ng-class="key.loading ? 'icon-loading-small' : 'icon-delete'" ng-click="removeAPIKey(key)"></a></td>
		</tr>
		<tr id="music-ampache-template-row" class="hidden">
			<td></td>
			<td class="key-action"><a class="icon icon-loading-small" data-id=""></a></td>
		</tr>
	</table>
	<div id="music-ampache-form">
		<input type="text" id="music-ampache-description" ng-model="ampacheDescription"
			placeholder="{{ 'Description (e.g. App name)' | translate }}" ng-enter="addAPIKey()"/>
		<button translate ng-click="addAPIKey()">Generate API password</button>
		<span style="color:red" ng-show="errorAmpache" translate>Failed to generate new Ampache/Subsonic password</span>
		<div id="music-password-info" class="info" ng-show="ampachePassword">
			<span translate>Use the following credentials to connect to this Ampache/Subsonic instance.</span>
			<dl>
				<dt translate>Username:</dt>
				<dd>{{ settings.user }}</dd>
				<dt translate>Password:</dt>
				<dd><span id="pw-label">{{ ampachePassword }}</span><a class="clipboardButton icon icon-clippy" ng-click="copyToClipboard('pw-label')"></a></dd>
			</dl>
		</div>
	</div>

	<h2 translate>Admin</h2>
	<div class="clickable" ng-show="!showAdmin" ng-click="showAdmin=true" translate>Show...</div>
	<div ng-show="showAdmin">
		<p translate translate-params-filename="'<cloud root>/config/config.php'">
			There is no settings UI for the server-wide settings of the Music app but some settings are available by adding specific key-value pairs to the file <samp>{{filename}}</samp>. The available keys are described below.
		</p>
		<div>
			<p>music.lastfm_api_key</p>
			<p><em translate translate-params-url="'https://www.last.fm/api/account/create'">
				To see the artist biography from Last.fm in the details view, you need to create an API account with Last.fm. For this, use the <a href="{{url}}" target="_blank">Last.fm form</a>. Only 'Contact email' and 'Application name' need to be filled in the form. You are then provided with an API key which should be used as a value for this key.
			</em></p>
		</div>
		<div>
			<p>music.cover_size</p>
			<p><em translate>
				Large album cover images are down-scaled to this size on the server before providing them for the web browser or the Subsonic/Ampache client. Smaller images are not up-scaled. The default size is 380 pixels. The value should be given as a single integer.
			</em></p>
		</div>
	</div>

	<h2 translate>About</h2>
	<div>
		<p>
			<img class="logotype" src="<?php p(OCP\Template::image_path('music', 'logo/music_logotype_horizontal.svg')) ?>" />
			<br/>
			<span translate>Music</span> <span>v{{ settings.appVersion }}</span>
			(<a href="https://github.com/owncloud/music/releases" target="_blank" translate>version history</a>)
		</p>
		<p translate>
			Please report any bugs and issues to the <a href="{{issueTrackerUrl}}" target="_blank">issue tracker</a>
		</p>
	</div>

</div>
