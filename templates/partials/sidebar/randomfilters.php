<div id="random-filters" class="sidebar-content" ng-controller="RandomFiltersController" ng-if="contentType=='random'">

	<h1 translate>Filters</h1>

	<div>
		<label for="play-rate">Play rate</label>
		<select name="play-rate" id="filter-play-rate">
			<option value="none" translate>(not defined)</option>
			<option value="recent" translate>Recently played</option>
			<option value="not-recent" translate>Not recently played</option>
			<option value="often" translate>Often played</option>
			<option value="rarely" translate>Rarely played</option>
		</select>
	</div>

	<div>
		<label for="genres">Genres</label>
		<select name="genres" id="filter-genres" multiple data-placeholder="{{ '(not defined)' | translate }}">
			<option ng-repeat="genre in genres" value="{{ genre.id }}">{{ genre.name }}</option>
		</select>
	</div>

	<div>
		<label for="from-year">From year</label>
		<input type="text" name="from-year"/>
	</div>

	<div>
		<label for="to-year">To year</label>
		<input type="text" name="to-year"/>
	</div>

	<div><button id="update-button" translate>Update</button></div>
</div>
