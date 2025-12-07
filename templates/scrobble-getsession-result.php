<?php

use OCA\Music\Utility\HtmlUtil;

/**
 * @var array $_
 * @var \OCP\IL10N $l
 */

HtmlUtil::addWebpackScript('scrobble_getsession_result');
HtmlUtil::addWebpackStyle('app');

?>
<div id="app-content" data-result="<?= $_['success'] ?>">
	<div class="section">
		<div id="app-view">
			<div id="music-user">
				<h2><?php HtmlUtil::p($_['headline']) ?></h2>
				<div><?php HtmlUtil::p($_['instructions']) ?></div>
				<?php if (!$_['success']): ?>
				<div class="warning"><strong><?php HtmlUtil::p($_['getsession_response']) ?></strong></div>
				<?php else: ?>
				<div><?= $l->t('You can now close this window') ?></div>
				<?php endif ?>
			</div>
		</div>
	</div>
</div>
