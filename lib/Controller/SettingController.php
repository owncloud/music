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
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Db\AmpacheUserMapper;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Utility\Scanner;
use OCA\Music\Utility\Util;
use OCA\Music\Utility\UserMusicFolder;

class SettingController extends Controller {
	const DEFAULT_PASSWORD_LENGTH = 10;

	private $ampacheUserMapper;
	private $scanner;
	private $userId;
	private $userMusicFolder;
	private $secureRandom;
	private $urlGenerator;
	private $logger;

	public function __construct(string $appName,
								IRequest $request,
								AmpacheUserMapper $ampacheUserMapper,
								Scanner $scanner,
								?string $userId,
								UserMusicFolder $userMusicFolder,
								ISecureRandom $secureRandom,
								IURLGenerator $urlGenerator,
								Logger $logger) {
		parent::__construct($appName, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->scanner = $scanner;
		$this->userId = $userId ?? ''; // ensure non-null to satisfy Scrutinizer; the null case should happen only when the user has already logged out
		$this->userMusicFolder = $userMusicFolder;
		$this->secureRandom = $secureRandom;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function userPath($value) {
		$prevPath = $this->userMusicFolder->getPath($this->userId);
		$success = $this->userMusicFolder->setPath($this->userId, $value);

		if ($success) {
			$this->scanner->updatePath($prevPath, $value, $this->userId);
		}

		return new JSONResponse(['success' => $success]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function userExcludedPaths($value) {
		$success = $this->userMusicFolder->setExcludedPaths($this->userId, $value);
		return new JSONResponse(['success' => $success]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAll() {
		return [
			'path' => $this->userMusicFolder->getPath($this->userId),
			'excludedPaths' => $this->userMusicFolder->getExcludedPaths($this->userId),
			'ampacheUrl' => $this->getAmpacheUrl(),
			'subsonicUrl' => $this->getSubsonicUrl(),
			'ampacheKeys' => $this->getAmpacheKeys(),
			'appVersion' => $this->getAppVersion(),
			'user' => $this->userId
		];
	}

	private function getAmpacheUrl() {
		return \str_replace('/server/xml.server.php', '',
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('music.ampache.xmlApi')));
	}

	private function getSubsonicUrl() {
		return \str_replace('/rest/dummy', '',
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute(
						'music.subsonic.handleRequest', ['method' => 'dummy'])));
	}

	private function getAmpacheKeys() {
		return $this->ampacheUserMapper->getAll($this->userId);
	}

	private function getAppVersion() {
		// Note: the following in deprecated since NC14 but the replacement
		// \OCP\App\IAppManager::getAppVersion is not available before NC14.
		return \OCP\App::getAppVersion($this->appName);
	}

	private function storeUserKey($description, $password) {
		$hash = \hash('sha256', $password);
		$description = Util::truncate($description, 64); // some DB setups can't truncate automatically to column max size
		return $this->ampacheUserMapper->addUserKey($this->userId, $hash, $description);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addUserKey($description, $password) {
		$id = $this->storeUserKey($description, $password);
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

		$id = $this->storeUserKey($description, $password);

		if ($id === null) {
			return new ErrorResponse(Http::STATUS_INTERNAL_SERVER_ERROR, 'Error while saving the credentials');
		}

		return new JSONResponse(['id' => $id, 'password' => $password, 'description' => $description], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeUserKey($id) {
		$this->ampacheUserMapper->removeUserKey($this->userId, (int)$id);
		return new JSONResponse(['success' => true]);
	}
}
