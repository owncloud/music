<div class="view-container" id="genres-area" ng-show="!loading && !loadingCollection">
	<div class="playlist-area genre-area" id="genre-{{ ::genre.id }}" in-view-observer
		in-view-observer-margin="1000"
		ng-repeat="genre in genres | limitTo: incrementalLoadLimit"
	>
		<list-heading 
				level="1"
				heading="genre.name || '(Unknown genre)' | translate"
				on-click="onGenreTitleClick"
				get-draggable="getGenreDraggable"
				model="genre"
				show-play-icon="true">
		</list-heading>
		<track-list
				tracks="genre.tracks"
				get-track-data="getTrackData"
				play-track="onTrackClick"
				show-track-details="showSidebar"
				get-draggable="getTrackDraggable"
				collapse-limit="10">
		</track-list>
	</div>

	<alphabet-navigation ng-if="genres && genres.length" item-count="genres.length"
		get-elem-title="getGenreName" get-elem-id="getGenreElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
