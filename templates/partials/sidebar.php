<div id="app-sidebar" ng-controller="SidebarController" class="disappear">
	<a class="close icon-close" alt="{{ 'Close' | translate }}" ng-click="hideSidebar()"></a>

	<?php print_unescaped($this->inc('partials/trackdetails')) ?>
	<?php print_unescaped($this->inc('partials/artistdetails')) ?>
	<?php print_unescaped($this->inc('partials/playlistdetails')) ?>

	<img id="follow-playback" class="control toggle small svg"
		alt="{{ 'Follow playback' | translate }}" title="{{ 'Follow playback' | translate }}"
		src="<?php p(OCP\Template::image_path('music', 'follow-playback.svg')) ?>" ng-class="{active: follow}"
		ng-click="toggleFollow()" />
</div>
