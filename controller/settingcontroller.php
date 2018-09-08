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
 * @copyright Pauli Järvinen 2017, 2018
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\Files\Folder;
use \OCP\IConfig;
use \OCP\IRequest;
use \OCP\Security\ISecureRandom;
use \OCP\IURLGenerator;

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
	private $urlGenerator;

	public function __construct($appname,
								IRequest $request,
								AmpacheUserMapper $ampacheUserMapper,
								Scanner $scanner,
								$userId,
								Folder $userFolder,
								IConfig $configManager,
								ISecureRandom $secureRandom,
								IURLGenerator $urlGenerator) {
		parent::__construct($appname, $request);

		$this->appname = $appname;
		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->scanner = $scanner;
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->configManager = $configManager;
		$this->secureRandom = $secureRandom;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function userPath($value) {
		$success = false;
		$path = $value;
		// TODO check for validity
		$element = $this->userFolder->get($path);
		if ($element instanceof \OCP\Files\Folder) {
			if ($path[0] !== '/') {
				$path = '/' . $path;
			}
			if ($path[\strlen($path)-1] !== '/') {
				$path .= '/';
			}
			$prevPath = $this->getPath();
			$this->configManager->setUserValue($this->userId, $this->appname, 'path', $path);
			$success = true;
			$this->scanner->updatePath($prevPath, $path, $this->userId);
		}
		return new JSONResponse(['success' => $success]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAll() {
		return [
			'path' => $this->getPath(),
			'ampacheUrl' => $this->getAmpacheUrl(),
			'ampacheKeys' => $this->getAmpacheKeys(),
			'appVersion' => $this->getAppVersion(),
			'user' => $this->userId
		];
	}

	private function getPath() {
		$path = $this->configManager->getUserValue($this->userId, $this->appname, 'path');
		return $path ?: '/';
	}

	private function getAmpacheUrl() {
		return \str_replace('/server/xml.server.php', '',
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('music.ampache.ampache')));
	}

	private function getAmpacheKeys() {
		return $this->ampacheUserMapper->getAll($this->userId);
	}

	private function getAppVersion() {
		return \OCP\App::getAppVersion($this->appname);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addUserKey($description, $password) {
		$hash = \hash('sha256', $password);
		$id = $this->ampacheUserMapper->addUserKey($this->userId, $hash, $description);
		$success = ($id !== null);
		return new JSONResponse(['success' => $success, 'id' => $id]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function generateUserKey($length, $description) {
		if ($description == null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, 'Please provide a description');
		}

		if ($length == null || $length < self::DEFAULT_PASSWORD_LENGTH) {
			$length = self::DEFAULT_PASSWORD_LENGTH;
		}

		$password = $this->secureRandom->generate(
			$length,
			ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_DIGITS);

		$hash = \hash('sha256', $password);
		$id = $this->ampacheUserMapper->addUserKey($this->userId, $hash, $description);

		if ($id === null) {
			return new ErrorResponse(Http::STATUS_INTERNAL_SERVER_ERROR, 'Error while saving the credentials');
		}

		return new JSONResponse(['id' => $id, 'password' => $password, 'description' => $description], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeUserKey($id) {
		$this->ampacheUserMapper->removeUserKey($this->userId, $id);
		return new JSONResponse(['success' => true]);
	}
}
