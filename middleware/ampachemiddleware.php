<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Middleware;

use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Middleware;

use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;

use \OCA\Music\Db\AmpacheSessionMapper;

/**
 * Used to do the authentication and checking stuff for an ampache controller method
 * It reads out the annotations of a controller method and checks which if
 * ampache authentification stuff has to be done.
 */
class AmpacheMiddleware extends Middleware {

	private $appname;
	private $request;
	private $ampacheSessionMapper;
	private $isAmpacheCall;
	private $ampacheUser;

	/**
	 * @param Request $request an instance of the request
	 */
	public function __construct($appname, IRequest $request, AmpacheSessionMapper $ampacheSessionMapper, $ampacheUser){
		$this->appname = $appname;
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;

		// used to share user info with controller
		$this->ampacheUser = $ampacheUser;
	}


	/**
	 * This runs all the security checks before a method call. The
	 * security checks are determined by inspecting the controller method
	 * annotations
	 * @param string/Controller $controller the controllername or string
	 * @param string $methodName the name of the method
	 * @throws AmpacheException when a security check fails
	 */
	public function beforeController($controller, $methodName){

		// get annotations from comments
		$annotationReader = new MethodAnnotationReader($controller, $methodName);

		$this->isAmpacheCall = $annotationReader->hasAnnotation('AmpacheAPI');

		// don't try to authenticate for the handshake request
		if($this->isAmpacheCall && $this->request['action'] !== 'handshake'){
			$token = $this->request['auth'];
			if($token !== null && $token !== '') {
				$user = $this->ampacheSessionMapper->findByToken($token);
				if($user !== false && array_key_exists('user_id', $user)) {
					// setup the filesystem for the user - actual login isn't really needed
					\OC_Util::setupFS($user['user_id']);
					$this->ampacheUser->setUserId($user['user_id']);
					return;
				}
			} else {
				// for ping action without token the version information is provided
				if($this->request['action'] === 'ping') {
					return;
				}
			}
			throw new AmpacheException('Invalid Login', 401);
		}
	}

	/**
	 * If an AmpacheException is being caught, the appropiate ampache
	 * exception response is rendered
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it cant handle it
	 * @return Response a Response object or null in case that the exception could not be handled
	 */
	public function afterException($controller, $methodName, \Exception $exception){
		if($exception instanceof AmpacheException && $this->isAmpacheCall){
			$response = new TemplateResponse($this->appname, 'ampache/error');
			$response->renderAs('blank');
			$response->addHeader('Content-Type', 'text/xml; charset=UTF-8');
			$response->setParams(array(
				'code' => $exception->getCode(),
				'message' => $exception->getMessage()
			));
			return $response;
		}
		throw $exception;
	}

}
