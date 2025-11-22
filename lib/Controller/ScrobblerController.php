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
 * @copyright Pauli Järvinen 2019 - 2025
 */

namespace OCA\Music\Controller;

use OCA\Music\Service\ScrobblerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
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
	 * @throws \TypeError when $userId is null
	 */
	public function handleToken(string $token) : ?StandaloneTemplateResponse {
		$sessionResponse = $this->scrobblerService->generateSession($token, $this->userId);
		$success = $sessionResponse === 'ok';
		return new StandaloneTemplateResponse($this->appName, 'scrobble-getsession-result', [
			'lang' => $this->l10n->getLanguageCode(),
			'success' => $success,
			'headline' => $this->l10n->t($success ? 'All set!' : 'Failed to authenticate.'),
			'getsession_response' => $sessionResponse,
			'instructions' => $this->l10n->t(
				$success ?
					'You are now ready to scrobble.' :
					'Authentication failure. Please review the error message and try again.'
			)
		], 'base');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @noSameSiteCookieRequired
	 * @throws \TypeError when $userId is null
	 */
	public function clearSession(): JSONResponse {
		try {
			$this->scrobblerService->clearSession($this->userId);
		} catch (\Throwable $t) {
			$exception = $t;
		}
		return new JSONResponse(
			empty($exception) ? true : [
				'error' => $this->l10n->t($exception->getMessage())
            ]
        );
    }
}
