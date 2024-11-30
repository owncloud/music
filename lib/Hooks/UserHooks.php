<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2024
 */

namespace OCA\Music\Hooks;

use OC\Hooks\Emitter;
use OCA\Music\Db\Maintenance;

class UserHooks {
	private Emitter $userManager;
	private Maintenance $maintenance;

	public function __construct(Emitter $userManager, Maintenance $maintenance) {
		$this->userManager = $userManager;
		$this->maintenance = $maintenance;
	}

	public function register() : void {
		$maintenance = $this->maintenance;
		$callback = function ($user) use ($maintenance) {
			$maintenance->resetAllData($user->getUID());
		};
		$this->userManager->listen('\OC\User', 'postDelete', $callback);
	}
}
