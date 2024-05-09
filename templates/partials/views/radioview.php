<div id="radio-view" class="view-container playlist-area" ng-show="!loading && !loadingRadio">
	<h1>
		<span ng-click="onHeaderClick()">
			<span translate>Internet radio stations</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-big') ?>"/>
		</span>
	</h1>
	<ul class="track-list">
		<li ng-repeat="entry in stations | limitTo: incrementalLoadLimit"
			ng-init="station = entry.track"
			id="{{ 'radio-station-' + station.id }}"
		>
			<div class="playlist-item-info" ng-click="onStationClick($index)" ng-class="{current: getCurrentStationIndex() === $index, playing: playing}">
				<span class="ordinal muted">{{ $index + 1 }}.</span>
				<div class="albumart" albumart="station"></div>
				<div class="play-pause overlay"></div>
				<div class="title-lines">
					<div>{{ station.name }}</div>
					<div class="muted">{{ station.stream_url }}</div>
				</div>
			</div>
			<button class="action icon-details" ng-click="showRadioStationDetails(station)"
				alt="{{ 'Details' | translate }}" title="{{ 'Details' | translate }}"></button>
			<button class="action icon-delete" ng-click="deleteStation(station)" ng-show="!station.busy"
				alt="{{ 'Delete' | translate }}" title="{{ 'Delete' | translate }}"></button>
			<span class="icon-loading-small" ng-show="station.busy"></span>
		</li>
	</ul>

	<alphabet-navigation ng-if="stations && stations.length" item-count="stations.length"
		get-elem-title="getStationTitle" get-elem-id="getStationElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>

	<div id="noStations" class="emptycontent clickable no-collapse" ng-show="stations.length == 0" ng-click="showRadioHint()">
		<div class="icon-radio svg"></div>
		<div>
			<h2 translate>No stations</h2>
			<p translate>Click to show "Getting started"</p>
		</div>
	</div>

</div>
