<div id="app-sidebar" ng-controller="DetailsController" class="disappear">
	<a class="close icon-close" alt="{{ 'Close' | translate }}" ng-click="hideSidebar()"></a>

	<div class="albumart"></div>
	<a id="path" title="{{ 'Show in Files' | translate }}">{{ details.path }}</a>

	<ul class="tabHeaders" ng-show="details">
		<li class="tabHeader" ng-class="{selected: selectedTab=='general'}" ng-click="selectedTab='general'">
			<a href="#" translate>General</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='lyrics'}" ng-click="selectedTab='lyrics'" ng-show="details.lyrics">
			<a href="#" translate>Lyrics</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='technical'}" ng-click="selectedTab='technical'">
			<a href="#" translate>Technical</a>
		</li>
	</ul>

	<div class="tabsContainer" ng-show="details">

		<div class="tab" id="generalTabView" ng-show="selectedTab=='general'">
			<dl class="tags">
				<dt ng-repeat-start="tag in details.tags | orderBy:tagRank" ng-if="tag.value">{{ formatDetailName(tag.key) }}</dt>
				<dd ng-repeat-end ng-show="tag.value">{{ formatDetailValue(tag.value) }}</dd>
	
				<dt ng-if="details.length">length</dt>
				<dd ng-if="details.length">{{ details.length | playTime }}</dd>
			</dl>
		</div>

		<div class="tab" id="lyricsTabView" ng-show="selectedTab=='lyrics'">
			<span id="lyrics">{{ formatDetailValue(details.lyrics.unsynced) }}</span>
		</div>

		<div class="tab" id="technicalTabView" ng-show="selectedTab=='technical'">
			<dl class="fileinfo">
				<dt ng-repeat-start="info in details.fileinfo">{{ formatDetailName(info.key) }}</dt>
				<dd ng-repeat-end>{{ formatDetailValue(info.value) }}</dd>
			</dl>
		</div>

	</div>

	<img id="follow-playback" class="control toggle small svg"
		alt="{{ 'Follow playback' | translate }}" title="{{ 'Follow playback' | translate }}"
		src="<?php p(OCP\Template::image_path('music', 'follow-playback.svg')) ?>" ng-class="{active: follow}"
		ng-click="toggleFollow()" />

	<div class="icon-loading" ng-if="!details"></div>
</div>
