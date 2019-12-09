<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019
 */

namespace OCA\Music\Middleware;

use \OCP\IRequest;
use \OCP\AppFramework\Http\Response;
use \OCP\AppFramework\Middleware;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\Controller\SubsonicController;
use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Utility\Util;


/**
 * Checks the authentication on each Subsonic API call before the
 * request is allowed to be passed to SubsonicController.
 * Map SubsonicExceptions from the controller to proper Subsonic error results.
 */
class SubsonicMiddleware extends Middleware {
	private $request;
	private $userMapper;

	public function __construct(IRequest $request, AmpacheUserMapper $userMapper) {
		$this->request = $request;
		$this->userMapper = $userMapper;
	}

	/**
	 * This function is run before any HTTP request handler method, but it does
	 * nothing if the call in question is not routed to SubsonicController. In
	 * case of Subsonic call, this checks the user authentication.
	 * 
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws SubsonicException when a security check fails
	 */
	public function beforeController($controller, $methodName) {
		if ($controller instanceof SubsonicController) {
			$this->setupResponseFormat($controller);
			$this->checkAuthentication($controller);
		}
	}

	/**
	 * Evaluate the reponse format parameters and setup the controller to use
	 * the requested format
	 * @param SubsoniController $controller
	 * @throws SubsonicException
	 */
	private function setupResponseFormat($controller) {
		$format = $this->request->getParam('f', 'xml');
		$callback = $this->request->getParam('callback');

		if (!\in_array($format, ['json', 'xml', 'jsonp'])) {
			throw new SubsonicException("Unsupported format $format", 0);
		}

		if ($format === 'jsonp' && empty($callback)) {
			throw new SubsonicException("Argument 'callback' is required with jsonp format", 10);
		}

		$controller->setResponseFormat($format, $callback);
	}

	/**
	 * Check that valid credentials have been given.
	 * Setup the controller with the acitve user if the authentication is ok.
	 * @param SubsoniController $controller
	 * @throws SubsonicException
	 */
	private function checkAuthentication($controller) {
		$user = $this->request->getParam('u');
		$pass = $this->request->getParam('p');

		if ($user === null || $pass === null) {
			if ($this->request->getParam('t') !== null) {
				throw new SubsonicException('Token-based authentication not supported', 41);
			} else {
				throw new SubsonicException('Required credentials missing', 10);
			}
		}

		// The password may be given in hexadecimal format
		if (Util::startsWith($pass, 'enc:')) {
			$pass = \hex2bin(\substr($pass, \strlen('enc:')));
		}

		if ($this->credentialsAreValid($user, $pass)) {
			$controller->setAuthenticatedUser($user);
		} else {
			throw new SubsonicException('Invalid Login', 40);
		}
	}

	/**
	 * @param string $user Username
	 * @param string $pass Password
	 * @return boolean
	 */
	private function credentialsAreValid($user, $pass) {
		$hashes = $this->userMapper->getPasswordHashes($user);

		foreach ($hashes as $hash) {
			if ($hash === \hash('sha256', $pass)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Catch SubsonicException and BusinessLayerExcpetion instances thrown when handling
	 * Subsonic requests, and render the the appropiate Subsonic error response. Any other
	 * exceptions are allowed to flow through, reaching eventually the default handler if
	 * no-one else intercepts them. The default handler logs the error and returns response
	 * code 500.
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
				return $controller->subsonicErrorResponse(
						$exception->getCode(),
						$exception->getMessage()
				);
			}
			elseif ($exception instanceof BusinessLayerException) {
				return $controller->subsonicErrorResponse(70, 'Entity not found');
			}
		}
		throw $exception;
	}
}
