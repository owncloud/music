<div id="smartlist-filters" class="sidebar-content" ng-controller="SmartListFiltersController" ng-if="contentType=='smartlist' && smartListParams">

	<h1 translate>Smart playlist filters</h1>

	<div>
		<label for="filter-size" translate>List size</label>
		<input id="filter-size" type="number" ng-model="smartListParams.size"/>
	</div>

	<div>
		<label for="filter-from-year" translate>Years</label>
		<input id="filter-from-year" type="number" ng-model="smartListParams.fromYear"/>
		—&nbsp;
		<input id="filter-to-year" type="number" ng-model="smartListParams.toYear"/>
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

	<div>
		<label for="filter-favorite" translate>Favorite</label>
		<select id="filter-favorite" ng-model="smartListParams.favorite">
			<option value=""></option>
			<option value="track" translate>Favorite track</option>
			<option value="album" translate>Favorite album</option>
			<option value="artist" translate>Favorite artist</option>
			<option value="track_album_artist" translate>Favorite track, album, or artist</option>
		</select>
	</div>

	<div title="{{ 'Note that this selection makes any difference only when the library has more than the requested number of matches. In the strict mode, only the best matching songs are included with no element of randomness.' | translate }}">
		<label for="filter-history" translate>History</label>
		<select id="filter-history" ng-model="smartListParams.history">
			<option value=""></option>
			<option value="recently-played" translate>Recently played</option>
			<option value="not-recently-played" translate>Not recently played</option>
			<option value="often-played" translate>Often played</option>
			<option value="rarely-played" translate>Rarely played</option>
			<option value="recently-added" translate>Recently added</option>
			<option value="not-recently-added" translate>Not recently added</option>
		</select>
		<label for="filter-history-strict" id="filter-history-strict-label" translate>Strict</label>
		<input id="filter-history-strict" type="checkbox" ng-model="smartListParams.historyStrict" />
	</div>

	<div><button id="update-button" ng-click="onUpdateButton()" ng-disabled="!fieldsValid" translate>Update</button></div>

	<div class="hint" translate translate-params-url="'#/search'">Hint: To list tracks with more refined criteria, try <a href="{{url}}">Advanced search</a></div>
</div>
