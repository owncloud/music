<div id="app-sidebar" ng-controller="SidebarController" class="disappear">
	<a class="close icon-close" alt="{{ 'Close' | translate }}" ng-click="hideSidebar()"></a>

	<div id="app-sidebar-scroll-container">
		<?php
		use OCA\Music\Utility\HtmlUtil;
		HtmlUtil::printNgTemplate('favoritetoggle');
		HtmlUtil::printPartial('sidebar/trackdetails');
		HtmlUtil::printPartial('sidebar/albumdetails');
		HtmlUtil::printPartial('sidebar/artistdetails');
		HtmlUtil::printPartial('sidebar/smartlistfilters');
		HtmlUtil::printPartial('sidebar/playlistdetails');
		HtmlUtil::printPartial('sidebar/radiostationdetails');
		HtmlUtil::printPartial('sidebar/radiohint');
		HtmlUtil::printPartial('sidebar/podcastdetails');
		?>
	</div>

	<div id="follow-playback" class="control toggle small" ng-class="{active: follow}" ng-click="toggleFollow()"
		alt="{{ 'Follow playback' | translate }}" title="{{ 'Follow playback' | translate }}"
	>
		<img class="svg clickable" src="<?php HtmlUtil::printSvgPath('follow-playback') ?>" />
	</div>
</div>
