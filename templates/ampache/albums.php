<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['albums'] as $album): ?>
		<album id='<?php p($album['id'])?>'>
			<name><?php print_unescaped($album['name'])?></name>
			<artist id='<?php p($album['artist'])?>'><?php p($album['artist_name'])?></artist>
			<tracks><?php p($album['songs'])?></tracks>
			<rating>0</rating>
			<year>0</year>
			<disk>1</disk>
			<art> </art>
			<preciserating>0</preciserating>
		</album>
	<?php endforeach;?>
</root>
