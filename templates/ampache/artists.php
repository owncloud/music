<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['artists'] as $artist): ?>
		<artist id='<?php p($artist['id']);?>'>
			<name><?php print_unescaped($artist['name']);?></name>
			<albums><?php p($artist['albums']);?></albums>
			<songs><?php p($artist['songs']);?></songs>
			<rating>0</rating>
			<preciserating>0</preciserating>
		</artist>
	<?php endforeach;?>
</root>
