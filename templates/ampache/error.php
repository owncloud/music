<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<error code='<?php echo $_['code'];?>'><?php echo $_['msg'];?></error>
</root>
