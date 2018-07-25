<div id="app-sidebar" ng-controller="DetailsController" class="disappear">
	<a class="close icon-close" alt="{{ ::('Close' | translate) }}" ng-click="hideSidebar()"></a>

	<div class="albumart"></div>
	<a id="path" title="{{ ::('Show in Files' | translate) }}">{{ details.path }}</a>
	<dl class="tags">
		<dt ng-repeat-start="tag in details.tags | orderBy:tagRank" ng-if="tag.value">{{ formatDetailName(tag.key) }}</dt>
		<dd ng-repeat-end ng-if="tag.value">{{ formatDetailValue(tag.value) }}</dd>

		<dt ng-if="details.length">length</dt>
		<dd ng-if="details.length">{{ details.length | playTime }}</dd>
	</dl>
	<dl class="fileinfo clickable" ng-click="toggleFormatExpanded()" ng-if="formatSummary"
		title="{{ formatExpanded ? 'Collapse' : 'Expand' | translate }}">
		<dt ng-if="!formatExpanded">dataformat</dt>
		<dd ng-if="!formatExpanded">{{ formatSummary }}</dd>

		<dt ng-if="formatExpanded" ng-repeat-start="info in details.fileinfo">{{ formatDetailName(info.key) }}</dt>
		<dd ng-if="formatExpanded" ng-repeat-end>{{ formatDetailValue(info.value) }}</dd>
	</dl>

	<img id="follow-playback" class="control toggle small svg"
		alt="{{ ::('Follow playback' | translate) }}" title="{{ ::('Follow playback' | translate) }}"
		src="<?php p(OCP\Template::image_path('music', 'follow-playback.svg')) ?>" ng-class="{active: follow}"
		ng-click="toggleFollow()" />

	<div class="icon-loading" ng-if="!details"></div>
</div>
