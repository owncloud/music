<?php
header('Content-Type: text/xml');
print '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
?>
<root>
	<error code='<?php p($_['code']);?>'><?php p($_['msg']);?></error>
</root>
