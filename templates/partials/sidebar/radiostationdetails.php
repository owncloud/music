<div id="radio-station-details" class="sidebar-content" ng-controller="RadioStationDetailsController" ng-if="contentType=='radioStation'">

	<h1 ng-show="!station" translate>New radio station</h1>

	<ul class="tabHeaders" ng-show="station">
		<li class="tabHeader" ng-class="{selected: selectedTab=='general'}" ng-click="selectedTab='general'">
			<a translate>Radio station</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='nowPlaying'}" ng-click="selectedTab='nowPlaying'" ng-show="station.metadata.title">
			<a translate>Now playing</a>
		</li>
	</ul>

	<div class="tabsContainer">

		<div class="tab" ng-show="!station || selectedTab=='general'">
			<dl class="tags">
				<dt translate>Name</dt>
				<dd class="clickable" ng-click="startEdit(nameEditor)"
					><span ng-show="!editing">{{ stationName }}<button class="icon-rename"></button></span
					><input ng-show="editing" id="radio-name-editor" ng-ref="nameEditor" type="text" on-enter="commitEdit()" ng-model="stationName" maxlength="256"
				/></dd>

				<dt translate>Stream URL</dt>
				<dd class="clickable" ng-click="startEdit(streamUrlEditor)"
					><span ng-show="!editing">{{ streamUrl }}<button class="icon-rename"></button></span
					><textarea ng-show="editing" ng-ref="streamUrlEditor" type="text" on-enter="commitEdit()" ng-model="streamUrl" maxlength="2048"></textarea
				></dd>

				<dt ng-show="editing"></dt>
				<dd ng-show="editing" class="editor-buttons"
					><button class="action icon-checkmark" ng-click="commitEdit()" ng-class="{ disabled: !streamUrl }"></button
					><button class="action icon-close" ng-click="cancelEdit()"></button
				></dd>

				<dt ng-show="createdDate" translate>Created</dt>
				<dd ng-show="createdDate">{{ createdDate }}</dd>

				<dt ng-show="updatedDate" translate>Modified</dt>
				<dd ng-show="updatedDate">{{ updatedDate }}</dd>

				<dt ng-show="station.metadata.station" translate>Broadcasted name</dt>
				<dd ng-show="station.metadata.station">{{ station.metadata.station }}</dd>

				<dt ng-show="station.metadata.description" translate>Description</dt>
				<dd ng-show="station.metadata.description">{{ station.metadata.description }}</dd>

				<dt ng-show="station.metadata.homepage" translate>Website</dt>
				<dd ng-show="station.metadata.homepage" ng-bind-html="urlToLink(station.metadata.homepage)"></dd>

				<dt ng-show="station.metadata.genre" translate>Genre</dt>
				<dd ng-show="station.metadata.genre">{{ station.metadata.genre }}</dd>

				<dt ng-show="station.metadata.bitrate" translate>Bit rate</dt>
				<dd ng-show="station.metadata.bitrate">{{ station.metadata.bitrate + ' kbps' }}</dd>
			</dl>
		</div>

		<div class="tab" ng-show="station.metadata.title && selectedTab=='nowPlaying'">
			<div ng-if="lastfmCoverUrl" class="albumart clickable" ng-style="{ 'background-image': 'url(' + lastfmCoverUrl + ')' }" ng-click="scrollToEntity('station', station)"></div>
			<dl class="tags">
				<dt ng-show="lastfmTrack" translate>Track</dt>
				<dd ng-show="lastfmTrack" ng-bind-html="lastfmTrack"></dd>

				<dt ng-show="lastfmArtist" translate>Artist</dt>
				<dd ng-show="lastfmArtist" ng-bind-html="lastfmArtist"></dd>

				<dt ng-show="lastfmAlbum" translate>Album</dt>
				<dd ng-show="lastfmAlbum" ng-bind-html="lastfmAlbum"></dd>

				<dt ng-show="lastfmTags" translate>Tags</dt>
				<dd ng-show="lastfmTags" ng-bind-html="lastfmTags"></dd>

				<dt ng-show="lastfmMbid" translate>MusicBrainz</dt>
				<dd ng-show="lastfmMbid" ng-bind-html="lastfmMbid"></dd>
			</dl>
			<p ng-if="lastfmInfo" collapsible-html="lastfmInfo" on-expand="adjustFixedPositions"></p>
		</div>
	</div>

</div>
