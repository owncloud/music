<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
<?php foreach ($_['songs'] as $song): ?>
	<song id='<?php p($song->getId());?>'>
		<title><?php p($song->getTitle());?></title>
		<artist id='<?php p($song->getArtist()->getId());?>'><?php p($song->getArtist()->getName());?></artist>
		<album id='<?php p($song->getAlbum()->getId());?>'><?php p($song->getAlbum()->getName());?></album>
		<url><?php p($_['api']->getAbsoluteURL($_['api']->linkToRoute('music_ampache_alternative'))); ?>?action=play&amp;filter=<?php p($song->getId());?>&amp;auth=<?php print_unescaped($_['authtoken']); ?></url>
		<time>0</time>
		<track><?php p($song->getNumber());?></track>
		<size>0</size>
		<art> </art>
		<rating>0</rating>
		<preciserating>0</preciserating>
	</song>
<?php endforeach;?>
</root>
