<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\IRequest;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\Scanner;

/**
 * End-points for shared audio file handling. Methods of this class may be
 * used while there is no user logged in.
 */
class ShareController extends Controller {

	/** @var \OCP\Share\IManager */
	private $shareManager;
	/** @var Scanner */
	private $scanner;
	/** @var Logger */
	private $logger;

	public function __construct($appname,
								IRequest $request,
								\OCP\Share\IManager $shareManager,
								Scanner $scanner,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->shareManager = $shareManager;
		$this->scanner = $scanner;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function fileInfo($token, $fileId) {
		$share = $this->shareManager->getShareByToken($token);

		$fileOwner = $share->getShareOwner();

		\OC_Util::tearDownFS();
		\OC_Util::setupFS($fileOwner);

		$fileOwnerHome = $this->scanner->resolveUserFolder($fileOwner);

		// If non-zero fileId is given, the $share identified by the token should
		// be the file's parent directory. Otherwise the share is the target file.
		if ($fileId == 0) {
			$fileId = $share->getNodeId();
		} else {
			$folderId = $share->getNodeId();
			$matchingFolders = $fileOwnerHome->getById($folderId);
			if (empty($matchingFolders)
				|| empty($matchingFolders[0]->getById($fileId))) {
				// no such shared folder or the folder does not contain the given file
				$fileId = null;
			}
		}

		$info = $this->scanner->getFileInfo($fileId, $fileOwner, $fileOwnerHome);
		if ($info) {
			return new JSONResponse($info);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}
}
