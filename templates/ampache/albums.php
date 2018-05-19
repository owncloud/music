<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<?php foreach ($_['albums'] as $album): ?>
		<?php $albumArtist = $album->getAlbumArtist(); ?>
		<album id='<?php p($album->getId())?>'>
			<name><?php p($album->getNameString($_['l10n']))?></name>
			<artist id='<?php p($albumArtist?$albumArtist->getId():'')?>'><?php p($albumArtist->getNameString($_['l10n'])) ?></artist>
			<tracks><?php p($album->getTrackCount())?></tracks>
			<rating>0</rating>
			<year><?php p($album->yearToAPI())?></year>
			<disk><?php p($album->getDisk())?></disk>
			<art><?php p($_['createCoverUrl']($album))?></art>
			<preciserating>0</preciserating>
		</album>
	<?php endforeach;?>
</root>
