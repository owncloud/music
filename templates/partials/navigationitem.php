<li class="music-navigation-item"
	ng-class="{	'active': $parent.currentView == destination,
				'menu-open': (playlist && playlist == $parent.popupShownForPlaylist)
							|| (destination == '#/radio' && $parent.popupShownForPlaylist == 'radio')
							|| (destination == '#/podcasts' && $parent.popupShownForPlaylist == 'podcasts')
							|| (destination == '#' && $parent.popupShownForPlaylist == 'albums'),
				'item-with-actions': playlist || destination=='#/radio' || destination=='#/podcasts' || destination=='#' || destination=='#/folders' }"
>
	<div class="music-navigation-item-content" ng-click="$parent.navigateTo(destination)"
		ng-class="{current: $parent.playingView == destination, playing: $parent.playing}"
	>
		<div class="play-pause-button svg" ng-hide="playlist && $parent.showEditForm == playlist.id"
			ng-class="icon ? 'icon-' + icon : ''"
			ng-click="$parent.togglePlay(destination, playlist); $event.stopPropagation()"
			title="{{ (($parent.playingView == destination && $parent.playing) ? 'Pause' : 'Play') | translate }}"
		>
			<div class="play-pause"></div>
		</div>
		<span ng-hide="playlist && $parent.showEditForm == playlist.id">{{ text }}</span>
		<div ng-show="playlist && $parent.showEditForm == playlist.id">
			<div class="input-container">
				<input type="text" class="edit-list" maxlength="256"
					ng-enter="$parent.$parent.commitEdit(playlist)" ng-model="playlist.name"/>
			</div>
			<button class="action icon-checkmark app-navigation-noclose"
				ng-class="{ disabled: playlist.name.length == 0 }"
				ng-click="$parent.$parent.commitEdit(playlist); $event.stopPropagation()"></button>
		</div>
		<div class="actions" ng-init="subMenuShown = false" title="" ng-show="playlist && $parent.showEditForm == null">
			<span class="icon-more" ng-show="!playlist.busy"
				ng-click="$parent.$parent.onPlaylistMoreButton(playlist); subMenuShown = false; $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="playlist.busy"></span>
			<div class="popovermenu bubble" ng-show="$parent.$parent.popupShownForPlaylist == playlist">
				<ul>
					<li ng-click="$parent.$parent.showDetails(playlist)">
						<a class="icon-details"><span translate>Details</span></a>
					</li>
					<li ng-click="$parent.$parent.startEdit(playlist)" class="app-navigation-noclose">
						<a class="icon-rename"><span translate>Rename</span></a>
					</li>
					<li ng-click="$parent.$parent.importFromFile(playlist)">
						<a class="icon-from-file"><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.$parent.exportToFile(playlist)">
						<a class="icon-to-file"><span translate>Export to file</span></a>
					</li>
					<li ng-click="subMenuShown = !subMenuShown; $event.stopPropagation()">
						<a class="icon-sort-by-alpha"><span translate>Sort …</span></a>
						<div class="popovermenu bubble submenu" ng-show="subMenuShown">
							<ul>
								<li ng-click="$parent.$parent.sortPlaylist(playlist, 'track')">
									<a><span translate>by title</span></a>
								</li>
								<li ng-click="$parent.$parent.sortPlaylist(playlist, 'artist')">
									<a><span translate>by artist</span></a>
								</li>
								<li ng-click="$parent.$parent.sortPlaylist(playlist, 'album')">
									<a><span translate>by album</span></a>
								</li>
							</ul>
						</div>
					</li>
					<li ng-click="$parent.$parent.remove(playlist)">
						<a class="icon-delete"><span translate>Delete</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/radio'">
			<span class="icon-more" ng-show="!$parent.radioBusy"
				ng-click="$parent.onPlaylistMoreButton('radio'); $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="$parent.radioBusy"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForPlaylist == 'radio'">
				<ul>
					<li ng-click="$parent.showRadioHint()">
						<a class="icon-details"><span translate>Getting started</span></a>
					</li>
					<li ng-click="$parent.importFromFileToRadio()">
						<a class="icon-from-file"><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.exportRadioToFile()">
						<a class="icon-to-file"><span translate>Export to file</span></a>
					</li>
					<li ng-click="$parent.addRadio()">
						<a class="icon-add"><span translate>Add manually</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/podcasts'">
			<span class="icon-more" ng-show="!$parent.podcastsBusy"
				ng-click="$parent.onPlaylistMoreButton('podcasts'); $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="$parent.podcastsBusy"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForPlaylist == 'podcasts'">
				<ul>
					<li ng-click="$parent.addPodcast()">
						<a class="icon-add"><span translate>Add from RSS feed</span></a>
					</li>
					<li ng-click="$parent.reloadPodcasts($event)" ng-class="{ disabled: !$parent.anyPodcastChannels() }">
						<a class="icon-reload"><span translate>Reload channels</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#'">
			<span class="icon-more"
				ng-click="$parent.onPlaylistMoreButton('albums'); $event.stopPropagation()"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForPlaylist == 'albums'">
				<ul>
					<li ng-click="$parent.toggleAlbumsCompactLayout(false)">
						<a ng-class="$parent.albumsCompactLayout ? 'icon-radio-button' : 'icon-radio-button-checked'"><span translate>Normal layout</span></a>
					</li>
					<li ng-click="$parent.toggleAlbumsCompactLayout(true)">
						<a ng-class="$parent.albumsCompactLayout ? 'icon-radio-button-checked' : 'icon-radio-button'"><span translate>Compact layout</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/folders'">
			<span class="icon-more"
				ng-click="$parent.onPlaylistMoreButton('folders'); $event.stopPropagation()"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForPlaylist == 'folders'">
				<ul>
					<li ng-click="$parent.toggleFoldersFlatLayout(false)">
						<a ng-class="$parent.foldersFlatLayout ? 'icon-radio-button' : 'icon-radio-button-checked'"><span translate>Tree layout</span></a>
					</li>
					<li ng-click="$parent.toggleFoldersFlatLayout(true)">
						<a ng-class="$parent.foldersFlatLayout ? 'icon-radio-button-checked' : 'icon-radio-button'"><span translate>Flat layout</span></a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</li>