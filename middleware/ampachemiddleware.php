<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2018 - 2020
 */

namespace OCA\Music\Middleware;

use \OCP\IRequest;
use \OCP\AppFramework\Middleware;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Controller\AmpacheController;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Utility\AmpacheUser;

/**
 * Checks the authentication on each Ampache API call before the
 * request is allowed to be passed to AmpacheController.
 * Map identified exceptions from the controller to proper Ampache error results.
 */
class AmpacheMiddleware extends Middleware {
	private $request;
	private $ampacheSessionMapper;
	private $ampacheUser;
	private $logger;

	/**
	 * @param Request $request an instance of the request
	 */
	public function __construct(
			IRequest $request, AmpacheSessionMapper $ampacheSessionMapper,
			AmpacheUser $ampacheUser, Logger $logger) {
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->ampacheUser = $ampacheUser; // used to share user info with controller
		$this->logger = $logger;
	}

	/**
	 * This runs all the security checks before a method call. The
	 * security checks are determined by inspecting the controller method
	 * annotations
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
				$this->checkAuthentication();
			}
		}
	}

	private function checkAuthentication() {
		$token = $this->request['auth'] ?: $this->request['ssid'] ?: null;

		if (empty($token)) {
			// ping is allowed without a session (but if session token is passed, then it has to be valid)
			if ($this->request['action'] !== 'ping') {
				throw new AmpacheException('Invalid Login - session token missing', 401);
			}
		}
		else {
			$user = $this->ampacheSessionMapper->findByToken($token);
			if ($user !== false && \array_key_exists('user_id', $user)) {
				$this->ampacheUser->setUserId($user['user_id']);
			} else {
				throw new AmpacheException('Invalid Login - invalid session token', 401);
			}
		}
	}

	/**
	 * If an AmpacheException is being caught, the appropiate ampache
	 * exception response is rendered
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it wasn't handled
	 * @return Response a Response object if the exception was handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($controller instanceof AmpacheController) {
			if ($exception instanceof AmpacheException) {
				return $this->errorResponse($controller, $exception->getCode(), $exception->getMessage());
			}
			elseif ($exception instanceof BusinessLayerException) {
				return $this->errorResponse($controller, 400, 'Entity not found');
			}
		}
		throw $exception;
	}

	private function errorResponse(AmpacheController $controller, $code, $message) {
		$this->logger->log($message, 'debug');

		return $controller->ampacheResponse([
			'error' => [
				'code' => $code,
				'value' => $message
			]
		]);
	}
}
