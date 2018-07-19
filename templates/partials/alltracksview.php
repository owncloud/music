<div class="playlist-area" id="alltracks-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="playAll()">
			<span translate>All tracks</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
		</span>
	</h1>
	<track-list ng-if="tracks"
		tracks="tracks"
		get-track-data="getTrackData"
		play-track="playTrack"
		show-track-details="showSidebar"
		get-draggable="getDraggable"
		details-text="'Details' | translate"
	/>
</div>
