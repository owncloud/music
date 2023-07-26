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
use OCA\Music\Db\AmpacheUserMapper;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Util;

/**
 * Handles the session management on Ampache login/logout.
 * Checks the authentication on each Ampache API call before the
 * request is allowed to be passed to AmpacheController.
 * Map identified exceptions from the controller to proper Ampache error results.
 */
class AmpacheMiddleware extends Middleware {

	const SESSION_EXPIRY_TIME = 6000;

	private $request;
	private $ampacheSessionMapper;
	private $ampacheUserMapper;
	private $logger;

	public function __construct(
			IRequest $request, AmpacheSessionMapper $ampacheSessionMapper, AmpacheUserMapper $ampacheUserMapper, Logger $logger) {
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->ampacheUserMapper = $ampacheUserMapper;
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

			// authenticate on 'handshake' and check the session token on any other action
			$action = $this->request->getParam('action');
			if ($action === 'handshake') {
				$this->handleHandshake($controller);
			} else {
				$this->handleNonHandshakeAction($controller, $action);
			}
		}
	}
	
	private function handleHandshake(AmpacheController $controller) : void {
		$user = $this->request->getParam('user');
		$timestamp = (int)$this->request->getParam('timestamp');
		$auth = $this->request->getParam('auth');
		$version = $this->request->getParam('version');

		$currentTime = \time();
		$expiryDate = $currentTime + self::SESSION_EXPIRY_TIME;

		$this->checkHandshakeTimestamp($timestamp, $currentTime);
		$apiKeyId = $this->checkHandshakeAuthentication($user, $timestamp, $auth);
		$session = $this->startNewSession($user, $expiryDate, $version, $apiKeyId);
		$controller->setSession($session);
	}

	private function checkHandshakeTimestamp(int $timestamp, int $currentTime) : void {
		if ($timestamp === 0) {
			throw new AmpacheException('Invalid Login - cannot parse time', 401);
		}
		if ($timestamp < ($currentTime - self::SESSION_EXPIRY_TIME)) {
			throw new AmpacheException('Invalid Login - session is outdated', 401);
		}
		// Allow the timestamp to be at maximum 10 minutes in the future. The client may use its
		// own system clock to generate the timestamp and that may differ from the server's time.
		if ($timestamp > $currentTime + 600) {
			throw new AmpacheException('Invalid Login - timestamp is in future', 401);
		}
	}

	private function checkHandshakeAuthentication(?string $user, int $timestamp, ?string $auth) : int {
		if ($user === null || $auth === null) {
			throw new AmpacheException('Invalid Login - required credentials missing', 401);
		}

		$hashes = $this->ampacheUserMapper->getPasswordHashes($user);

		foreach ($hashes as $keyId => $hash) {
			$expectedHash = \hash('sha256', $timestamp . $hash);

			if ($expectedHash === $auth) {
				return (int)$keyId;
			}
		}

		throw new AmpacheException('Invalid Login - passphrase does not match', 401);
	}

	private function startNewSession(string $user, int $expiryDate, ?string $apiVersion, int $apiKeyId) : AmpacheSession {
		// create new session
		$session = new AmpacheSession();
		$session->setUserId($user);
		$session->setToken(Random::secure(16));
		$session->setExpiry($expiryDate);
		$session->setApiVersion(Util::truncate($apiVersion, 16));
		$session->setAmpacheUserId($apiKeyId);

		// save session to the database
		$this->ampacheSessionMapper->insert($session);

		return $session;
	}

	private function handleNonHandshakeAction(AmpacheController $controller, ?string $action) : void {
		$token = $this->request->getParam('auth') ?: $this->request->getParam('ssid');

		// 'ping' is allowed without a session (but if session token is passed, then it has to be valid)
		if (!($action === 'ping' && empty($token))) {
			$session = $this->getExistingSession($token);
			$controller->setSession($session);
		}

		if ($action === 'goodbye') {
			$this->ampacheSessionMapper->delete($session);
		}

		if ($action === null) {
			throw new AmpacheException("Required argument 'action' missing", 400);
		}
	}

	private function getExistingSession(?string $token) : AmpacheSession {
		if (empty($token)) {
			throw new AmpacheException('Invalid Login - session token missing', 401);
		} else {
			try {
				// extend the session deadline on any authorized API call
				$this->ampacheSessionMapper->extend($token, \time() + self::SESSION_EXPIRY_TIME);
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
				return $controller->ampacheErrorResponse($exception->getCode(), $exception->getMessage());
			} elseif ($exception instanceof BusinessLayerException) {
				return $controller->ampacheErrorResponse(404, 'Entity not found');
			}
		}
		throw $exception;
	}

}
