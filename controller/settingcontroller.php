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

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\Files\Folder;
use \OCP\IConfig;
use \OCP\IL10N;
use \OCP\IRequest;
use \OCP\Security\ISecureRandom;

use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Utility\Scanner;

class SettingController extends Controller {

	const DEFAULT_PASSWORD_LENGTH = 10;

	private $appname;
	private $ampacheUserMapper;
	private $scanner;
	private $userId;
	private $userFolder;
	private $configManager;
	private $secureRandom;
	private $l10n;

	public function __construct($appname,
								IRequest $request,
								AmpacheUserMapper $ampacheUserMapper,
								Scanner $scanner,
								$userId,
								Folder $userFolder,
								IConfig $configManager,
								ISecureRandom $secureRandom,
								$l10n){
		parent::__construct($appname, $request);

		$this->appname = $appname;
		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->scanner = $scanner;
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->configManager = $configManager;
		$this->secureRandom = $secureRandom;
		$this->l10n = $l10n;
	}

	/**
	 * @NoAdminRequired
	 */
	public function userPath() {
		$success = false;
		$path = $this->params('value');
		// TODO check for validity
		$element = $this->userFolder->get($path);
		if ($element instanceof \OCP\Files\Folder) {
			if ($path[0] !== '/') {
				$path = '/' . $path;
			}
			if ($path[strlen($path)-1] !== '/') {
				$path .= '/';
			}
			$this->configManager->setUserValue($this->userId, $this->appname, 'path', $path);
			$success = true;
			$this->scanner->updatePath($path);
		}
		return new JSONResponse(array('success' => $success));
	}

	/**
	 * @NoAdminRequired
	 */
	public function addUserKey() {
		$success = false;
		$description = $this->params('description');
		$password = $this->params('password');

		$hash = hash('sha256', $password);
		$id = $this->ampacheUserMapper->addUserKey($this->userId, $hash, $description);
		if($id !== null) {
			$success = true;
		}
		return new JSONResponse(array('success' => $success, 'id' => $id));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function generateUserKey($length, $description) {
		if($description == NULL) {
			return new JSONResponse(['message' => $this->l10n->t('Please provide a description')], Http::STATUS_BAD_REQUEST);
		}

		if($length == NULL || $length < self::DEFAULT_PASSWORD_LENGTH) {
			$length = self::DEFAULT_PASSWORD_LENGTH;
		}

		$password = $this->secureRandom->generate(
			$length,
			ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_DIGITS);

		$hash = hash('sha256', $password);
		$id = $this->ampacheUserMapper->addUserKey($this->userId, $hash, $description);

		if(is_null($id)) {
			return new JSONResponse(['message' => $this->l10n->t('Error while saving the credentials')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(['id' => $id, 'password' => $password, 'description' => $description], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeUserKey() {
		$id = $this->params('id');
		$this->ampacheUserMapper->removeUserKey($this->userId, $id);
		return new JSONResponse(array('success' => true));
	}
}
