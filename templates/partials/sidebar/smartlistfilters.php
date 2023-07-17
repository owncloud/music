<div id="smartlist-filters" class="sidebar-content" ng-controller="SmartListFiltersController" ng-if="contentType=='smartlist'">

	<h1 translate>Smart playlist filters</h1>

	<div>
		<label for="filter-size" translate>List size</label>
		<input id="filter-size" type="number" ng-model="listSize"/>
	</div>

	<div>
		<label for="filter-from-year" translate>From year</label>
		<input id="filter-from-year" type="number" ng-model="fromYear"/>
	</div>

	<div>
		<label for="filter-to-year" translate>To year</label>
		<input id="filter-to-year" type="number" ng-model="toYear"/>
	</div>

	<div>
		<label for="filter-genres" translate>Genres</label>
		<select id="filter-genres" multiple data-placeholder=" " ng-model="genres">
			<option ng-repeat="genre in allGenres" value="{{ genre.id }}">{{ genre.name }}</option>
		</select>
	</div>

	<div>
		<label for="filter-artists" translate>Artists</label>
		<select id="filter-artists" multiple data-placeholder=" " ng-model="artists">
			<option ng-repeat="artist in allArtists" value="{{ artist.id }}">{{ artist.name }}</option>
		</select>
	</div>

	<div title="{{ 'Note that this selection makes any difference only when the library has more than requested number of matches' | translate }}">
		<label for="filter-play-rate" translate>Play history</label>
		<select id="filter-play-rate" ng-model="playRate">
			<option value=""></option>
			<option value="recently" translate>Recently played</option>
			<option value="not-recently" translate>Not recently played</option>
			<option value="often" translate>Often played</option>
			<option value="rarely" translate>Rarely played</option>
		</select>
	</div>

	<div><button id="update-button" ng-click="onUpdateButton()" ng-disabled="!fieldsValid" translate>Update</button></div>
</div>
