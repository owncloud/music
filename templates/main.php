<?php
// scripts
\OCP\Util::addScript('core', 'placeholder');

\OCP\Util::addScript('music', 'vendor/angular/angular.min');
\OCP\Util::addScript('music', 'vendor/angular-bindonce/bindonce.min');
\OCP\Util::addScript('music', 'vendor/angular-route/angular-route.min');
\OCP\Util::addScript('music', 'vendor/angular-scroll/angular-scroll.min');
\OCP\Util::addScript('music', 'vendor/dragdrop/draganddrop.min');
\OCP\Util::addScript('music', 'vendor/soundmanager/script/soundmanager2-nodebug-jsmin');
\OCP\Util::addScript('music', 'vendor/restangular/dist/restangular.min');
\OCP\Util::addScript('music', 'vendor/angular-gettext/dist/angular-gettext.min');
\OCP\Util::addScript('music', 'vendor/aurora/aurora-bundle.min');
\OCP\Util::addScript('music', 'vendor/js-cookie/src/js.cookie');
\OCP\Util::addScript('music', 'public/app');

// stylesheets
\OCP\Util::addStyle('settings', 'settings');
\OCP\Util::addStyle('music', 'style-controls');
\OCP\Util::addStyle('music', 'style-playlist');
\OCP\Util::addStyle('music', 'settings-user');
\OCP\Util::addStyle('music', 'style-sidebar');
\OCP\Util::addStyle('music', 'style');
\OCP\Util::addStyle('music', 'mobile');
\OCP\Util::addStyle('music', 'tablet');

?>


<div id="app" ng-app="Music" ng-cloak ng-init="started = false; lang = '<?php p($_['lang']) ?>'">

	<script type="text/ng-template" id="overview.html">
		<?php print_unescaped($this->inc('partials/overview')) ?>
	</script>
	<script type="text/ng-template" id="playlistview.html">
		<?php print_unescaped($this->inc('partials/playlistview')) ?>
	</script>
	<script type="text/ng-template" id="sidebarlistitem.html">
		<?php print_unescaped($this->inc('partials/sidebarlistitem')) ?>
	</script>
	<script type="text/ng-template" id="settingsview.html">
		<?php print_unescaped($this->inc('partials/settingsview')) ?>
	</script>


	<div ng-controller="MainController">
		<!-- this will be used to display the flash element to give the user a chance to unblock flash -->
		<div id="sm2-container" ng-class="{started: started}"></div>
		<div id="app-navigation" ng-controller="SidebarController">
			<ul>
				<li sidebar-list-item text="'Albums' | translate" destination="'#'"
					title="{{ albumCountText() }}"></li>
				<li sidebar-list-item text="'All tracks' | translate" destination="'#/alltracks'"
					title="{{ trackCountText() }}"></li>
				<li class="app-navigation-separator"></li>
				<li id="new-playlist" class="music-navigation-item">
					<a id="create" class="app-navigation-noclose" ng-click="showCreateForm=!showCreateForm" ng-hide="showCreateForm" translate>+ New Playlist</a>
					<input type="text" class="new-list" ng-show="showCreateForm" 
						placeholder="{{ 'New Playlist' | translate }}" ng-enter="create()" ng-model="newPlaylistName" />
					<div class="actions" ng-show="showCreateForm">
						<button ng-if="newPlaylistName.length > 0" class="svg action icon-checkmark app-navigation-noclose" ng-click="create()"></button>
						<button class="svg action icon-close app-navigation-noclose" ng-click="showCreateForm=!showCreateForm"></button>
					</div>
				</li>
				<li sidebar-list-item
					playlist="playlist" text="playlist.name" destination="'#/playlist/' + playlist.id"
					ng-repeat="playlist in playlists"
					ui-on-drop="dropOnPlaylist($data, playlist)"
					drop-validate="allowDrop(playlist)"
					drag-hover-class="active"
					title="{{ trackCountText(playlist) }}"></li>
				<li class="music-nav-settings" ng-class="{active: $parent.currentView=='#/settings'}">
					<a class="" ng-click="navigateTo('#/settings')">
						{{ 'Settings' | translate }}
					</a>
				</li>
			</ul>
		</div>

		<div id="app-content" du-scroll-container>

			<div id="controls" ng-controller="PlayerController" ng-class="{started: started}">
				<div id="play-controls">
					<img ng-click="prev()" class="control small svg" alt="{{ 'Previous' | translate }}"
						src="<?php p(OCP\Template::image_path('music', 'play-previous.svg')) ?>" />
					<img ng-click="toggle()" ng-hide="playing" class="control svg" alt="{{ 'Play' | translate }}"
						src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>" />
					<img ng-click="toggle()" ng-show="playing" class="control svg" alt="{{ 'Pause' | translate }}"
						src="<?php p(OCP\Template::image_path('music', 'pause-big.svg')) ?>" />
					<img ng-click="next()" class="control small svg" alt="{{ 'Next' | translate }}"
						src="<?php p(OCP\Template::image_path('music', 'play-next.svg')) ?>" />
				</div>


				<div ng-show="currentAlbum" ng-click="scrollToCurrentTrack()"
					class="albumart clickable" cover="{{ currentAlbum.cover }}"
					albumart="{{ currentAlbum.name }}" title="{{ currentAlbum.name }}" ></div>

				<div class="song-info clickable" ng-click="scrollToCurrentTrack()">
					<span class="title" title="{{ currentTrack.title }}">{{ currentTrack.title }}</span><br />
					<span class="artist" title="{{ currentTrack.artistName }}">{{ currentTrack.artistName }}</span>
				</div>
				<div ng-show="currentTrack.title" class="progress-info">
					<span ng-hide="loading" class="muted">{{ position.current | playTime }}/{{ position.total | playTime }}</span>
					<span ng-show="loading" class="muted">Loading...</span>
					<div class="progress">
						<div class="seek-bar" ng-click="seek($event)" ng-style="{'cursor': seekCursorType}">
							<div class="buffer-bar" ng-style="{'width': position.bufferPercent, 'cursor': seekCursorType}"></div>
							<div class="play-bar" ng-show="position.total" 
								ng-style="{'width': position.currentPercent, 'cursor': seekCursorType}"></div>
						</div>
					</div>
				</div>

				<img id="shuffle" class="control small svg" alt="{{ 'Shuffle' | translate }}" title="{{ 'Shuffle' | translate }}"
					src="<?php p(OCP\Template::image_path('music', 'shuffle.svg')) ?>" ng-class="{active: shuffle}" ng-click="toggleShuffle()" />
				<img id="repeat" class="control small svg" alt="{{ 'Repeat' | translate }}" title="{{ 'Repeat' | translate }}"
					src="<?php p(OCP\Template::image_path('music', 'repeat.svg')) ?>" ng-class="{active: repeat}" ng-click="toggleRepeat()" />
				<div class="volume-control" title="{{ 'Volume' | translate }} {{volume}} %">
					<img id="volume-icon" class="control small svg" alt="{{ 'Volume' | translate }}" ng-show="volume > 0"
						src="<?php p(OCP\Template::image_path('music', 'sound.svg')) ?>" />
					<img id="volume-icon" class="control small svg" alt="{{ 'Volume' | translate }}" ng-show="volume == 0"
						src="<?php p(OCP\Template::image_path('music', 'sound-off.svg')) ?>" />
					<input type="range" class="volume-slider" min="0" max="100" ng-model="volume"/>
				</div>
			</div>

			<div id="app-view" ng-view ng-class="{started: started, 'icon-loading': loading || (loadingCollection && currentView!='#/settings')}">
			</div>

			<div id="emptycontent" ng-show="noMusicAvailable && currentView!='#/settings'">
				<div class="icon-audio svg"></div>
				<h2 translate>No music found</h2>
				<p translate>Upload music in the files app to listen to it here</p>
			</div>

			<img id="updateData" ng-show="updateAvailable && currentView!='#/settings'"
				 class="svg clickable" src="<?php p(OCP\Template::image_path('music', 'repeat.svg')) ?>"  ng-click="update()"
				 alt  ="{{ 'New music available. Click here to reload the music library.' | translate }}"
				 title="{{ 'New music available. Click here to reload the music library.' | translate }}" >

			<div id="toScan" ng-show="toScan && currentView!='#/settings'" class="emptycontent clickable" ng-click="processNextScanStep()">
				<div class="icon-audio svg"></div>
				<h2 translate>New music available</h2>
				<p translate>Click here to start the scan</p>
			</div>

			<div id="scanning" class="emptycontent" ng-show="scanning && currentView!='#/settings'">
				<div class="icon-loading svg"></div>
				<h2 translate>Scanning music …</h2>
				<p translate>{{ scanningScanned }} of {{ scanningTotal }}</p>
			</div>
		</div>

	</div>

</div>
