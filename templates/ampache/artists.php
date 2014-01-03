<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['artists'] as $artist): ?>
		<artist id='<?php p($artist->getId());?>'>
			<name><?php print_unescaped($artist->getName());?></name>
			<albums><?php p($artist->getAlbumCount());?></albums>
			<songs><?php p($artist->getTrackCount());?></songs>
			<rating>0</rating>
			<preciserating>0</preciserating>
		</artist>
	<?php endforeach;?>
</root>
