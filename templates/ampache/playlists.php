<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['playlists'] as $playlist): ?>
		<playlist id='<?php p($playlist->getId());?>'>
			<name><?php p($playlist->getName());?></name>
			<owner><?php $_['userId']?></owner>
			<items><?php p($playlist->getTrackCount());?></items>
			<type>Private</type>
		</playlist>
	<?php endforeach;?>
</root>
