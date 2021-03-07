<div id="app-sidebar" ng-controller="SidebarController" class="disappear">
	<a class="close icon-close" alt="{{ 'Close' | translate }}" ng-click="hideSidebar()"></a>

	<?php
	use \OCA\Music\Utility\HtmlUtil;
	HtmlUtil::printPartial('trackdetails');
	HtmlUtil::printPartial('albumdetails');
	HtmlUtil::printPartial('artistdetails');
	HtmlUtil::printPartial('playlistdetails');
	HtmlUtil::printPartial('radiostationdetails');
	HtmlUtil::printPartial('radiohint');
	?>

	<img id="follow-playback" class="control toggle small svg"
		alt="{{ 'Follow playback' | translate }}" title="{{ 'Follow playback' | translate }}"
		src="<?php HtmlUtil::printSvgPath('follow-playback') ?>" ng-class="{active: follow}"
		ng-click="toggleFollow()" />
</div>
