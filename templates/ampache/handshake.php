<?php
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<auth><?php p($_['token']);?></auth>
	<version>350001</version>
	<update><?php p(date('c', $_['updateDate']));?></update>
	<add><?php p(date('c', $_['addDate']));?></add>
	<clean><?php p(date('c', $_['cleanDate']));?></clean>
	<songs><?php p($_['songCount']);?></songs>
	<artists><?php p($_['artistCount']);?></artists>
	<albums><?php p($_['albumCount']);?></albums>
	<playlists><?php p($_['playlistCount']);?></playlists>
	<session_expire><?php p(date('c', $_['expireDate']));?></session_expire>
	<tags>0</tags>
	<videos>0</videos>
</root>
