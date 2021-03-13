<div id="radio-station-details" class="sidebar-content" ng-controller="RadioStationDetailsController" ng-if="contentType=='radioStation'">

	<h1 translate>Radio station</h1>

	<dl class="tags">
		<dt translate>Name</dt>
		<dd class="clickable" ng-click="startEdit(nameEditor)"
			><span ng-show="!editing">{{ station.name }}<button class="icon-rename"></button></span
			><input ng-show="editing" ng-ref="nameEditor" type="text" ng-enter="commitEdit()" ng-model="station.name" maxlength="256"
		/></dd>

		<dt translate>Stream URL</dt>
		<dd class="clickable" ng-click="startEdit(streamUrlEditor)"
			><span ng-show="!editing">{{ station.stream_url }}<button class="icon-rename"></button></span
			><textarea ng-show="editing" ng-ref="streamUrlEditor" type="text" ng-enter="commitEdit()" ng-model="station.stream_url" maxlength="2048"></textarea
		></dd>

		<dt ng-show="editing"></dt>
		<dd ng-show="editing" class="editor-buttons"
			><button class="action icon-checkmark" ng-click="commitEdit()" ng-class="{ disabled: station.stream_url.length == 0 }"></button
			><button class="action icon-close" ng-click="cancelEdit()"></button
		></dd>

		<dt translate>Created</dt>
		<dd>{{ createdDate }}</dd>

		<dt translate>Updated</dt>
		<dd>{{ updatedDate }}</dd>
	</dl>

</div>
