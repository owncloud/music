<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2025
 */

namespace OCA\Music\Middleware;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Controller\SubsonicController;
use OCA\Music\Db\AmpacheUserMapper;
use OCA\Music\Utility\StringUtil;

/**
 * Checks the authentication on each Subsonic API call before the
 * request is allowed to be passed to SubsonicController.
 * Map SubsonicExceptions from the controller to proper Subsonic error results.
 */
class SubsonicMiddleware extends Middleware {
	private IRequest $request;
	private AmpacheUserMapper $userMapper;
	private Logger $logger;

	public function __construct(IRequest $request, AmpacheUserMapper $userMapper, Logger $logger) {
		$this->request = $request;
		$this->userMapper = $userMapper;
		$this->logger = $logger;
	}

	/**
	 * This function is run before any HTTP request handler method, but it does
	 * nothing if the call in question is not routed to SubsonicController. In
	 * case of Subsonic call, this checks the user authentication.
	 *
	 * NOTE: Type declarations cannot be used on this function signature because that would be
	 * in conflict with the base class which is not in our hands.
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws SubsonicException when a security check fails
	 */
	public function beforeController($controller, $methodName) {
		// The security access logic is not applied to the CORS pre-flight calls with the 'OPTIONS'
		if ($controller instanceof SubsonicController && $methodName !== 'preflightedCors') {
			$this->setupResponseFormat($controller);
			$this->checkAuthentication($controller);
		}
	}

	/**
	 * Evaluate the response format parameters and setup the controller to use
	 * the requested format
	 * @param SubsonicController $controller
	 * @throws SubsonicException
	 */
	private function setupResponseFormat(SubsonicController $controller) {
		$format = $this->request->getParam('f', 'xml');
		$callback = $this->request->getParam('callback');

		if (!\in_array($format, ['json', 'xml', 'jsonp'])) {
			throw new SubsonicException("Unsupported format $format", 0);
		}

		if ($format === 'jsonp' && empty($callback)) {
			$format = 'xml';
			$this->logger->log("'jsonp' format requested but no arg 'callback' supplied, falling back to 'xml' format", 'debug');
		}

		$controller->setResponseFormat($format, $callback);
	}

	/**
	 * Check that valid credentials have been given.
	 * Setup the controller with the active user if the authentication is ok.
	 * @param SubsonicController $controller
	 * @throws SubsonicException
	 */
	private function checkAuthentication(SubsonicController $controller) {
		if ($this->request->getParam('t') !== null) {
			throw new SubsonicException('Token-based authentication not supported', 41);
		}

		$user = $this->request->getParam('u');
		$apiKey = $this->request->getParam('apiKey');

		if ($user !== null && $apiKey !== null) {
			throw new SubsonicException('Multiple conflicting authentication mechanisms provided', 43);
		}
		else if ($apiKey !== null) {
			$credentials = $this->userAndKeyIdForPass($apiKey);
			if ($credentials !== null) {
				$controller->setAuthenticatedUser($credentials['user_id'], $credentials['key_id']);
			} else {
				throw new SubsonicException('Invalid API key', 44);
			}
		}
		else if ($user !== null) {
			$pass = $this->request->getParam('p');
			if ($pass === null) {
				throw new SubsonicException('Password argument `p` missing', 10);
			}

			// The password may be given in hexadecimal format
			if (StringUtil::startsWith($pass, 'enc:')) {
				$pass = \hex2bin(\substr($pass, \strlen('enc:')));
			}

			$credentials = $this->userAndKeyIdForPass($pass);
			if (StringUtil::caselessCompare($user, $credentials['user_id']) === 0) {
				$controller->setAuthenticatedUser($credentials['user_id'], $credentials['key_id']);
			} else {
				throw new SubsonicException('Wrong username or password', 40);
			}
		}
		else {
			// Not passing any credentials is allowed since some parts of the API are allowed without authentication.
			// SubsonicController::handleRequest needs to check that there is an authenticated user if needed.
		}
	}

	/**
	 * @param string $pass Password aka API key
	 * @return ?array like ['key_id' => int, 'user_id' => string] or null if $pass was not valid
	 */
	private function userAndKeyIdForPass(string $pass) : ?array {
		$hash = \hash('sha256', $pass);
		return $this->userMapper->getUserByPasswordHash($hash);
	}

	/**
	 * Catch SubsonicException and BusinessLayerException instances thrown when handling
	 * Subsonic requests, and render the the appropriate Subsonic error response. Any other
	 * exceptions are allowed to flow through, reaching eventually the default handler if
	 * no-one else intercepts them. The default handler logs the error and returns response
	 * code 500.
	 *
	 * NOTE: Type declarations cannot be used on this function signature because that would be
	 * in conflict with the base class which is not in our hands.
	 *
	 * @param Controller $controller the controller that was being called
	 * @param string $methodName the name of the method that was called on the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it couldn't be handled
	 * @return Response a Response object in case the exception could be handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($controller instanceof SubsonicController) {
			if ($exception instanceof SubsonicException) {
				$this->logger->log($exception->getMessage(), 'debug');
				return $controller->subsonicErrorResponse(
						$exception->getCode(),
						$exception->getMessage()
				);
			} elseif ($exception instanceof BusinessLayerException) {
				return $controller->subsonicErrorResponse(70, 'Entity not found');
			}
		}
		throw $exception;
	}
}
