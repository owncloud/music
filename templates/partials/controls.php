<div id="controls" ng-controller="PlayerController" ng-class="{started: started}">
	<div id="play-controls">
		<img ng-click="prev()" class="control small svg" alt="{{ ::('Previous' | translate) }}"
			src="<?php p(OCP\Template::image_path('music', 'play-previous.svg')) ?>" />
		<img ng-click="toggle()" ng-hide="playing" class="control svg" alt="{{ ::('Play' | translate) }}"
			src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>" />
		<img ng-click="toggle()" ng-show="playing" class="control svg" alt="{{ ::('Pause' | translate) }}"
			src="<?php p(OCP\Template::image_path('music', 'pause-big.svg')) ?>" />
		<img ng-click="next()" class="control small svg" alt="{{ ::('Next' | translate) }}"
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

	<img id="shuffle" class="control toggle small svg" alt="{{ ::('Shuffle' | translate) }}" title="{{ ::('Shuffle' | translate) }}"
		src="<?php p(OCP\Template::image_path('music', 'shuffle.svg')) ?>" ng-class="{active: shuffle}" ng-click="toggleShuffle()" />
	<img id="repeat" class="control toggle small svg" alt="{{ ::('Repeat' | translate) }}" title="{{ ::('Repeat' | translate) }}"
		src="<?php p(OCP\Template::image_path('music', 'repeat.svg')) ?>" ng-class="{active: repeat}" ng-click="toggleRepeat()" />
	<div class="volume-control" title="{{ ::('Volume' | translate) }} {{volume}} %">
		<img id="volume-icon" class="control small svg" alt="{{ ::('Volume' | translate) }}" ng-show="volume > 0"
			src="<?php p(OCP\Template::image_path('music', 'sound.svg')) ?>" />
		<img id="volume-icon" class="control small svg" alt="{{ ::('Volume' | translate) }}" ng-show="volume == 0"
			src="<?php p(OCP\Template::image_path('music', 'sound-off.svg')) ?>" />
		<input type="range" class="volume-slider" min="0" max="100" ng-model="volume"/>
	</div>
</div>
