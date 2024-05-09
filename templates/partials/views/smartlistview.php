<div id="smartlist-view" class="view-container playlist-area flat-list-view" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span translate>Smart playlist</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-big') ?>"/>
		</span>
	</h1>

	<track-list
		ng-if="tracks"
		tracks="tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showTrackDetails"
		get-draggable="getDraggable"
	>
	</track-list>

	<div class="emptycontent clickable no-collapse" ng-click="showSmartListFilters()"
		ng-show="tracks.length == 0 && !scanning && !toScan && !noMusicAvailable"
	>
		<div class="icon-smart-playlist svg"></div>
		<div>
			<h2 translate>No tracks matching the filters</h2>
			<p translate>Refine the filters defined for the smart playlist</p>
		</div>
	</div>

</div>
