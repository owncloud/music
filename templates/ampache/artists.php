<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['artists'] as $artist): ?>
		<artist id='<?php echo $artist['id'];?>'>
			<name><?php echo $artist['name'];?></name>
			<albums><?php echo $artist['albums'];?></albums>
			<songs><?php echo $artist['songs'];?></songs>
			<rating>0</rating>
			<preciserating>0</preciserating>
		</artist>
	<?php endforeach;?>
</root>
