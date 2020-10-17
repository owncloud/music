<?php

// scripts
\OCP\Util::addScript('music', '../dist/webpack.app');

// stylesheets
\OCP\Util::addStyle('music', '../dist/webpack.app');
\OCP\Util::addStyle('settings', 'settings');

use \OCA\Music\Utility\HtmlUtil;
?>


<div id="app" ng-app="Music" ng-strict-di ng-cloak ng-init="started = false; lang = '<?php HtmlUtil::p($_['lang']) ?>'">

	<?php 
	HtmlUtil::printNgTemplate('albumsview');
	HtmlUtil::printNgTemplate('alltracksview');
	HtmlUtil::printNgTemplate('foldersview');
	HtmlUtil::printNgTemplate('genresview');
	HtmlUtil::printNgTemplate('playlistview');
	HtmlUtil::printNgTemplate('settingsview');
	HtmlUtil::printNgTemplate('alphabetnavigation'); 
	?>

	<div ng-controller="MainController">
		<?php HtmlUtil::printPartial('navigation') ?>

		<div id="app-content">

			<?php
			HtmlUtil::printPartial('controls');
			HtmlUtil::printPartial('sidebar');
			?>

			<div id="app-view" ng-view resize-notifier
				ng-class="{started: started, 'icon-loading': loadIndicatorVisible()}">
			</div>

			<div id="emptycontent" class="emptycontent" ng-show="noMusicAvailable && currentView!='#/settings'">
				<div class="icon-audio svg"></div>
				<div>
					<h2 translate>No music found</h2>
					<p translate>Upload music in the files app to listen to it here</p>
				</div>
			</div>

			<div id="toScan" class="emptycontent clickable" ng-show="toScan && currentView!='#/settings'" ng-click="startScanning()">
				<div class="icon-audio svg"></div>
				<div>
					<h2 translate>New music available</h2>
					<p translate>Click here to start the scan</p>
				</div>
			</div>

			<div id="scanning" class="emptycontent" ng-show="scanning && currentView!='#/settings'">
				<div class="icon-loading svg"></div>
				<div>
					<h2 translate>Scanning music …</h2>
					<p translate>{{ scanningScanned }} of {{ scanningTotal }}</p>
				</div>
			</div>

			<div id="searchContainer" ng-controller="SearchController">
				<div id="searchResultsOmitted" class="emptycontent" ng-show="searchResultsOmitted">
					<div class="icon-search svg"></div>
					<div>
						<h2 translate>Some search results are omitted</h2>
						<p translate>Try to refine the search</p>
					</div>
				</div>
				<div id="noSearchResults" class="emptycontent" ng-show="noSearchResults">
					<div class="icon-search svg"></div>
					<div>
						<h2 translate>
							No search results in this view for <strong>{{ queryString }}</strong>
						</h2>
					</div>
				</div>
			</div>

			<img id="updateData" ng-show="updateAvailable && currentView!='#/settings'"
				 class="svg clickable" src="<?php HtmlUtil::printSvgPath('reload') ?>"  ng-click="update()"
				 alt  ="{{ 'New music available. Click here to reload the music library.' | translate }}"
				 title="{{ 'New music available. Click here to reload the music library.' | translate }}" >

		</div>

		<!-- The following exists just in order to make the core unhide the #searchbox element -->
		<div id="searchresults" data-appfilter="music"></div>
	</div>

</div>
