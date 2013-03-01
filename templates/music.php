<div class='player-controls' id="controls">
	<div class="jp-controls">
		<a href="#" class="jp-previous action"><img class="svg" alt="<?php p($l->t('Previous'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/play-previous.svg')); ?>" /></a>
		<a href="#" class="jp-play action"><img class="svg" alt="<?php p($l->t('Play'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/play-big.svg')); ?>" /></a>
		<a href="#" class="jp-pause action"><img class="svg" alt="<?php p($l->t('Pause'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/pause-big.svg')); ?>" /></a>
		<a href="#" class="jp-next action"><img class="svg" alt="<?php p($l->t('Next'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/play-next.svg')); ?>" /></a>
		<div class="jp-progress">
			<div class="jp-seek-bar">
				<div class="jp-play-bar"></div>
			</div>
			<div class="jp-current-time"></div>
		</div>
		<a href="#" class="jp-mute action"><img class="svg" alt="<?php p($l->t('Mute'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/sound.svg')); ?>" /></a>
		<a href="#" class="jp-unmute action"><img class="svg" alt="<?php p($l->t('Unmute'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/sound-off.svg')); ?>" /></a>
		<div class="jp-volume-bar">
			<div class="jp-volume-bar-value"></div>
		</div>

		<div class="jp-current-song"></div>

		<div id="scan">
			<input type="button" class="start" value="<?php p($l->t('Rescan Collection'))?>" />
			<input type="button" class="stop" style="display:none" value="<?php p($l->t('Pause'))?>" />
			<div id="scanprogressbar"></div>
		</div>
	</div>

	<div class="player" id="jp-player"></div>
</div>

<ul id="leftcontent" class="hascontrols"></ul>

<div id="rightcontent">
<table id="collection" data-etag="<?php p($_['etag']); ?>">
	<thead>
		<tr>
			<th><?php p($l->t('Artist'))?></th>
			<th><?php p($l->t('Album'))?></th>
			<th><?php p($l->t('Title'))?></th>
		</tr>
	</thead>
	<tbody>
		<tr class="template">
			<td class="artist"><a></a></td>
			<td class="artist-expander"><a></a></td>
			<td class="album"><a></a></td>
			<td class="album-expander"><a></a></td>
			<td class="title"><a></a></td>
		</tr>
	</tbody>
</table>
</div>
