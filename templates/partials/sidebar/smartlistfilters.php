<div id="smartlist-filters" class="sidebar-content" ng-controller="SmartListFiltersController" ng-if="contentType=='smartlist'" on-enter="onUpdateButton()">

	<h1 translate>Smart playlist filters</h1>

	<div>
		<label for="genres">Genres</label>
		<select name="genres" id="filter-genres" multiple data-placeholder="{{ '(not defined)' | translate }}" ng-model="genres">
			<option ng-repeat="genre in allGenres" value="{{ genre.id }}">{{ genre.name }}</option>
		</select>
	</div>

	<div>
		<label for="artists">Artists</label>
		<select name="artists" id="filter-artists" multiple data-placeholder="{{ '(not defined)' | translate }}" ng-model="artists">
			<option ng-repeat="artist in allArtists" value="{{ artist.id }}">{{ artist.name }}</option>
		</select>
	</div>

	<div>
		<label for="from-year">From year</label>
		<input type="text" name="from-year" ng-model="fromYear"/>
	</div>

	<div>
		<label for="to-year">To year</label>
		<input type="text" name="to-year" ng-model="toYear"/>
	</div>

	<div>
		<label for="size">List size</label>
		<input type="text" name="size" id="" ng-model="listSize"/>
	</div>

	<div title="{{ 'Note that this selection makes any difference only when the library has more than requested number of matches' | translate }}">
		<label for="play-rate">Play rate</label>
		<select name="play-rate" id="filter-play-rate" ng-model="playRate">
			<option value="" translate>(not defined)</option>
			<option value="recently" translate>Recently played</option>
			<option value="not-recently" translate>Not recently played</option>
			<option value="often" translate>Often played</option>
			<option value="rarely" translate>Rarely played</option>
		</select>
	</div>

	<div><button id="update-button" ng-click="onUpdateButton()" translate>Update</button></div>
</div>
