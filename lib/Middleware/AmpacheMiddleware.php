<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2018 - 2023
 */

namespace OCA\Music\Middleware;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Middleware;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Controller\AmpacheController;
use OCA\Music\Db\AmpacheSession;
use OCA\Music\Db\AmpacheSessionMapper;

/**
 * Checks the authentication on each Ampache API call before the
 * request is allowed to be passed to AmpacheController.
 * Map identified exceptions from the controller to proper Ampache error results.
 */
class AmpacheMiddleware extends Middleware {
	private $request;
	private $ampacheSessionMapper;
	private $logger;

	public function __construct(
			IRequest $request, AmpacheSessionMapper $ampacheSessionMapper, Logger $logger) {
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->logger = $logger;
	}

	/**
	 * This runs all the security checks before a method call. The
	 * security checks are determined by inspecting the controller method
	 * annotations
	 *
	 * NOTE: Type declarations cannot be used on this function signature because that would be
	 * in conflict with the base class which is not in our hands.
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws AmpacheException when a security check fails
	 */
	public function beforeController($controller, $methodName) {
		if ($controller instanceof AmpacheController) {
			if ($methodName === 'jsonApi') {
				$controller->setJsonMode(true);
			}

			// don't try to authenticate for the handshake request
			if ($this->request['action'] !== 'handshake') {
				$session = $this->checkAuthentication();
				$controller->setSession($session);
			}
		}
	}

	private function checkAuthentication() : AmpacheSession {
		$token = $this->request['auth'] ?: $this->request['ssid'] ?: null;

		if (empty($token)) {
			// ping is allowed without a session (but if session token is passed, then it has to be valid)
			if ($this->request['action'] !== 'ping') {
				throw new AmpacheException('Invalid Login - session token missing', 401);
			}
		} else {
			try {
				// extend the session deadline on any authorized API call
				$this->ampacheSessionMapper->extend($token, \time() + AmpacheController::SESSION_EXPIRY_TIME);
				return $this->ampacheSessionMapper->findByToken($token);
			} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
				throw new AmpacheException('Invalid Login - invalid session token', 401);
			}
		}
	}

	/**
	 * If an AmpacheException is being caught, the appropiate ampache
	 * exception response is rendered
	 *
	 * NOTE: Type declarations cannot be used on this function signature because that would be
	 * in conflict with the base class which is not in our hands.
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it wasn't handled
	 * @return \OCP\AppFramework\Http\Response object if the exception was handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($controller instanceof AmpacheController) {
			if ($exception instanceof AmpacheException) {
				return $this->errorResponse($controller, $exception->getCode(), $exception->getMessage());
			} elseif ($exception instanceof BusinessLayerException) {
				return $this->errorResponse($controller, 404, 'Entity not found');
			}
		}
		throw $exception;
	}

	private function errorResponse(AmpacheController $controller, int $code, string $message) {
		$this->logger->log($message, 'debug');

		return $controller->ampacheResponse([
			'error' => [
				'code' => $code,
				'value' => $message
			]
		]);
	}
}
