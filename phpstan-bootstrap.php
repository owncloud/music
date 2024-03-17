<?php

/**
 * PHPStan reckons only those class aliases which are given here, not those found within the code to be analyzed.
 * Also, the autoloading of the classes doesn't seem to work yet while this file is being executed.
 */
require_once('lib/AppFramework/Db/OldNextcloudMapper.php');
\class_alias(\OCA\Music\AppFramework\Db\OldNextcloudMapper::class, 'OCA\Music\AppFramework\Db\CompatibleMapper');

require_once('vendor/nextcloud/ocp/OCP/BackgroundJob/IJob.php');
require_once('stubs/OC/BackgroundJob/Job.php');
require_once('stubs/OC/BackgroundJob/TimedJob.php');
\class_alias(\OC\BackgroundJob\TimedJob::class, '\OCA\Music\BackgroundJob\TimedJob');