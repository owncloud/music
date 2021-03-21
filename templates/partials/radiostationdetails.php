<div id="radio-station-details" class="sidebar-content" ng-controller="RadioStationDetailsController" ng-if="contentType=='radioStation'">

	<h1 ng-show="station" translate>Radio station</h1>
	<h1 ng-show="!station" translate>New radio station</h1>

	<dl class="tags">
		<dt translate>Name</dt>
		<dd class="clickable" ng-click="startEdit(nameEditor)"
			><span ng-show="!editing">{{ stationName }}<button class="icon-rename"></button></span
			><input ng-show="editing" id="radio-name-editor" ng-ref="nameEditor" type="text" ng-enter="commitEdit()" ng-model="stationName" maxlength="256"
		/></dd>

		<dt translate>Stream URL</dt>
		<dd class="clickable" ng-click="startEdit(streamUrlEditor)"
			><span ng-show="!editing">{{ streamUrl }}<button class="icon-rename"></button></span
			><textarea ng-show="editing" ng-ref="streamUrlEditor" type="text" ng-enter="commitEdit()" ng-model="streamUrl" maxlength="2048"></textarea
		></dd>

		<dt ng-show="editing"></dt>
		<dd ng-show="editing" class="editor-buttons"
			><button class="action icon-checkmark" ng-click="commitEdit()" ng-class="{ disabled: !streamUrl }"></button
			><button class="action icon-close" ng-click="cancelEdit()"></button
		></dd>

		<dt ng-show="createdDate" translate>Created</dt>
		<dd ng-show="createdDate">{{ createdDate }}</dd>

		<dt ng-show="updatedDate" translate>Modified</dt>
		<dd ng-show="updatedDate">{{ updatedDate }}</dd>
	</dl>

</div>
