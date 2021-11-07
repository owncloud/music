<?php use \OCA\Music\Utility\HtmlUtil ?>

<div id="controls" ng-controller="PlayerController" ng-class="{started: started}">
	<div id="play-controls">
		<img ng-click="prev()" class="control small svg" alt="{{ 'Previous' | translate }}"
			src="<?php HtmlUtil::printSvgPath('skip-previous') ?>" />
		<div id="play-pause-container" ng-click="togglePlayback()">
			<div ng-class="playing ? 'icon-pause-big' : 'icon-play-big'" class="control svg"
				alt="{{ playing ? ('Pause' | translate) : ('Play' | translate) }}" id="play-pause-button"
				title="{{ 'press and hold for more' | translate }}" data-long-press-delay="1000">
			</div>
			<div id="play-pause-menu" class="popovermenu bubble" ng-show="playPauseContextMenuVisible">
				<ul>
					<li ng-click="stop()">
						<a class="icon-stop"><span translate>Stop</span></a>
					</li>
				</ul>
			</div>
		</div>
		<img ng-click="next()" class="control small svg" alt="{{ 'Next' | translate }}"
			src="<?php HtmlUtil::printSvgPath('skip-next') ?>" />
	</div>

	<div ng-show="currentTrack.type != 'radio'" ng-click="scrollToCurrentTrack()"
		class="albumart clickable" cover="{{ coverArt() }}"
		albumart="{{ coverArtTitle() }}" title="{{ coverArtTitle() }}" ></div>

	<div ng-show="currentTrack.type == 'radio'" ng-click="scrollToCurrentTrack()" class="icon-radio svg albumart clickable"></div>

	<div class="song-info clickable" ng-click="scrollToCurrentTrack()">
		<span class="title" title="{{ primaryTitle() }}">{{ primaryTitle() }}</span><br />
		<span class="artist" title="{{ secondaryTitle() }}">{{ secondaryTitle() }}</span>
	</div>
	<div ng-show="currentTrack" class="progress-info">
		<span ng-show="!loading" class="muted">{{ position.current | playTime }}</span><span
			ng-show="!loading && durationKnown()" class="muted">/{{ position.total | playTime }}</span>
		<span ng-show="loading" class="muted">Loading...</span>
		<div class="progress">
			<div class="seek-bar" ng-click="seek($event)" ng-style="{'cursor': seekCursorType}">
				<div class="buffer-bar" ng-style="{'width': position.bufferPercent, 'cursor': seekCursorType}"></div>
				<div class="play-bar" ng-show="position.total"
					ng-style="{'width': position.currentPercent, 'cursor': seekCursorType}"></div>
			</div>
		</div>
	</div>

	<img id="shuffle" class="control toggle small svg" alt="{{ 'Shuffle' | translate }}" title="{{ shuffleTooltip() }}"
		src="<?php HtmlUtil::printSvgPath('shuffle') ?>" ng-class="{active: shuffle}" ng-click="toggleShuffle()" />
	<img id="repeat" class="control toggle small svg" alt="{{ 'Repeat' | translate }}" title="{{ repeatTooltip() }}"
		src="{{ repeat=='one' ? '<?php HtmlUtil::printSvgPath('repeat-1') ?>' : '<?php HtmlUtil::printSvgPath('repeat') ?>' }}"
		ng-class="{active: repeat != 'false' }" ng-click="toggleRepeat()" />
	<div class="volume-control" title="{{ 'Volume' | translate }} {{volume}} %">
		<img id="volume-icon" class="control small svg" alt="{{ 'Volume' | translate }}" ng-show="volume > 0"
			src="<?php HtmlUtil::printSvgPath('sound') ?>" />
		<img id="volume-icon" class="control small svg" alt="{{ 'Volume' | translate }}" ng-show="volume == 0"
			src="<?php HtmlUtil::printSvgPath('sound-off') ?>" />
		<input type="range" class="volume-slider" min="0" max="100" ng-model="volume"/>
	</div>
</div>
