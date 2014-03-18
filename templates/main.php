<?php
\OCP\Util::addScript('music', 'vendor/underscore/underscore.min');
\OCP\Util::addScript('music', 'vendor/angular/angular.min');
\OCP\Util::addScript('music', 'vendor/soundmanager/soundmanager2');
\OCP\Util::addScript('music', 'vendor/restangular/restangular.min');
\OCP\Util::addScript('music', 'vendor/angular-gettext/angular-gettext.min');
\OCP\Util::addScript('music', 'public/app');

\OCP\Util::addStyle('music', 'style-playerbar');
\OCP\Util::addStyle('music', 'style-sidebar');
\OCP\Util::addStyle('music', 'style');
\OCP\Util::addStyle('music', 'mobile');
?>

<div id="app" ng-app="Music" ng-cloak ng-init="started = false; lang = '<?php p($_['lang']) ?>'">

	<div ng-controller="MainController">
		<!-- this will be used to display the flash element to give the user a chance to unblock flash -->
		<div id="sm2-container" ng-class="{started: started}"></div>

		<div id="playerbar" ng-controller="PlayerController" ng-class="{started: started}">
			<div id="play-controls">
				<img  ng-click="prev()"class="control small svg" alt="{{'Previous' | translate }}"
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
				<span ng-hide="buffering" class="muted">{{ position | playTime }} / {{ duration | playTime }}</span>
				<span ng-show="buffering" class="muted" translate>Loading ...</span>
				<div class="progress">
					<div class="seek-bar">
						<div class="play-bar" style="width: {{ position / duration * 100 }}%;"></div>
					</div>
				</div>
			</div>

			<img id="shuffle" class="control small svg" alt="{{'Shuffle' | translate }}"
				src="<?php p(OCP\image_path('music', 'shuffle.svg')) ?>" ng-class="{active: shuffle}" ng-click="shuffle=!shuffle" />
			<img id="repeat" class="control small svg" alt="{{'Repeat' | translate }}"
				src="<?php p(OCP\image_path('music', 'repeat.svg')) ?>" ng-class="{active: repeat}" ng-click="repeat=!repeat" />
		</div>

		<!--<div id="app-navigation">
			<ul ng-controller="PlaylistController">
				<li><a href="#/" translate>All</a></li>
				<li class="app-navigation-separator"><a href="#/" translate>Favorites</a></li>
				<li><a href="#/" translate>+ New Playlist</a></li>
				<li ng-repeat="playlist in playlists">
					<a href="#/playlist/{{playlist.id}}">{{playlist.name}}</a>
					<img alt="{{ 'Delete' | translate }}" 	src="<?php p(OCP\image_path('core', 'actions/close.svg')) ?>" />
				</li>
			</ul>
		</div>-->

		<div id="app-content" ng-class="{started: started}">
			<?php print_unescaped($this->inc('part.main')) ?>
		</div>

		<div ng-show="artists" class="alphabet-navigation" ng-class="{started: started}" resize>
			<a scroll-to="{{ letter }}" ng-repeat="letter in letters" ng-class="{available: letterAvailable[letter]}">{{ letter }}</a>
		</div>

	</div>

</div>
