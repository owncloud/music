<?php declare(strict_types=1);
/**
 * ownCloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\AppFramework\Core;

class Logger {
	protected string $appName;
	/** @var \OCP\ILogger|\Psr\Log\LoggerInterface $logger */
	protected $logger;

	/**
	 * @param \OCP\ILogger|\Psr\Log\LoggerInterface $logger
	 */
	public function __construct(string $appName, $logger) {
		$this->appName = $appName;
		$this->logger = $logger;
	}

	/**
	 * Writes a message to the log file
	 * @param string $msg the message to be logged
	 * @param string $level the severity of the logged event, defaults to 'error'
	 */
	public function log(string $msg, string $level=null) {
		$context = ['app' => $this->appName];
		switch ($level) {
			case 'debug':
				$this->logger->debug($msg, $context);
				break;
			case 'info':
				$this->logger->info($msg, $context);
				break;
			case 'warn':
				$this->logger->warning($msg, $context);
				break;
			case 'fatal':
				$this->logger->emergency($msg, $context);
				break;
			default:
				$this->logger->error($msg, $context);
				break;
		}
	}
}
