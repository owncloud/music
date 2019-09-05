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
use \OCP\AppFramework\Middleware;

use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;

use \OCA\Music\Utility\Util;


/**
 * Checks the authentication on each Subsonic API call before the
 * request is allowed to be passed to SubsonicController.
 * Map SubsonicExceptions from the controller to proper Subsonic error results.
 */
class SubsonicMiddleware extends Middleware {
	private $request;
	private $isSubsonicCall;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	/**
	 * This is run before any HTTP request handler method.
	 * Need for Subsonic authentication checking is detected from the function
	 * annotation 'SubsonicAPI'
	 * 
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws SubsonicException when a security check fails
	 */
	public function beforeController($controller, $methodName) {
		// get annotations from comments
		$annotationReader = new MethodAnnotationReader($controller, $methodName);

		$this->$isSubsonicCall = $annotationReader->hasAnnotation('SubsonicAPI');

		if ($this->$isSubsonicCall) {
			$user = $this->request->u;
			$pass = $this->request->p;

			// The password may be given in hexadecimal format
			if (Util::startsWith($pass, 'enc:')) {
				$pass = \hex2bin(\substr($pass, \strlen('enc:')));
			}

			// Dummy implementation: allow only single test account
			if ($user != 'root' || $pass != 'Test123') {
				throw new SubsonicException('Invalid Login', 40);
			}
		}
	}

	/**
	 * If an SubsonicException is being caught, the appropiate subsonic
	 * exception response is rendered
	 * @param Controller $controller the controller that was being called
	 * @param string $methodName the name of the method that was called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it couldn't be handled
	 * @return Response a Response object in case the exception could be handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof SubsonicException && $this->$isSubsonicCall) {
			return $controller->subsonicErrorResponse(
					$exception->getCode(),
					$exception->getMessage()
			);
		}
		throw $exception;
	}
}
