<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
<?php foreach ($_['songs'] as $song): ?>
	<song id='<?php p($song['id']);?>'>
		<title><?php print_unescaped($song['name']);?></title>
		<artist id='<?php p($song['artist']);?>'><?php print_unescaped($song['artist_name']);?></artist>
		<album id='<?php p($song['album']);?>'><?php print_unescaped($song['album_name']);?></album>
		<url><?php print_unescaped($song['url']);?></url>
		<time><?php p($song['length']);?></time>
		<track><?php p($song['track']);?></track>
		<size><?php p($song['size']);?></size>
		<art> </art>
		<rating>0</rating>
		<preciserating>0</preciserating>
	</song>
<?php endforeach;?>
</root>
