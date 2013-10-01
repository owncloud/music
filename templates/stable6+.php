<?php
\OCP\Util::addScript('core', 'placeholder');
\OCP\Util::addScript('3rdparty', 'md5/md5.min');

print_unescaped($this->inc('main'));
?>