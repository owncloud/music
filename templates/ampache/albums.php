<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['albums'] as $album): ?>
		<?php $artist = $album->getArtist(); ?>
		<album id='<?php p($album->getId())?>'>
			<name><?php p($album->getNameString($_['api']))?></name>
			<artist id='<?php p($artist?$artist->getId():'')?>'><?php p($artist?$artist->getName():$_['api']->getTrans()->t('Unknown artist')->__toString())?></artist>
			<tracks><?php p($album->getTrackCount())?></tracks>
			<rating>0</rating>
			<year><?php p($album->getYear())?></year>
			<disk>1</disk>
			<art></art>
			<preciserating>0</preciserating>
		</album>
	<?php endforeach;?>
</root>
