<div id="radio-area" class="view-container playlist-area" ng-show="!loading && !loadingRadio">
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
			<div ng-class="{current: getCurrentStationIndex() === $index, playing: playing}">
				<div ng-click="onStationClick($index)">
					<div class="play-pause"></div>
					<span class="muted">{{ $index + 1 }}.</span>
					<div>{{ ::(station.name || station.stream_url) }}</div>
				</div>
				<button class="action icon-delete" ng-click="deleteStation(station)" ng-show="!station.busy"
					alt="{{ 'Delete' | translate }}" title="{{ 'Delete' | translate }}"></button>
				<span class="icon-loading-small" ng-show="station.busy"></span>
			</div>
		</li>
	</ul>

	<div class="emptycontent" ng-show="stations.length == 0">
		<div class="icon-radio svg"></div>
		<div>
			<h2 translate>No stations</h2>
			<p translate>Import stations from PLS or M3U files</p>
		</div>
	</div>

</div>
