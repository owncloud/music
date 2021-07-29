<div class="view-container" id="podcasts" ng-show="!loading">
	<div class="artist-area">
		<div class="album-area" id="podcast-channel-{{ ::channel.id }}" in-view-observer ng-repeat="channel in channels">
			<list-heading 
				level="2"
				heading="channel.title"
				tooltip="channel.title"
				on-click="playChannel"
				model="channel"
				show-play-icon="true">
			</list-heading>
			<div ng-click="playAlbum(album)" class="albumart" cover="{{ channel.image }}" albumart="{{ channel.title }}"></div>
			<img class="play overlay svg" alt="{{ 'Play' | translate }}"
				 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-overlay') ?>" ng-click="playChannel(channel)" />
			<track-list
				tracks="channel.episodes"
				get-track-data="getEpisodeData"
				play-track="playEpisode"
				collapse-limit="6">
			</track-list>
		</div>
	</div>

	<alphabet-navigation ng-if="channels && channels.length" item-count="channels.length"
		get-elem-title="getChannelName" get-elem-id="getChannelElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
