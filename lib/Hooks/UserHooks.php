<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Hooks;

class UserHooks {
	private $userManager;
	private $maintenance;

	public function __construct($userManager, $maintenance) {
		$this->userManager = $userManager;
		$this->maintenance = $maintenance;
	}

	public function register() {
		$maintenance = $this->maintenance;
		$callback = function ($user) use ($maintenance) {
			$maintenance->resetDb($user->getUID());
		};
		$this->userManager->listen('\OC\User', 'postDelete', $callback);
	}
}
