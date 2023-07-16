<div class="view-container" id="albums" ng-show="!loading && !loadingCollection">
	<div class="artist-area" id="artist-{{ ::artist.id }}" in-view-observer
		ng-repeat="artist in artists | limitTo: incrementalLoadLimit"
	>
		<list-heading
			level="1"
			heading="artist.name"
			on-click="playArtist"
			on-details-click="showArtistDetails"
			get-draggable="getArtistDraggable"
			model="artist"
			show-play-icon="true">
		</list-heading>
		<div class="album-area" id="album-{{ ::album.id }}" ng-repeat="album in artist.albums" ng-init="album.tracksExpanded=false">
			<list-heading
				level="2"
				heading="album.name"
				heading-ext="decoratedYear(album)"
				tooltip="album.name + decoratedYear(album)"
				on-click="playAlbum"
				on-details-click="showAlbumDetails"
				get-draggable="getAlbumDraggable"
				model="album"
				show-play-icon="true">
			</list-heading>
			<div ng-click="playAlbum(album)" class="albumart" albumart="::album"></div>
			<img ng-if="!albumsCompactLayout || searchMode" class="play overlay svg" alt="{{ 'Play' | translate }}"
				 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-overlay') ?>" ng-click="playAlbum(album)" />
			<img ng-if="albumsCompactLayout && !searchMode" class="overlay svg"
				 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('expand') ?>"
				 ng-class="{ 'flip-vertically': album.tracksExpanded }"
				 ng-click="album.tracksExpanded = !album.tracksExpanded; $event.stopPropagation()" />
			<track-list
				ng-show="!albumsCompactLayout || album.tracksExpanded || searchMode"
				tracks="album.tracks"
				get-track-data="getTrackData"
				play-track="playTrack"
				show-track-details="showTrackDetails"
				get-draggable="getTrackDraggable"
				collapse-limit="6">
			</track-list>
		</div>
	</div>

	<alphabet-navigation ng-if="artists && artists.length" item-count="artists.length"
		get-elem-title="getArtistSortName" get-elem-id="getArtistElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
