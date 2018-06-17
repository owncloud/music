<div class="section" id="music-user" ng_show="!loading">
	<h2 translate>Settings</h2>
	<div>
		<label for="music-path" translate>Path to your music collection</label>:
		<input type="text" id="music-path" ng-model="settings.path" ng-click="selectPath()"/>
		<span style="color:red" ng-show="errorPath" translate>Failed to save music path</span>
		<p><em translate>This setting specifies the folder which will be scanned for music.</em></p>
		<p><em translate>Note: When the path is changed, any previously scanned files outside the new path are removed from the collection and any playlists.</em></p>
	</div>
	<div>
		<label for="reset-collection" translate>Reset music collection</label>
		<input type="button" ng-class="{ 'invisible': resetOngoing }" class="icon-delete" id="reset-collection" ng-click="resetCollection()"/>
		<div class="icon-loading-small" ng-class="{ 'invisible': !resetOngoing }" id="reset-in-progress"></div>
		<p><em translate>This action resets all the scanned tracks and all the user-created playlists. After this, the collection can be scanned again from scratch.</em></p>
		<p><em translate>There should usually be no need to do this. In case you find it necessary, you have probably found a bug which should be reported to the <a href="https://github.com/owncloud/music/issues">issues</a>.</em></p>
	</div>

	<h3>Ampache</h3>
	<div class="warning" translate>
		Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href="https://github.com/owncloud/music/issues/60">issue</a>. I would also like to have a list of clients to test with. Thanks
	</div>
	<div>
		<code ng-bind="settings.ampacheUrl"></code><br />
		<em translate>Use this address to browse your music collection from any Ampache compatible player.</em> <em translate>If this URL doesn't work try to append '/server/xml.server.php'.</em>
	</div>
	<div translate>
		Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.
	</div>
	<table id="music-ampache-keys" class="grid" ng-show="settings.ampacheKeys.length">
		<tr class="head">
			<th translate>Description</th>
			<th class="key-action" translate>Revoke API password</th>
		</tr>
		<tr ng-repeat="key in settings.ampacheKeys">
			<td>{{key.description}}</td>
			<td class="key-action"><a ng-class="key.loading ? 'icon-loading-small' : 'icon-delete'" ng-click="removeAPIKey(key)"></a></td>
		</tr>
		<tr id="music-ampache-template-row" class="hidden">
			<td></td>
			<td class="key-action"><a href="#" class="icon-loading-small" data-id=""></a></td>
		</tr>
	</table>
	<div id="music-ampache-form">
		<input type="text" id="music-ampache-description" placeholder="{{ 'Description (e.g. App name)' | translate}}" ng-model="ampacheDescription"/>
		<button translate ng-click="addAPIKey()">Generate API password</button>
		<span style="color:red" ng-show="errorAmpache" translate>Failed to generated new Ampache key</span>
		<div id="music-password-info" class="info" ng_show="ampachePassword">
			<span translate>Use your username and following password to connect to this Ampache instance:</span><br />
			<span class="password" ng-bind="ampachePassword"></span>
		</div>
	</div>

	<h3 translate>About</h3>
	<div>
		<p>
			<img class="logotype" src="<?php p(OCP\Template::image_path('music', 'logo/music_logotype_horizontal.svg')) ?>" />
			<br/>
			<span translate>Music</span> <span>v{{ settings.appVersion }}</span>
		</p>
		<p translate>
			Please report any bugs and issues <a href="https://github.com/owncloud/music/issues"><b>here</b></a>
		</p>
	</div>

</div>
