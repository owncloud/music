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

use OCP\IServerContainer;

class Logger {

	protected string $appName;
	/** @var \OCP\ILogger|\Psr\Log\LoggerInterface $logger */
	protected $logger;

	public function __construct(string $appName, IServerContainer $container) {
		$this->appName = $appName;

		// NC 31 removed the getLogger method but the Psr alternative is not available on OC
		if (\method_exists($container, 'getLogger')) { // @phpstan-ignore function.alreadyNarrowedType
			$this->logger = $container->getLogger();
		} else {
			$this->logger = $container->get(\Psr\Log\LoggerInterface::class);
		}
	}

	public function emergency(string $message) : void
	{
		$this->logger->emergency($message, ['app' => $this->appName]);
	}

	/**
	 * Action must be taken immediately.
	 */
	public function alert(string $message) : void
	{
		$this->logger->alert($message, ['app' => $this->appName]);
	}

	/**
	 * Critical conditions.
	 */
	public function critical(string $message) : void
	{
		$this->logger->critical($message, ['app' => $this->appName]);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 */
	public function error(string $message) : void
	{
		$this->logger->error($message, ['app' => $this->appName]);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 */
	public function warning(string $message) : void
	{
		$this->logger->warning($message, ['app' => $this->appName]);
	}

	/**
	 * Normal but significant events.
	 */
	public function notice(string $message) : void
	{
		$this->logger->notice($message, ['app' => $this->appName]);
	}

	/**
	 * Interesting events.
	 */
	public function info(string $message) : void
	{
		$this->logger->info($message, ['app' => $this->appName]);
	}

	/**
	 * Detailed debug information.
	 */
	public function debug(string $message) : void
	{
		$this->logger->debug($message, ['app' => $this->appName]);
	}

}
