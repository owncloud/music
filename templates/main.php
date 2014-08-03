<?php
\OCP\Util::addScript('core', 'placeholder');
\OCP\Util::addScript('3rdparty', 'md5/md5.min');
\OCP\Util::addScript('music', 'vendor/underscore/underscore-min');
\OCP\Util::addScript('music', 'vendor/angular/angular.min');
\OCP\Util::addScript('music', 'vendor/angular-route/angular-route.min');
\OCP\Util::addScript('music', 'vendor/dragdrop/draganddrop');
\OCP\Util::addScript('music', 'vendor/soundmanager/script/soundmanager2-nodebug-jsmin');
\OCP\Util::addScript('music', 'vendor/restangular/dist/restangular.min');
\OCP\Util::addScript('music', 'vendor/angular-gettext/dist/angular-gettext.min');
\OCP\Util::addScript('music', 'public/app');

\OCP\Util::addStyle('music', 'style-playerbar');
\OCP\Util::addStyle('music', 'style-sidebar');
\OCP\Util::addStyle('music', 'style');
\OCP\Util::addStyle('music', 'mobile');
\OCP\Util::addStyle('music', 'tablet');
\OCP\Util::addStyle('music', 'stable6+-fixes');
?>


<div id="app" ng-app="Music" ng-cloak ng-init="started = false; lang = '<?php p($_['lang']) ?>'">

	<script type="text/ng-template" id="overview.html">
		<?php print_unescaped($this->inc('partials/overview')) ?>
	</script>
	<script type="text/ng-template" id="plsongs.html">
		<?php print_unescaped($this->inc('partials/plsongs')) ?>
	</script>

	<div ng-controller="MainController">
		<!-- this will be used to display the flash element to give the user a chance to unblock flash -->
		<div id="sm2-container" ng-class="{started: started}"></div>

		<div id="playerbar" ng-controller="PlayerController" ng-class="{started: started}">
			<div id="play-controls">
				<img ng-click="prev()" class="control small svg" alt="{{'Previous' | translate }}"
					src="<?php p(OCP\image_path('music', 'play-previous.svg')) ?>" />
				<img ng-click="toggle()" ng-hide="playing" class="control svg" alt="{{'Play' | translate }}"
					src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" />
				<img ng-click="toggle()" ng-show="playing" class="control svg" alt="{{'Pause' | translate }}"
					src="<?php p(OCP\image_path('music', 'pause-big.svg')) ?>" />
				<img ng-click="next()" class="control small svg" alt="{{'Next' | translate }}"
					src="<?php p(OCP\image_path('music', 'play-next.svg')) ?>" />
			</div>


			<div ng-show="currentAlbum" class="albumart" cover="{{ currentAlbum.cover }}"
				albumart="{{ currentAlbum.name }}" title="{{ currentAlbum.name }}" ></div>

			<div class="song-info">
				<span class="title" title="{{ currentTrack.title }}">{{ currentTrack.title }}</span><br />
				<span class="artist" title="{{ currentArtist.name }}">{{ currentArtist.name }}</span>
			</div>
			<div ng-show="currentTrack.title" class="progress-info">
				<span ng-hide="buffering" class="play-position muted">&nbsp;</span>
				<span ng-show="buffering" class="muted" translate>Loading ...</span>
				<div class="progress">
					<div class="seek-bar" ng-click="seek($event)">
						<div class="buffer-bar"></div>
						<div class="play-bar"></div>
					</div>
				</div>
			</div>

			<img id="shuffle" class="control small svg" alt="{{'Shuffle' | translate }}"
				src="<?php p(OCP\image_path('music', 'shuffle.svg')) ?>" ng-class="{active: shuffle}" ng-click="shuffle=!shuffle" />
			<img id="repeat" class="control small svg" alt="{{'Repeat' | translate }}"
				src="<?php p(OCP\image_path('music', 'repeat.svg')) ?>" ng-class="{active: repeat}" ng-click="repeat=!repeat" />
		</div>

		<div id="app-navigation">
			<ul ng-controller="PlaylistController" ng-init="showForm = false">
				<li><a href="#/" translate>All</a></li>
				<li class="app-navigation-separator"><a href="#/" translate>Favorites</a></li>
				<li ng-hide="showForm"><a href="" id="create" ng-click="showForm = !showForm" translate>+ New Playlist</a></li>
				<form name="newPlaylistForm" id="newPlaylistForm" ng-show="showForm">
					<li id="new-playlist">
						<input type="text" placeholder="New Playlist" ng-model="newPlaylistForm.name" />
						<input class="primary icon-checkmark-white" type="button" ng-click="createPlaylist(newPlaylistForm); showForm = !showForm">
					</li>
					<li style="padding: 6px 12px;" ng-show="showForm"><pre>{{ newPlaylistForm | json }}</pre></li>
				<!-- debug end -->
				</form>
				<div ng-repeat="playlist in playlists" ui-on-Drop="dropSong($event, $data, playlist.id)">
					<a ng-hide="editorEnabled" href="#/playlist/{{playlist.id}}">{{playlist.name}}</a>
					<div ng-if="editorEnabled">
					      <input ng-model="playlist.name" /><button class="svg action icon-checkmark" ng-click="editorEnabled=!editorEnabled; updatePlaylist(playlist.id, playlist.name)"></button>
					</div>
					<button class="svg action edit-icon" ng-hide="editorEnabled" ng-click="editorEnabled=!editorEnabled"></button>
					<button ng-hide="editorEnabled" class="svg action delete-icon" ng-click="removePlaylist(playlist.id)" title="" oc-tooltip="" data-original-title=""></button>
				</div>
			</ul>
		</div>

		<div id="app-content" ng-view ng-class="{started: started, 'icon-loading': loading}">
		</div>

		<div ng-show="artists" class="alphabet-navigation" ng-class="{started: started}" resize>
			<a scroll-to="{{ letter }}" ng-repeat="letter in letters" ng-class="{available: letterAvailable[letter], filler: ($index % 2) == 1}">
				<span class="letter-content">{{ letter }}</span>
			</a>
		</div>

	</div>

</div>
