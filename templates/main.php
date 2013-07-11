
{{ script('vendor/underscore/underscore.min', 'music') }}
{{ script('vendor/angular/angular.min', 'music') }}
{{ script('vendor/restangular/restangular.min', 'music') }}
{{ script('vendor/md5/md5', 'music') }}
{{ script('public/app', 'appframework') }}
{{ script('public/app') }}
{{ style('style-playerbar') }}
{{ style('style-sidebar') }}
{{ style('style') }}

<div id="app" ng-app="Music" ng-cloak>

	<script type="text/ng-template" id="main.html">
		{{ include('part.main.html') }}
	</script>

	<div id="playerbar" ng-controller="PlayerController">
		<div id="play-controls">
			<img class="control small" alt="{{ trans('Previous') }}" src="{{ image_path('actions/play-previous.svg', 'core') }}" />
			<img ng-click="toggle()" ng-hide="playing" class="control" alt="{{ trans('Play') }}" src="{{ image_path('actions/play-big.svg', 'core') }}" />
			<img ng-click="toggle()" ng-show="playing" class="control" alt="{{ trans('Pause') }}" src="{{ image_path('actions/pause-big.svg', 'core') }}" />
			<img ng-click="next()" class="control small" alt="{{ trans('Next') }}" src="{{ image_path('actions/play-next.svg', 'core') }}" />
		</div>

		<div ng-show="currentAlbum" class="albumart" albumart="[[ currentAlbum.name ]]">[[ currentAlbum.name | minify ]]</div>

		<div class="song-info">
			<span class="title" title="[[ currentTrack.title ]]">[[ currentTrack.title ]]</span><br />
			<span class="artist" title="[[ currentArtist.name ]]">[[ currentArtist.name ]]</span>
		</div>
		<div ng-show="currentTrack.title" class="progress-info">
			<span class="muted">[[ currentTime | playTime ]] / [[ duration | playTime ]]</span>
			<div class="jp-progress">
				<div class="jp-seek-bar">
					<div class="jp-play-bar" style="width: [[ currentTime / duration * 100 ]]%;"></div>
				</div>
			</div>
		</div>
		<div id="player"></div>

		<img id="shuffle" class="control small" alt="{{ trans('Shuffle') }}" src="{{ image_path('shuffle.svg', 'music') }}" />
		<img id="repeat" class="control small" alt="{{ trans('Repeat') }}" src="{{ image_path('repeat.svg', 'music') }}" />
	</div>

	<div id="app-navigation">
		<ul ng-controller="PlaylistController">
			<li><a href="#/">{{ trans('All') }}</a></li>
			<li class="separator-element"><a href="#/">{{ trans('Favorites') }}</a></li>
			<li><a href="#/">{{ trans('New Playlist') }}</a></li>
			<li ng-repeat="playlist in playlists">
				<a href="#/playlist/[[playlist.id]]">[[playlist.name]]</a>
				<img alt="{{ trans('Delete') }}" 	src="{{ image_path('actions/close.svg', 'core') }}" />
			</li>
		</ul>
	</div>

	<div id="app-content" ng-view></div>
</div>
