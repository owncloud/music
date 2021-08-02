<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\IRequest;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use \OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\Util;

class PodcastApiController extends Controller {
	private $channelBusinessLayer;
	private $episodeBusinessLayer;
	private $userId;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								PodcastChannelBusinessLayer $channelBusinessLayer,
								PodcastEpisodeBusinessLayer $episodeBusinessLayer,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
		$this->userId = $userId ?? ''; // ensure non-null to satisfy Scrutinizer; the null case should happen only when the user has already logged out
		$this->logger = $logger;
	}

	/**
	 * lists all podcasts
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$episodes = $this->episodeBusinessLayer->findAll($this->userId);
		$episodesLut = [];
		foreach ($episodes as $episode) {
			$episodesLut[$episode->getChannelId()][] = $episode->toApi();
		}

		$channels = Util::arrayMapMethod($this->channelBusinessLayer->findAll($this->userId), 'toApi');
		foreach ($channels as &$channel) {
			$channel['episodes'] = $episodesLut[$channel['id']] ?? [];
		}

		return $channels;
	}

	/**
	 * add a followed podcast
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function subscribe(?string $url) {
		if ($url === null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Mandatory argument 'url' not given");
		} else {
			// TODO
		}
	}

	/**
	 * deletes a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function unsubscribe(int $id) {
		try {
			// TODO
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get a single radio station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id) {
		try {
			// TODO
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * reset all the subscribed podcasts of the user
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function resetAll() {
		// TODO
		return new JSONResponse(['success' => true]);
	}
}
