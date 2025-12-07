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

use OCA\Music\Service\ScrobbleServiceException;
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
	public function handleToken(?string $token) : StandaloneTemplateResponse {
		$success = false;
		$headline = $this->l10n->t('Unexpected error');
		$instructions = $this->l10n->t('Please contact your server administrator for assistance.');
		$getSessionResponse = '';
		try {
			$this->scrobblerService->generateSession($token, $this->userId);
			$success = true;
			$headline = $this->l10n->t('All Set!');
			$instructions = $this->l10n->t('You are now ready to scrobble.');
		} catch (ScrobbleServiceException $e) {
			$headline = $this->l10n->t('Authentication failure');
			$instructions = $this->l10n->t('Please review the error message prior to trying again.');
			$getSessionResponse = $e->getMessage();
		} catch (\Exception $t) {
			$getSessionResponse = $t->getMessage();
		} catch (\TypeError $t) {
			$getSessionResponse = $t->getMessage();
		} finally {
			return new StandaloneTemplateResponse($this->appName, 'scrobble-getsession-result', [
				'lang' => $this->l10n->getLanguageCode(),
				'success' => $success,
				'headline' => $headline,
				'getsession_response' => $getSessionResponse,
				'instructions' => $instructions
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
