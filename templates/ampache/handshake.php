<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<auth><?php p($_['token']);?></auth>
	<version>350001</version>
	<update><?php p($_['date']);?></update>
	<add><?php p($_['date']);?></add>
	<clean><?php p($_['date']);?></clean>
	<songs><?php p($_['songs']);?></songs>
	<artists><?php p($_['artists']);?></artists>
	<albums><?php p($_['albums']);?></albums>
	<session_length>600</session_length>
	<session_expire><?php p($_['expire']);?></session_expire>
	<tags>0</tags>
	<videos>0</videos>
</root>
