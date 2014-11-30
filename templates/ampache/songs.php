<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
<?php foreach ($_['songs'] as $song): ?>
	<song id='<?php p($song->getId());?>'>
		<title><?php p($song->getTitle());?></title>
		<artist id='<?php p($song->getArtist()->getId());?>'><?php p($song->getArtist()->getName());?></artist>
		<album id='<?php p($song->getAlbum()->getId());?>'><?php p($song->getAlbum()->getName());?></album>
		<url><?php p($_['urlGenerator']->getAbsoluteURL($_['urlGenerator']->linkToRoute('music.ampache.ampache'))); ?>?action=play&amp;filter=<?php p($song->getId());?>&amp;auth=<?php p($_['authtoken']); ?></url>
		<time><?php p($song->getLength());?></time>
		<track><?php p($song->getNumber());?></track>
		<size>0</size>
		<art><?php $cid = $song->getAlbum()->getCoverFileId(); if ($cid){p($_['urlGenerator']->getAbsoluteURL($_['urlGenerator']->linkToRoute('music.ampache.ampache'))); ?>?action=_get_cover&amp;filter=<?php p($song->getAlbum()->getId());?>&amp;auth=<?php p($_['authtoken']);} ?></art>
		<rating>0</rating>
		<preciserating>0</preciserating>
	</song>
<?php endforeach;?>
</root>
