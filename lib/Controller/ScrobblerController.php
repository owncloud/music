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

	/** @var ScrobblerService[] $scrobblerServices */
	private array $scrobblerServices;

	public function __construct(string $appName,
								IRequest $request,
								IL10N $l10n,
								?string $userId,
								array $scrobblerServices) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->userId = $userId;
		$this->appName = $appName;
		$this->scrobblerServices = $scrobblerServices;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function handleToken(string $serviceIdentifier, ?string $token) : StandaloneTemplateResponse {
		$params = [
			'lang' => $this->l10n->getLanguageCode(),
			'success' => false,
			'headline' => $this->l10n->t('Unexpected error'),
			'identifier' => $serviceIdentifier,
			'getsession_response' => '',
			'instructions' => $this->l10n->t('Please contact your server administrator for assistance.')
		];
		$response = new StandaloneTemplateResponse($this->appName, 'scrobble-getsession-result', [], 'base');

		$scrobblerService = $this->getScrobblerService($serviceIdentifier);

		if (!$scrobblerService) {
			$params['headline'] = $this->l10n->t('Unknown Service');
			$params['getsession_response'] = $this->l10n->t('Unkonwn service %s', [$serviceIdentifier]);
			$response->setParams($params);
			return $response;
		}

		try {
			$scrobblerService->generateSession($token, $this->userId);
			$params['success'] = true;
			$params['headline'] = $this->l10n->t('All Set!');
			$params['instructions'] = $this->l10n->t('You are now ready to scrobble.');
			$params['getsession_response'] = '';
		} catch (ScrobbleServiceException $e) {
			$params['headline'] = $this->l10n->t('Authentication failure');
			$params['instructions'] = $this->l10n->t('Please review the error message prior to trying again.');
			$params['getsession_response'] = $e->getMessage();
		} catch (\Exception $t) {
			$params['getsession_response'] = $t->getMessage();
		} catch (\TypeError $t) {
			$params['getsession_response'] = $t->getMessage();
		} finally {
			$response->setParams($params);
			return $response;
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @noSameSiteCookieRequired
	 */
	public function clearSession(string $serviceIdentifier): JSONResponse {
		$response = new JSONResponse(['error' => [
			'message' => 'Unknown error'
		]]);

		$scrobblerService = $this->getScrobblerService($serviceIdentifier);
		if (!$scrobblerService) {
			$response->setData(['error' => [
				'message' => $this->l10n->t('Unknown service %s', [$serviceIdentifier])
			]]);
			return $response;
		}

		try {
			$scrobblerService->clearSession($this->userId);
			$response->setData(['success' => true]);
		} catch (\InvalidArgumentException $e) {
			$response->setData(['error' => [
				'message' => $this->l10n->t('Check the error log for details.')
			]]);
		} finally {
			return $response;
		}
	}

	private function getScrobblerService(string $serviceIdentifier) : ?ScrobblerService {
		foreach ($this->scrobblerServices as $scrobblerService) {
			if ($scrobblerService->getIdentifier() === $serviceIdentifier) {
				return $scrobblerService;
			}
		}
		return null;
	}
}
