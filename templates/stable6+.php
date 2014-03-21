<?php
\OCP\Util::addScript('core', 'placeholder');
\OCP\Util::addScript('3rdparty', 'md5/md5.min');
\OCP\Util::addStyle('music', 'stable6+-fixes');

print_unescaped($this->inc('main'));
