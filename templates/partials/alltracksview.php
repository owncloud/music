<div class="view-container playlist-area" id="alltracks-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span translate>All tracks</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
		</span>
	</h1>
	<track-list ng-if="tracks"
		tracks="tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showSidebar"
		get-draggable="getDraggable"
		details-text="'Details' | translate">
	</track-list>

	<alphabet-navigation ng-if="tracks && tracks.length" item-count="tracks.length"
		get-elem-title="getTrackArtistName" get-elem-id="getTrackElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
