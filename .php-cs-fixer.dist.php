<?php

$config = new OC\CodingStandard\Config();

$config
    ->setUsingCache(true)
    ->getFinder()
    ->exclude('3rdparty')
    ->exclude('build')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->exclude('stubs')
    ->in(__DIR__);

return $config;
