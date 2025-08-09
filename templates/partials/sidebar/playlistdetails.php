<div id="playlist-details" class="sidebar-content" ng-controller="PlaylistDetailsController" ng-if="contentType=='playlist'">

	<div favorite-toggle entity="playlist" rest-prefix="'playlists'"></div>
	<div class="albumart"></div>

	<div class="sidebar-container">
		<h1>{{playlist.name}}</h1>

		<dl class="tags">
			<dt translate>Number of tracks</dt>
			<dd>{{ playlist.tracks.length }}</dd>

			<dt translate>Total length</dt>
			<dd>{{ totalLength | playTime }}</dd>

			<dt ng-if="createdDate" translate>Created</dt>
			<dd ng-if="createdDate">{{ createdDate }}</dd>

			<dt ng-if="updatedDate" translate>Modified</dt>
			<dd ng-if="updatedDate">{{ updatedDate }}</dd>

			<dt translate>Comment</dt>
			<dd class="clickable" ng-click="startEdit()"
				><span ng-show="!editing">{{ playlist.comment }}<button class="icon-rename"></button></span
				><textarea ng-show="editing" type="text" on-enter="commitEdit()" ng-model="playlist.comment" maxlength="256"></textarea
			></dd>
		</dl>
	</div>

</div>
