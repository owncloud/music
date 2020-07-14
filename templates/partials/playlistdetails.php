<div id="playlist-details" class="sidebar-content" ng-controller="PlaylistDetailsController" ng-if="contentType=='playlist'">

	<h1>{{playlist.name}}</h1>

	<dl class="tags">
		<dt translate>Number of tracks</dt>
		<dd>{{ playlist.tracks.length }}</dd>

		<dt translate>Total length</dt>
		<dd>{{ totalLength | playTime }}</dd>

		<dt translate>Created</dt>
		<dd>{{ (playlist.created.replace(' ', 'T') + 'Z') | date : 'medium' }}</dd>

		<dt translate>Comment</dt>
		<dd>{{ playlist.comment }}</dd>
	</dl>

</div>
