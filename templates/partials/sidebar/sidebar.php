<div id="app-sidebar" ng-controller="SidebarController" class="disappear">
	<a class="close icon-close" alt="{{ 'Close' | translate }}" ng-click="hideSidebar()"></a>

	<?php
	use \OCA\Music\Utility\HtmlUtil;
	HtmlUtil::printPartial('sidebar/trackdetails');
	HtmlUtil::printPartial('sidebar/albumdetails');
	HtmlUtil::printPartial('sidebar/artistdetails');
	HtmlUtil::printPartial('sidebar/playlistdetails');
	HtmlUtil::printPartial('sidebar/radiostationdetails');
	HtmlUtil::printPartial('sidebar/radiohint');
	HtmlUtil::printPartial('sidebar/podcastdetails');
	?>

	<img id="follow-playback" class="control toggle small svg"
		alt="{{ 'Follow playback' | translate }}" title="{{ 'Follow playback' | translate }}"
		src="<?php HtmlUtil::printSvgPath('follow-playback') ?>" ng-class="{active: follow}"
		ng-click="toggleFollow()" />
</div>
