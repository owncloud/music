<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

/**
 * TODO: Proper extractor
 *
 * Translation note: Keep in mind to update the fake-template.php with the string which
 * has to be translated, because just that file is scanned by the exctrator
 */

?>

<div class="section" id="music-user">
	<h2><?php p($l->t('Music')); ?></h2>
	<div>
		<label for="music-path"><?php p($l->t('Path to your music collection')); ?></label>
		<input type="text" id="music-path" value="<?php p($_['path']); ?>" />
		<p><em><?php p($l->t('This setting specifies the folder which will be scanned for music.')); ?>.</em></p>
	</div>
	<h3>Ampache</h3>
	<div class="warning">
		<?php print_unescaped($l->t('Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your ' .
		'experience with this feature in the corresponding <a href="https://github.com/owncloud/music/issues/60">issue</a>. ' .
		'I would also like to have a list of clients to test with. Thanks')); ?>
	</div>
	<div>
		<code><?php p(\OC_Helper::makeURLAbsolute(\OC_Helper::linkToRoute('music.ampache.ampache')));?></code><br />
		<em><?php p($l->t('Use this address to browse your music collection from any Ampache compatible player.')); ?></em>
	</div>
	<div>
		<?php p($l->t("Here you can generate passwords to use with the Ampache API, because they " .
		"can't be stored in a really secure way due to the design of the Ampache API. " .
		"You can generate as many passwords as you want and revoke them anytime.")); ?>
	</div>
	<table id="music-ampache-keys" class="grid <?php if(!count($_['ampacheKeys'])) { ?>hidden<?php } ?>">
		<tr class="head">
			<th><?php p($l->t('Description')); ?></th>
			<th class="key-action"><?php p($l->t('Revoke API password')); ?></th>
		</tr>
		<?php foreach ($_['ampacheKeys'] as $key) { ?>
			<tr>
				<td><?php p($key['description']); ?></td>
				<td class="key-action"><a href="#" class="icon-delete" data-id="<?php p($key['id']); ?>"></a></td>
			</tr>
		<?php } ?>
		<tr id="music-ampache-template-row" class="hidden">
			<td></td>
			<td class="key-action"><a href="#" class="icon-loading-small" data-id=""></a></td>
		</tr>
	</table>
	<div id="music-ampache-form">
		<input type="text" id="music-ampache-description" placeholder="<?php p($l->t('Description (e.g. App name)')); ?>" />
		<button><?php p($l->t('Generate API password')); ?></button>
		<div id="music-password-info" class="info hidden">
			<?php p($l->t('Use your username and following password to connect to this Ampache instance:')); ?><br />
			<span></span>
		</div>
	</div>
</div>
