<div class="view-container" id="albums" ng-show="!loading && !loadingCollection">
	<div class="artist-area" id="artist-{{ ::artist.id }}" ng-repeat="artist in artists | limitTo: incrementalLoadLimit">
		<list-heading 
			level="1"
			heading="artist.name"
			on-click="playArtist"
			get-draggable="getArtistDraggable"
			model="artist"
			show-play-icon="true">
		</list-heading>
		<div class="album-area" id="album-{{ ::album.id }}" ng-repeat="album in artist.albums">
			<list-heading 
				level="2"
				heading="album.name"
				heading-ext="decoratedYear(album)"
				tooltip="album.name + decoratedYear(album)"
				on-click="playAlbum"
				get-draggable="getAlbumDraggable"
				model="album">
			</list-heading>
			<div ng-click="playAlbum(album)" class="albumart" cover="{{ album.cover }}" albumart="{{ album.name }}"></div>
			<img ng-click="playAlbum(album)" class="play overlay svg" alt="{{ 'Play' | translate }}"
				 src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>" />
			<track-list
				tracks="album.tracks"
				get-track-data="getTrackData"
				play-track="playTrack"
				show-track-details="showSidebar"
				get-draggable="getTrackDraggable"
				collapse-limit="6"
				more-text="'Show all {{ album.tracks.length }} songs …' | translate"
				less-text="'Show less …' | translate"
				details-text="'Details' | translate">
			</track-list>
		</div>
	</div>

	<alphabet-navigation ng-if="artists && artists.length" item-count="artists.length"
		get-elem-title="getArtistName" get-elem-id="getArtistElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
