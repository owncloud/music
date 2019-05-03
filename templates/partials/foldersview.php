<div class="playlist-area" id="folders-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span translate>Folders</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
		</span>
	</h1>
	<!-- track-list ng-if="tracks"
		tracks="tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showSidebar"
		get-draggable="getDraggable"
		details-text="'Details' | translate">
	</track-list-->

</div>
