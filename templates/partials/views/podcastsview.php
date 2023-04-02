<div class="view-container" id="podcasts" ng-show="!loading">
	<div class="artist-area">
		<div class="album-area" id="podcast-channel-{{ ::channel.id }}" in-view-observer ng-repeat="channel in channels">
			<list-heading
				level="2"
				heading="channel.title"
				tooltip="channel.title"
				on-click="playChannel"
				model="channel"
				actions="[
					{ icon: 'details', text: 'Details', callback: showPodcastChannelDetails },
					{ icon: 'reload svg', text: 'Reload', callback: reloadChannel },
					{ icon: 'delete', text: 'Remove', callback: removeChannel }
				]"
				show-play-icon="true">
			</list-heading>
			<div class="albumart" albumart="::channel"></div>
			<img class="play overlay svg" alt="{{ 'Play' | translate }}"
				 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-overlay') ?>" ng-click="playChannel(channel)" />
			<track-list
				tracks="channel.episodes"
				get-track-data="getEpisodeData"
				play-track="playEpisode"
				show-track-details="showPodcastEpisodeDetails"
				collapse-limit="6"
				show-collapsed-text="getMoreEpisodesText"
				track-id-prefix="'podcast-episode'"
				content-type="'podcast'">
			</track-list>
		</div>
	</div>

	<alphabet-navigation ng-if="channels && channels.length" item-count="channels.length"
		get-elem-title="getChannelName" get-elem-id="getChannelElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>

	<div class="emptycontent clickable no-collapse" ng-show="channels.length == 0" ng-click="showAddPodcast()">
		<div class="icon-podcast svg"></div>
		<div>
			<h2 translate>No channels</h2>
			<p translate>Click to add your first podcast channel</p>
		</div>
	</div>
</div>
