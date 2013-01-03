<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<auth><?php echo $_['token'];?></auth>
	<version>350001</version>
	<update><?php echo $_['date'];?></update>
	<add><?php echo $_['date'];?></add>
	<clean><?php echo $_['date'];?></clean>
	<songs><?php echo $_['songs'];?></songs>
	<artists><?php echo $_['artists'];?></artists>
	<albums><?php echo $_['albums'];?></albums>
	<session_length>600</session_length>
	<session_expire><?php echo $_['expire'];?></session_expire>
	<tags>0</tags>
	<videos>0</videos>
</root>
