<?php
if(!isset($_)) {//allow the template to be loaded standalone
	$tmpl = new OCP\Template( 'media', 'player');
	$tmpl->printPage();
	exit;
}
?>
<?php p($l->t('Music'));?>
<div class='player-controls' id="playercontrols">
	<div class="player" id="jp-player"></div>
	<ul class="jp-controls">
		<li><a href="#" class="jp-play action"><img class="svg" alt="<?php p($l->t('Play'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/play.svg')); ?>" /></a></li>
		<li><a href="#" class="jp-pause action"><img class="svg" alt="<?php p($l->t('Pause'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/pause.svg')); ?>" /></a></li>
		<li><a href="#" class="jp-next action"><img class="svg" alt="<?php p($l->t('Next'));?>" src="<?php print_unescaped(OCP\image_path('core', 'actions/play-next.svg')); ?>" /></a></li>
	</ul>
</div>