<form id="mediaform">
	<fieldset class="personalblock">
		<strong><?php p($l->t('Media')); ?></strong><br />
		<?php p($l->t('Ampache address:')); ?>
		<code><?php print_unescaped(OCP\Util::linkToRemote('ampache')); ?></code><br />
	</fieldset>
</form>
