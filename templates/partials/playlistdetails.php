<div id="playlist-details" class="sidebar-content" ng-controller="PlaylistDetailsController" ng-if="contentType=='playlist'">

	<h1>{{playlist.name}}</h1>

	<dl class="tags">
		<dt translate>Number of tracks</dt>
		<dd>{{ playlist.tracks.length }}</dd>

		<dt translate>Total length</dt>
		<dd>{{ totalLength | playTime }}</dd>

		<dt translate>Created</dt>
		<dd>{{ createdDate }}</dd>

		<dt translate>Modified</dt>
		<dd>{{ updatedDate }}</dd>

		<dt translate>Comment</dt>
		<dd class="clickable" ng-click="startEdit()"
			><span ng-show="!editing">{{ playlist.comment }}<button class="icon-rename"></button></span
			><textarea ng-show="editing" type="text" ng-enter="commitEdit()" ng-model="playlist.comment" maxlength="256"></textarea
		></dd>
	</dl>

</div>
