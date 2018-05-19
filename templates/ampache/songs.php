<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
<?php foreach ($_['songs'] as $song): ?>
	<song id='<?php p($song->getId());?>'>
		<title><?php p($song->getTitle());?></title>
		<artist id='<?php p($song->getArtist()->getId());?>'><?php p($song->getArtist()->getName());?></artist>
		<albumartist id='<?php p($song->getAlbum()->getAlbumArtist()->getId());?>'><?php p($song->getAlbum()->getAlbumArtist()->getName());?></albumartist>
		<album id='<?php p($song->getAlbum()->getId());?>'><?php p($song->getAlbum()->getName());?></album>
		<url><?php p($_['createPlayUrl']($song));?></url>
		<time><?php p($song->getLength());?></time>
		<track><?php p($song->getNumber());?></track>
		<bitrate><?php p($song->getBitrate());?></bitrate>
		<mime><?php p($song->getMimetype());?></mime>
		<size>0</size>
		<art><?php p($_['createCoverUrl']($song));?></art>
		<rating>0</rating>
		<preciserating>0</preciserating>
	</song>
<?php endforeach;?>
</root>
