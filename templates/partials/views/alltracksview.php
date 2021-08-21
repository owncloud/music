<div class="view-container playlist-area flat-list-view" id="alltracks-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span translate>All tracks</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-big') ?>"/>
		</span>
	</h1>

	<div class="track-bucket"
		ng-if="trackBuckets"
		ng-repeat="bucket in trackBuckets"
		ng-class="::('track-bucket-' + bucket.char)"
		in-view-observer
		in-view-observer-margin="3000"
		id="{{ ::('track-bucket-' + bucket.id) }}"
	>
		<h2 ng-if="::bucket.firstForChar">{{ ::bucket.char }}</h2>
		<track-list
			tracks="bucket.tracks"
			get-track-data="getTrackData"
			play-track="onTrackClick"
			show-track-details="showTrackDetails"
			get-draggable="getDraggable"
		>
		</track-list>
	</div>

	<alphabet-navigation ng-if="trackBuckets && trackBuckets.length" item-count="trackBuckets.length"
		get-elem-title="getBucketName" get-elem-id="getBucketElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
