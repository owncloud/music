<?php

use OCA\Music\Utility\HtmlUtil;

/** @var array $_ */
?>

<div id="app-content">
	<div class="section">
		<div id="app-view">
			<h2><?php HtmlUtil::p($_['headline']) ?></h2>
			<p><strong><?php HtmlUtil::p($_['getsession_response']) ?></strong></p>
			<p><?php HtmlUtil::p($_['instructions']) ?></p>
		</div>
	</div>
</div>
