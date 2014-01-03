<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Music\Controller;

use \OCA\Music\Core\API;
use \OCA\Music\AppFramework\Http\Request;
use \OCA\Music\Middleware\AmpacheException;
use \OCA\Music\DB\AmpacheUserMapper;
use \OCA\Music\DB\AmpacheSession;
use \OCA\Music\DB\AmpacheSessionMapper;
use \OCA\Music\DB\AlbumMapper;
use \OCA\Music\DB\ArtistMapper;
use \OCA\Music\DB\TrackMapper;


class AmpacheController extends Controller {

	private $ampacheUserMapper;
	private $ampacheSessionMapper;
	private $albumMapper;
	private $artistMapper;
	private $trackMapper;

	public function __construct(API $api, Request $request, AmpacheUserMapper $ampacheUserMapper,
		AmpacheSessionMapper $ampacheSessionMapper, AlbumMapper $albumMapper, ArtistMapper $artistMapper,
		TrackMapper $trackMapper){
		parent::__construct($api, $request);

		$this->ampacheUserMapper = $ampacheUserMapper;
		$this->ampacheSessionMapper = $ampacheSessionMapper;
		$this->albumMapper = $albumMapper;
		$this->artistMapper = $artistMapper;
		$this->trackMapper = $trackMapper;
	}


	/**
	 * ATTENTION!!!
	 * The following comment turns off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 * @CSRFExemption
	 * @AmpacheAPI
	 */
	public function ampache() {
		switch($this->params('action')) {
			case 'handshake':
				return $this->handshake();
		}
		throw new AmpacheException('TODO', 999);
	}

	public function handshake() {
		$sessionExpiryTime = 6000;

		$userId = $this->params('user');

		// prepare hash check
		$hash = $this->ampacheUserMapper->getPasswordHash($userId);
		$expectedHash = hash('sha256', $this->params('timestamp') . $hash);

		// prepare time check
		$currentTime = time();
		$providedTime = intval($this->params('timestamp'));

		if($hash === null) {
			throw new AmpacheException('Invalid Login - not an ampache user', 401);
		}
		if($providedTime === 0) {
			throw new AmpacheException('Invalid Login - cannot parse time', 401);
		}
		if($providedTime < ($currentTime - $sessionExpiryTime)) {
			throw new AmpacheException('Invalid Login - session is outdated', 401);
		}
		// TODO - while testing with tomahawk it sometimes is $currenttime+1 ... needs further investigation
		if($providedTime > $currentTime + 100) {
			throw new AmpacheException('Invalid Login - timestamp is in future', 401);
		}
		if($expectedHash !== $this->params('auth')) {
			throw new AmpacheException('Invalid Login - passphrase does not match', 401);
		}

		// this can cause collision, but it's just a temporary token
		$token = md5(uniqid(rand(), true));
		$expiryDate = $currentTime + $sessionExpiryTime;

		$session = new AmpacheSession();
		$session->setUserId($userId);
		$session->setToken($token);
		$session->setExpiry($expiryDate);

		$this->ampacheSessionMapper->insert($session);

		$artistCount = $this->artistMapper->count($userId);
		$albumCount = $this->albumMapper->count($userId);
		$trackCount = $this->trackMapper->count($userId);

		return $this->render(
			'ampache/handshake',
			array(
				'token' => $token,
				'songCount' => $trackCount,
				'artistCount' => $artistCount,
				'albumCount' => $albumCount,
				'playlistCount' => 0,
				'updateDate' => $currentTime,
				'cleanDate' => $currentTime,
				'addDate' => $currentTime,
				'expireDate' => $expiryDate
			),
			'blank',
			array('Content-Type' => 'text/xml')
		);
	}
}