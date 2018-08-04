<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['playlists'] as $playlist): ?>
		<playlist id='<?php p($playlist['id']);?>'>
			<name><?php p($playlist['name']);?></name>
			<owner><?php $_['userId']?></owner>
			<items><?php p($playlist['trackCount']);?></items>
			<type>Private</type>
		</playlist>
	<?php endforeach;?>
</root>
