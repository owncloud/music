<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\AppFramework\BackgroundJob;

/**
 * A base class wrapper which inherits TimedJob either from OC or OCP namespace. The OC version is available
 * on ownCloud and on Nextcloud versions <29. The OCP version is available on NC15+.
 */
if (\class_exists('\OCP\BackgroundJob\TimedJob')) {
	abstract class TimedJob extends \OCP\BackgroundJob\TimedJob {}
} else {
	abstract class TimedJob extends \OC\BackgroundJob\TimedJob {}
}
