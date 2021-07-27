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
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\Util;

class PodcastApiController extends Controller {
	private $businessLayer;
	private $playlistFileService;
	private $userId;
	private $userFolder;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
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
		// TODO: This is just a mock-up
		$rssFeeds = [
			'https://audioboom.com/channels/5039800.rss',
			'https://feeds.npr.org/510289/podcast.xml'
		];

		foreach ($rssFeeds as $index => $feed) {
			$xmlTree = \simplexml_load_string(
				\file_get_contents($feed),
				\SimpleXMLElement::class,
				LIBXML_NOCDATA);

			$channel = [
				'id' => $index + 1,
				'title' => (string)$xmlTree->channel->title,
				'image' => (string)$xmlTree->channel->image->url
			];

			foreach ($xmlTree->channel->item as $item) {
				$channel['episodes'][] = [
					'title' => (string)$item->title,
					'url' => (string)$item->enclosure->attributes()['url']
				];
			}

			$result[] = $channel;
		}

		return $result;
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
