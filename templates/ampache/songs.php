<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
<?php foreach ($_['songs'] as $song): ?>
	<song id='<?php echo $song['id'];?>'>
		<title><?php echo $song['name'];?></title>
		<artist id='<?php echo $song['artist'];?>'><?php echo $song['artist_name'];?></artist>
		<album id='<?php echo $song['album'];?>'><?php echo $song['album_name'];?></album>
		<url><?php echo $song['url'];?></url>
		<time><?php echo $song['length'];?></time>
		<track><?php echo $song['track'];?></track>
		<size><?php echo $song['size'];?></size>
		<art> </art>
		<rating>0</rating>
		<preciserating>0</preciserating>
	</song>
<?php endforeach;?>
</root>
