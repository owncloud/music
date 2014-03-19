<?php
\OCP\Util::addScript('music', 'vendor/placeholder');
\OCP\Util::addScript('music', 'vendor/md5/md5.min');

print_unescaped($this->inc('main'));
