<?php

use OCA\Music\Utility\HtmlUtil;

// add the webpack assets, containing all the .js and .css for the Music app
HtmlUtil::addWebpackScript('app');
HtmlUtil::addWebpackStyle('app');

?>


<div id="app" ng-app="Music" ng-strict-di ng-cloak ng-init="started = false; lang = '<?php HtmlUtil::p($_['lang']) ?>'">

	<?php
	HtmlUtil::printNgTemplate('views/albumsview');
	HtmlUtil::printNgTemplate('views/alltracksview');
	HtmlUtil::printNgTemplate('views/smartlistview');
	HtmlUtil::printNgTemplate('views/foldersview');
	HtmlUtil::printNgTemplate('views/genresview');
	HtmlUtil::printNgTemplate('views/playlistview');
	HtmlUtil::printNgTemplate('views/radioview');
	HtmlUtil::printNgTemplate('views/podcastsview');
	HtmlUtil::printNgTemplate('views/advancedsearchview');
	HtmlUtil::printNgTemplate('views/settingsview');
	HtmlUtil::printNgTemplate('alphabetnavigation');
	HtmlUtil::printNgTemplate('foldertreenode');
	?>

	<div ng-controller="MainController">
		<?php HtmlUtil::printPartial('navigation') ?>

		<div id="app-content">

			<?php
			HtmlUtil::printPartial('controls');
			HtmlUtil::printPartial('sidebar/sidebar');
			?>

			<div id="app-view" ng-view resize-notifier
				ng-class="{started: started, 'icon-loading': loadIndicatorVisible()}">
			</div>

			<div id="emptycontent" class="emptycontent" ng-show="noMusicAvailable && viewingLibrary()">
				<div class="icon-audio svg"></div>
				<div>
					<h2 translate>No music found</h2>
					<p translate>Upload music in the files app to listen to it here</p>
				</div>
			</div>

			<div id="toScan" class="emptycontent clickable" ng-show="!scanning && unscannedFiles.length && viewingLibrary()" ng-click="startScanning(unscannedFiles)">
				<div class="icon-audio svg"></div>
				<div>
					<h2 translate>New music available</h2>
					<p translate>Click here to start the scan</p>
				</div>
			</div>

			<div id="toRescan" class="emptycontent clickable" ng-show="!scanning && !unscannedFiles.length && dirtyFiles.length && viewingLibrary()" ng-click="startScanning(dirtyFiles)">
				<div class="icon-audio svg"></div>
				<div>
					<h2 translate>Some of the previously scanned files may have changed</h2>
					<p translate>Click here to rescan these files</p>
				</div>
			</div>

			<div id="scanning" class="emptycontent" ng-show="scanning && viewingLibrary()">
				<div class="icon-loading svg"></div>
				<div>
					<h2 translate>Scanning music …</h2>
					<p translate>{{ scanningScanned }} of {{ scanningTotal }}</p>
				</div>
			</div>

			<div id="searchContainer" ng-controller="SearchController">
				<div id="searchResultsOmitted" class="emptycontent" ng-show="searchResultsOmitted">
					<div class="icon-search"></div>
					<div>
						<h2 translate>Some search results are omitted</h2>
						<p translate>Try to refine the search</p>
					</div>
				</div>
				<div id="noSearchResults" class="emptycontent no-collapse" ng-show="noSearchResults">
					<div class="icon-search"></div>
					<div>
						<h2 translate>
							No search results in this view for <strong>{{ queryString }}</strong>
						</h2>
					</div>
				</div>
			</div>

			<img id="updateData" ng-show="updateAvailable && currentView!='#/settings'"
				 class="svg clickable" src="<?php HtmlUtil::printSvgPath('reload') ?>" ng-click="update()"
				 alt  ="{{ 'New music available. Click here to reload the music library.' | translate }}"
				 title="{{ 'New music available. Click here to reload the music library.' | translate }}" >

		</div>

	</div>

</div>
