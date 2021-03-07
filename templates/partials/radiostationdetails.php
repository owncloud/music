<div id="radio-station-details" class="sidebar-content" ng-controller="RadioStationDetailsController" ng-if="contentType=='radioStation'">

	<h1 translate>Radio station</h1>

	<dl class="tags">

		<dt translate>Name</dt>
		<dd>{{ station.name }}</dd>

		<dt translate>Stream URL</dt>
		<!-- TODO: <dd class="clickable" ng-click="startEdit()" -->
		<dd><span ng-show="!editing">{{ station.stream_url }}<!--TODO button class="icon-rename"></button--></span
			><textarea ng-show="editing" type="text" ng-enter="commitEdit()" ng-model="station.stream_url" maxlength="2048"></textarea
		></dd>

		<dt translate>Created</dt>
		<dd>{{ createdDate }}</dd>

		<dt translate>Updated</dt>
		<dd>{{ updatedDate }}</dd>

	</dl>

</div>
