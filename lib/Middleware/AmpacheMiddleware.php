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
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Middleware;

use OCP\IConfig;
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
use OCA\Music\Utility\StringUtil;

/**
 * Handles the session management on Ampache login/logout.
 * Checks the authentication on each Ampache API call before the
 * request is allowed to be passed to AmpacheController.
 * Map identified exceptions from the controller to proper Ampache error results.
 */
class AmpacheMiddleware extends Middleware {

	private IRequest $request;
	private AmpacheSessionMapper $ampacheSessionMapper;
	private AmpacheUserMapper $ampacheUserMapper;
	private Logger $logger;
	private ?string $loggedInUser;
	private int $sessionExpiryTime;

	public function __construct(
			IRequest $request,
			IConfig $config,
			AmpacheSessionMapper $ampacheSessionMapper,
			AmpacheUserMapper $ampacheUserMapper,
			Logger $logger,
			?string $userId) {
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->logger = $logger;
		$this->loggedInUser = $userId;

		$this->sessionExpiryTime = (int)$config->getSystemValue('music.ampache_session_expiry_time', 6000);
		$this->sessionExpiryTime = \min($this->sessionExpiryTime, 365*24*60*60); // limit to one year
	}

	/**
	 * This runs all the security checks before a method call.
	 *
	 * NOTE: Type declarations cannot be used on this function signature because that would be
	 * in conflict with the base class which is not in our hands.
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws AmpacheException when a security check fails
	 */
	public function beforeController($controller, $methodName) {
		// The security access logic is not applied to the CORS pre-flight calls with the 'OPTIONS'
		if ($controller instanceof AmpacheController && $methodName !== 'preflightedCors') {
			if ($methodName === 'internalApi') {
				// internal clients get the internal session without any additional checking
				$controller->setSession($this->getInternalSession());
			} else {
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
	}
	
	private function handleHandshake(AmpacheController $controller) : void {
		$user = $this->request->getParam('user');
		$timestamp = (int)$this->request->getParam('timestamp');
		$auth = $this->request->getParam('auth');
		$version = $this->request->getParam('version');

		$expiryDate = \time() + $this->sessionExpiryTime;
		// TODO: The expiry timestamp is currently saved in the database as an unsigned integer.
		// For PostgreSQL, this has the maximum value of 2^31 which will become a problem in the
		// year 2038 (or already in 2037 if the admin has configured close to the maximum expiry time).

		$credentials = $this->checkHandshakeAuthentication($user, $timestamp, $auth);
		$session = $this->startNewSession($credentials['user'], $expiryDate, $version, $credentials['apiKeyId']);
		$controller->setSession($session);
	}

	private function checkHandshakeTimestamp(int $timestamp, int $currentTime) : void {
		if ($timestamp === 0) {
			throw new AmpacheException('Invalid Login - cannot parse time', 401);
		}
		if ($timestamp < ($currentTime - $this->sessionExpiryTime)) {
			throw new AmpacheException('Invalid Login - session is outdated', 401);
		}
		// Allow the timestamp to be at maximum 10 minutes in the future. The client may use its
		// own system clock to generate the timestamp and that may differ from the server's time.
		if ($timestamp > $currentTime + 600) {
			throw new AmpacheException('Invalid Login - timestamp is in future', 401);
		}
	}

	private function checkHandshakeAuthentication(?string $user, int $timestamp, ?string $auth) : array {
		if ($auth === null) {
			throw new AmpacheException('Invalid Login - required credentials missing', 401);
		}

		// The username is not passed by the client when the "API key" authentication is used
		if ($user === null) {
			$credentials = $this->credentialsForApiKey($auth);
		} else {
			$this->checkHandshakeTimestamp($timestamp, \time());
			$credentials = $this->credentialsForUsernameAndPassword($user, $timestamp, $auth);
		}

		if ($credentials === null) {
			throw new AmpacheException('Invalid Login - passphrase does not match', 401);
		}

		return $credentials;
	}

	private function credentialsForApiKey($auth) : ?array {
		$usersAndHashes = $this->ampacheUserMapper->getUsersAndPasswordHashes();

		foreach ($usersAndHashes as $keyId => $row) {
			// It's a bit vague in the API documentation, but looking at the Ampache source codes,
			// there are two valid options for passing the API key: either it is passed in plaintext,
			// or it's passed hashed together with the username like sha256(username . sha256(apiKey)).
			// On the other hand, our DB contains hashed keys sha256(apiKey).
			$valid1 = ($row['hash'] == \hash('sha256', $auth));
			$valid2 = ($auth == \hash('sha256', $row['user_id'] . $row['hash']));

			if ($valid1 || $valid2) {
				return ['user' => $row['user_id'], 'apiKeyId' => (int)$keyId];
			}
		}

		return null;
	}

	private function credentialsForUsernameAndPassword(string $user, int $timestamp, string $auth) : ?array {
		$user = $this->ampacheUserMapper->getProperUserId($user);

		if ($user !== null) {
			$hashes = $this->ampacheUserMapper->getPasswordHashes($user);

			foreach ($hashes as $keyId => $hash) {
				$expectedHash = \hash('sha256', $timestamp . $hash);

				if ($expectedHash === $auth) {
					return ['user' => $user, 'apiKeyId' => (int)$keyId];
				}
			}
		}

		return null;
	}

	private function startNewSession(string $user, int $expiryDate, ?string $apiVersion, int $apiKeyId) : AmpacheSession {
		// create new session
		$session = new AmpacheSession();
		$session->setUserId($user);
		$session->setToken(Random::secure(16));
		$session->setExpiry($expiryDate);
		$session->setApiVersion(StringUtil::truncate($apiVersion, 16));
		$session->setAmpacheUserId($apiKeyId);

		// save session to the database
		$this->ampacheSessionMapper->insert($session);

		return $session;
	}

	private function handleNonHandshakeAction(AmpacheController $controller, ?string $action) : void {
		$token = $this->request->getParam('auth') ?: $this->request->getParam('ssid') ?: $this->getTokenFromHeader();

		// 'ping' is allowed without a session (but if session token is passed, then it has to be valid)
		if ($action === 'ping' && empty($token)) {
			return;
		}

		$session = $this->getExistingSession($token);
		$controller->setSession($session);

		if ($action === 'goodbye') {
			$this->ampacheSessionMapper->delete($session);
		}

		if ($action === null) {
			throw new AmpacheException("Required argument 'action' missing", 400);
		}
	}

	private function getTokenFromHeader() : ?string {
		// The Authorization header cannot be obtained with $this->request->getHeader(). Hence, we
		// use the native PHP API for this. Apparently, the getallheaders() function is not available
		// on non-Apache servers (e.g. nginx) prior to PHP 7.3.
		$authHeader = getallheaders()['Authorization'] ?? '';
		$prefix = 'Bearer ';
		if (StringUtil::startsWith($authHeader, $prefix)) {
			return \substr($authHeader, \strlen($prefix));
		} else {
			return null;
		}
	}

	/**
	 * Internal session may be used to utilize the Ampache API within the Nextcloud/ownCloud server while in
	 * a valid user session, without needing to create an API key for this. That is, this session type is never
	 * used by the external client applications.
	 */
	private function getInternalSession() : AmpacheSession {
		if ($this->loggedInUser === null) {
			throw new AmpacheException('Internal session requires a logged-in cloud user', 401);
		}

		$session = new AmpacheSession();
		$session->userId = $this->loggedInUser;
		$session->token = 'internal';
		$session->expiry = 0;
		$session->apiVersion = AmpacheController::API6_VERSION;
		$session->ampacheUserId = 0;

		return $session;
	}

	private function getExistingSession(?string $token) : AmpacheSession {
		if (empty($token)) {
			throw new AmpacheException('Invalid Login - session token missing', 401);
		} else {
			try {
				// extend the session deadline on any authorized API call
				$this->ampacheSessionMapper->extend($token, \time() + $this->sessionExpiryTime);
				return $this->ampacheSessionMapper->findByToken($token);
			} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
				throw new AmpacheException('Invalid Login - invalid session token', 401);
			}
		}
	}

	/**
	 * If an AmpacheException is being caught, the appropriate ampache
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
