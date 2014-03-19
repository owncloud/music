<?php
\OCP\Util::addScript('music', 'vendor/placeholder');
\OCP\Util::addScript('music', 'vendor/md5/md5.min');
\OCP\Util::addScript('music', 'public/oc5fixes');

print_unescaped($this->inc('main'));
