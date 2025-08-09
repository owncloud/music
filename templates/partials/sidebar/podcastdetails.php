<div id="podcast-details" class="sidebar-content" ng-controller="PodcastDetailsController" ng-if="contentType=='podcastChannel' || contentType=='podcastEpisode'">

	<div favorite-toggle entity="entity" rest-prefix="restPrefix()"></div>
	<div class="albumart clickable" ng-show="details.image" ng-click="scrollToEntity(contentType, entity)"></div>

	<div class="sidebar-container">
		<dl class="tags" ng-show="details">

			<dt ng-repeat-start="(key, value) in details" ng-if="keyShown(key, value)">{{ formatKey(key) }}</dt>
			<dd ng-if="keyShown(key, value) && keyHasDetails(key)" class="clickable"
				ng-click="showKeyDetails(key, value)">{{ formatValue(key, value) }}<button class="icon-info"></button></dd>
			<dd ng-if="keyShown(key, value) && keyMayCollapse(key)"
				collapsible-html="formatValue(key, value)" on-expand="adjustFixedPositions"></dd>
			<dd ng-repeat-end ng-if="keyShown(key, value) && !keyHasDetails(key) && !keyMayCollapse(key)"
				ng-bind-html="formatValue(key, value)"></dd>

		</dl>
	</div>

	<div class="icon-loading" ng-if="!details"></div>
</div>
