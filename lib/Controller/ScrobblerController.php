<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matthew Wells
 * @copyright Matthew Wells 2025
 */

namespace OCA\Music\Controller;

use OCA\Music\Service\ScrobblerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\IL10N;
use OCP\IRequest;

class ScrobblerController extends Controller {
	private IL10N $l10n;

	private ?string $userId;

	private ScrobblerService $scrobblerService;

	public function __construct(string $appName,
								IRequest $request,
								IL10N $l10n,
								?string $userId,
								ScrobblerService $scrobblerService) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->userId = $userId;
		$this->appName = $appName;
		$this->scrobblerService = $scrobblerService;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function handleToken(string $token) : StandaloneTemplateResponse {
		try {
			$this->scrobblerService->generateSession($token, $this->userId);
			$success = true;
			$headline = 'All Set!';
			$getSessionResponse = '';
			$instructions = 'You are now ready to scrobble.';
		} catch (\Throwable $t) {
			$success = false;
			$headline = 'Failed to authenticate.';
			$getSessionResponse = $t->getMessage();
			$instructions = 'Authentication failure. Please review the error message and try again.';
		} finally {
			return new StandaloneTemplateResponse($this->appName, 'scrobble-getsession-result', [
				'lang' => $this->l10n->getLanguageCode(),
				'success' => $success,
				'headline' => $this->l10n->t($headline),
				'getsession_response' => $getSessionResponse,
				'instructions' => $this->l10n->t($instructions)
			], 'base');
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @noSameSiteCookieRequired
	 */
	public function clearSession(): JSONResponse {
		$result = $this->scrobblerService->clearSession($this->userId);
		return new JSONResponse($result ?: ['error' => [
			'message' =>$this->l10n->t('Check the error log for details.')
		]]);
	}
}
