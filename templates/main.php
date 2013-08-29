
{{ script('placeholder', 'core') }}
{{ script('md5/md5.min', '3rdparty') }}
{{ script('vendor/underscore/underscore.min', 'music') }}
{{ script('vendor/angular/angular.min', 'music') }}
{{ script('vendor/soundmanager/soundmanager2', 'music') }}
{{ script('vendor/restangular/restangular.min', 'music') }}
{{ script('public/app') }}
{{ style('style-playerbar') }}
{{ style('style-sidebar') }}
{{ style('style') }}

<div id="app" ng-app="Music" ng-cloak ng-init="started = false">

	<script type="text/ng-template" id="main.html">
		{{ include('part.main.html') }}
	</script>

	<div id="playerbar" ng-controller="PlayerController" ng-class="{started: started}">
		<div id="play-controls">
			<img class="control small svg" alt="{{ trans('Previous') }}" src="{{ image_path('actions/play-previous.svg', 'core') }}" />
			<img ng-click="toggle()" ng-hide="playing" class="control svg" alt="{{ trans('Play') }}" src="{{ image_path('actions/play-big.svg', 'core') }}" />
			<img ng-click="toggle()" ng-show="playing" class="control svg" alt="{{ trans('Pause') }}" src="{{ image_path('actions/pause-big.svg', 'core') }}" />
			<img ng-click="next()" class="control small svg" alt="{{ trans('Next') }}" src="{{ image_path('actions/play-next.svg', 'core') }}" />
		</div>

		<div ng-show="currentAlbum" class="albumart" albumart="[[ currentAlbum.name ]]"></div>

		<div class="song-info">
			<span class="title" title="[[ currentTrack.title ]]">[[ currentTrack.title ]]</span><br />
			<span class="artist" title="[[ currentArtist.name ]]">[[ currentArtist.name ]]</span>
		</div>
		<div ng-show="currentTrack.title" class="progress-info">
			<span class="muted">[[ position | playTime ]] / [[ duration | playTime ]]</span>
			<div class="progress">
				<div class="seek-bar">
					<div class="play-bar" style="width: [[ position / duration * 100 ]]%;"></div>
				</div>
			</div>
		</div>

		<img id="shuffle" class="control small svg" alt="{{ trans('Shuffle') }}" src="{{ image_path('shuffle.svg', 'music') }}"
			ng-class="{active: shuffle}" ng-click="shuffle=!shuffle" />
		<img id="repeat" class="control small svg" alt="{{ trans('Repeat') }}" src="{{ image_path('repeat.svg', 'music') }}"
			ng-class="{active: repeat}" ng-click="repeat=!repeat" />
	</div>

	<!--<div id="app-navigation">
		<ul ng-controller="PlaylistController">
			<li><a href="#/">{{ trans('All') }}</a></li>
			<li class="app-navigation-separator"><a href="#/">{{ trans('Favorites') }}</a></li>
			<li><a href="#/">+ {{ trans('New Playlist') }}</a></li>
			<li ng-repeat="playlist in playlists">
				<a href="#/playlist/[[playlist.id]]">[[playlist.name]]</a>
				<img alt="{{ trans('Delete') }}" 	src="{{ image_path('actions/close.svg', 'core') }}" />
			</li>
		</ul>
	</div>-->

	<div id="app-content" ng-view ng-class="{started: started}"></div>
</div>
