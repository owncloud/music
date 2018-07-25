<script type="text/ng-template" id="navigationitem.html">
	<?php print_unescaped($this->inc('partials/navigationitem')) ?>
</script>

<div id="app-navigation" ng-controller="NavigationController">
	<ul>
		<li navigation-item text="'Albums' | translate" destination="'#'"
			title="{{ albumCountText() }}"></li>
		<li navigation-item text="'All tracks' | translate" destination="'#/alltracks'"
			title="{{ trackCountText() }}"></li>
		<li class="app-navigation-separator"></li>
		<li id="new-playlist" class="music-navigation-item">
			<a id="create" class="app-navigation-noclose" ng-click="showCreateForm=!showCreateForm" ng-hide="showCreateForm" translate>+ New Playlist</a>
			<input type="text" class="new-list" ng-show="showCreateForm" 
				placeholder="{{ ::('New Playlist' | translate) }}" ng-enter="create()" ng-model="newPlaylistName" />
			<div class="actions" ng-show="showCreateForm">
				<button ng-if="newPlaylistName.length > 0" class="svg action icon-checkmark app-navigation-noclose" ng-click="create()"></button>
				<button class="svg action icon-close app-navigation-noclose" ng-click="showCreateForm=!showCreateForm"></button>
			</div>
		</li>
		<li navigation-item
			playlist="playlist" text="playlist.name" destination="'#/playlist/' + playlist.id"
			ng-repeat="playlist in playlists"
			ui-on-drop="dropOnPlaylist($data, playlist)"
			drop-validate="allowDrop(playlist)"
			drag-hover-class="active"
			title="{{ trackCountText(playlist) }}"></li>
		<li class="music-nav-settings" ng-class="{active: $parent.currentView=='#/settings'}">
			<a class="" ng-click="navigateTo('#/settings')">
				{{ ::('Settings' | translate) }}
			</a>
		</li>
	</ul>
</div>
