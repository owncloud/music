<?xml version = "1.0" encoding = "UTF-8"?>
<root>
	<?php foreach ($_['albums'] as $album): ?>
		<album id='<?php echo $album['id'];?>'>
			<name><?php echo $album['name'];?></name>
			<artist id='<?php echo $album['artist'];?>'><?php echo $album['artist_name'];?></artist>
			<tracks><?php echo $album['songs'];?></tracks>
			<rating>0</rating>
			<year>0</year>
			<disk>1</disk>
			<art> </art>
			<preciserating>0</preciserating>
		</album>
	<?php endforeach;?>
</root>
