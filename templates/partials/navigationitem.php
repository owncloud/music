<li class="music-navigation-item"
	ng-class="{	'active': $parent.currentView == destination,
				'menu-open': $parent.popupShownForNaviItem == destination,
				'item-with-actions': playlist || destination=='#/radio' || destination=='#/podcasts' || destination=='#' || destination=='#/folders' || destination=='#/smartlist' }"
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
			<div class="input-container with-buttons">
				<input type="text" class="edit-list" maxlength="256"
					on-enter="$parent.commitEdit(playlist)" ng-model="playlist.name"/>
			</div>
			<button class="action icon-checkmark app-navigation-noclose"
				ng-class="{ disabled: playlist.name.length == 0 }"
				ng-click="$parent.commitEdit(playlist); $event.stopPropagation()"></button>
		</div>
		<div class="actions" ng-init="subMenuShown = false" title="" ng-show="playlist && $parent.showEditForm == null">
			<span class="icon-more" ng-show="!playlist.busy"
				ng-click="$parent.onNaviItemMoreButton(destination); subMenuShown = false; $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="playlist.busy"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.showDetails(playlist)">
						<a><span class="icon-details icon"></span><span translate>Details</span></a>
					</li>
					<li ng-click="$parent.startEdit(playlist)" class="app-navigation-noclose">
						<a><span class="icon-rename icon"></span><span translate>Rename</span></a>
					</li>
					<li ng-click="$parent.importFromFile(playlist)">
						<a><span class="icon-from-file icon svg"></span><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.exportToFile(playlist)">
						<a><span class="icon-to-file icon svg"></span><span translate>Export to file</span></a>
					</li>
					<li ng-click="subMenuShown = !subMenuShown; $event.stopPropagation()">
						<a><span class="icon-sort-by-alpha icon svg"></span><span translate>Sort …</span></a>
						<div class="popovermenu bubble submenu" ng-show="subMenuShown">
							<ul>
								<li ng-click="$parent.sortPlaylist(playlist, 'track')">
									<a><span translate>by title</span></a>
								</li>
								<li ng-click="$parent.sortPlaylist(playlist, 'artist')">
									<a><span translate>by artist</span></a>
								</li>
								<li ng-click="$parent.sortPlaylist(playlist, 'album')">
									<a><span translate>by album</span></a>
								</li>
							</ul>
						</div>
					</li>
					<li ng-click="$parent.removeDuplicates(playlist)">
						<a><span class="icon-close icon"></span><span translate>Remove duplicates</span></a>
					</li>
					<li ng-click="$parent.remove(playlist)">
						<a><span class="icon-delete icon"></span><span translate>Delete</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/radio'">
			<span class="icon-more" ng-show="!$parent.radioBusy"
				ng-click="$parent.onNaviItemMoreButton(destination); $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="$parent.radioBusy"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.showRadioHint()">
						<a><span class="icon-details icon"></span><span translate>Getting started</span></a>
					</li>
					<li ng-click="$parent.importFromFileToRadio()">
						<a><span class="icon-from-file icon svg"></span><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.exportRadioToFile()">
						<a><span class="icon-to-file icon svg"></span><span translate>Export to file</span></a>
					</li>
					<li ng-click="$parent.addRadio()">
						<a><span class="icon-add icon"></span><span translate>Add manually</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/podcasts'">
			<span class="icon-more" ng-show="!$parent.podcastsBusy"
				ng-click="$parent.onNaviItemMoreButton(destination); $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="$parent.podcastsBusy"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.addPodcast()">
						<a><span class="icon-add icon"></span><span translate>Add from RSS feed</span></a>
					</li>
					<li ng-click="$parent.importPodcastsFromFile()">
						<a><span class="icon-from-file icon svg"></span><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.exportPodcastsToFile($event)" ng-class="{ disabled: !$parent.anyPodcastChannels() }">
						<a><span class="icon-to-file icon svg"></span><span translate>Export to file</span></a>
					</li>
					<li ng-click="$parent.reloadPodcasts($event)" ng-class="{ disabled: !$parent.anyPodcastChannels() }">
						<a><span class="icon-reload icon svg"></span><span translate>Reload channels</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#'">
			<span class="icon-more"
				ng-click="$parent.onNaviItemMoreButton(destination); $event.stopPropagation()"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.toggleAlbumsCompactLayout(false)">
						<a><span class="icon" ng-class="$parent.albumsCompactLayout ? 'icon-radio-button' : 'icon-radio-button-checked'"></span><span translate>Normal layout</span></a>
					</li>
					<li ng-click="$parent.toggleAlbumsCompactLayout(true)">
						<a><span class="icon" ng-class="$parent.albumsCompactLayout ? 'icon-radio-button-checked' : 'icon-radio-button'"></span><span translate>Compact layout</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/folders'">
			<span class="icon-more"
				ng-click="$parent.onNaviItemMoreButton(destination); $event.stopPropagation()"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.toggleFoldersFlatLayout(false)">
						<a><span class="icon" ng-class="$parent.foldersFlatLayout ? 'icon-radio-button' : 'icon-radio-button-checked'"></span><span translate>Tree layout</span></a>
					</li>
					<li ng-click="$parent.toggleFoldersFlatLayout(true)">
						<a><span class="icon" ng-class="$parent.foldersFlatLayout ? 'icon-radio-button-checked' : 'icon-radio-button'"></span><span translate>Flat layout</span></a>
					</li>
				</ul>
			</div>
		</div>
		<div class="actions" title="" ng-show="destination == '#/smartlist'">
			<span class="icon-more"
				ng-click="$parent.onNaviItemMoreButton(destination); $event.stopPropagation()"></span>
			<div class="popovermenu bubble" ng-show="$parent.popupShownForNaviItem == destination">
				<ul>
					<li ng-click="$parent.reloadSmartListView()">
						<a><span class="icon-reload icon svg"></span><span translate>Reload</span></a>
					</li>
					<li ng-click="$parent.showSmartListFilters()">
						<a><span class="icon-filter icon svg"></span><span translate>Filters</span></a>
					</li>
					<li ng-click="$parent.saveSmartList()">
						<a><span class="icon-playlist icon svg"></span><span translate>Save playlist</span></a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</li>