<?php
\OCP\Util::addScript('music', 'vendor/placeholder');
\OCP\Util::addScript('music', 'vendor/md5/md5.min');
\OCP\Util::addStyle('music', 'stable5-fixes');

print_unescaped($this->inc('main'));
