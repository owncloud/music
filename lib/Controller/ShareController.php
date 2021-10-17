<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2021
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\Folder;
use OCP\IRequest;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Utility\PlaylistFileService;
use OCA\Music\Utility\Scanner;

/**
 * End-points for shared audio file handling. Methods of this class may be
 * used while there is no user logged in.
 */
class ShareController extends Controller {

	/** @var \OCP\Share\IManager */
	private $shareManager;
	/** @var Scanner */
	private $scanner;
	/** @var PlaylistFileService */
	private $playlistFileService;
	/** @var Logger */
	private $logger;

	public function __construct($appname,
								IRequest $request,
								Scanner $scanner,
								PlaylistFileService $playlistFileService,
								Logger $logger,
								\OCP\Share\IManager $shareManager) {
		parent::__construct($appname, $request);
		$this->shareManager = $shareManager;
		$this->scanner = $scanner;
		$this->playlistFileService = $playlistFileService;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function fileInfo(string $token, int $fileId) {
		$share = $this->shareManager->getShareByToken($token);
		$fileOwner = $share->getShareOwner();
		$fileOwnerHome = $this->scanner->resolveUserFolder($fileOwner);

		// If non-zero fileId is given, the $share identified by the token should
		// be the file's parent directory. Otherwise the share is the target file.
		if ($fileId == 0) {
			$fileId = $share->getNodeId();
		} else {
			$folderId = $share->getNodeId();
			$matchingFolders = $fileOwnerHome->getById($folderId);
			$folder = $matchingFolders[0] ?? null;
			if (!($folder instanceof Folder) || empty($folder->getById($fileId))) {
				// no such shared folder or the folder does not contain the given file
				$fileId = -1;
			}
		}

		$info = $this->scanner->getFileInfo($fileId, $fileOwner, $fileOwnerHome);

		if ($info) {
			return new JSONResponse($info);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function parsePlaylist(string $token, int $fileId) {
		$share = $this->shareManager->getShareByToken($token);
		$fileOwner = $share->getShareOwner();
		$fileOwnerHome = $this->scanner->resolveUserFolder($fileOwner);

		$matchingFolders = $fileOwnerHome->getById($share->getNodeId());

		try {
			$sharedFolder = $matchingFolders[0] ?? null;
			if (!($sharedFolder instanceof Folder)) {
				throw new \OCP\Files\NotFoundException();
			}
			$result = $this->playlistFileService->parseFile($fileId, $sharedFolder);

			// compose the final result
			$result['files'] = \array_map(function ($fileAndCaption) use ($sharedFolder) {
				$file = $fileAndCaption['file'];
				return [
					'id' => $file->getId(),
					'name' => $file->getName(),
					'path' => $sharedFolder->getRelativePath($file->getParent()->getPath()),
					'mimetype' => $file->getMimeType()
				];
			}, $result['files']);
			return new JSONResponse($result);
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}
}
