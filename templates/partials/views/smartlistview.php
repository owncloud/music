<div class="view-container playlist-area flat-list-view" id="smartlist-area" ng-show="!loading && !loadingCollection">
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

</div>
