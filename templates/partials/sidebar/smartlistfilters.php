<div id="smartlist-filters" class="sidebar-content" ng-controller="SmartListFiltersController" ng-if="contentType=='smartlist'">

	<h1 translate>Smart playlist filters</h1>

	<div>
		<label for="size" translate>List size</label>
		<input type="number" name="size" id="filters-size" ng-model="listSize"/>
	</div>

	<div>
		<label for="from-year" translate>From year</label>
		<input type="number" name="from-year" ng-model="fromYear"/>
	</div>

	<div>
		<label for="to-year" translate>To year</label>
		<input type="number" name="to-year" ng-model="toYear"/>
	</div>

	<div>
		<label for="genres" translate>Genres</label>
		<select name="genres" id="filter-genres" multiple data-placeholder=" " ng-model="genres">
			<option ng-repeat="genre in allGenres" value="{{ genre.id }}">{{ genre.name }}</option>
		</select>
	</div>

	<div>
		<label for="artists" translate>Artists</label>
		<select name="artists" id="filter-artists" multiple data-placeholder=" " ng-model="artists">
			<option ng-repeat="artist in allArtists" value="{{ artist.id }}">{{ artist.name }}</option>
		</select>
	</div>

	<div title="{{ 'Note that this selection makes any difference only when the library has more than requested number of matches' | translate }}">
		<label for="play-rate" translate>Play history</label>
		<select name="play-rate" id="filter-play-rate" ng-model="playRate">
			<option value=""></option>
			<option value="recently" translate>Recently played</option>
			<option value="not-recently" translate>Not recently played</option>
			<option value="often" translate>Often played</option>
			<option value="rarely" translate>Rarely played</option>
		</select>
	</div>

	<div><button id="update-button" ng-click="onUpdateButton()" ng-disabled="!fieldsValid" translate>Update</button></div>
</div>
