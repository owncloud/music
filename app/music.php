<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
 */


namespace OCA\Music\App;

use \OCP\AppFramework\App;

use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Controller\AmpacheController;
use \OCA\Music\Controller\ApiController;
use \OCA\Music\Controller\LogController;
use \OCA\Music\Controller\PageController;
use \OCA\Music\Controller\PlaylistApiController;
use \OCA\Music\Controller\SettingController;

use \OCA\Music\DB\AlbumMapper;
use \OCA\Music\DB\AmpacheSessionMapper;
use \OCA\Music\DB\AmpacheUserMapper;
use \OCA\Music\DB\ArtistMapper;
use \OCA\Music\DB\Cache;
use \OCA\Music\DB\PlaylistMapper;
use \OCA\Music\DB\TrackMapper;

use \OCA\Music\Hooks\FileHooks;

use \OCA\Music\Middleware\AmpacheMiddleware;

use \OCA\Music\Utility\AmpacheUser;
use \OCA\Music\Utility\ExtractorGetID3;
use \OCA\Music\Utility\Helper;
use \OCA\Music\Utility\Scanner;
use OCP\AppFramework\IAppContainer;

class Music extends App {

	public function __construct(array $urlParams=array()){
		parent::__construct('music', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('AmpacheController', function($c) {
			return new AmpacheController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('URLGenerator'),
				$c->query('AmpacheUserMapper'),
				$c->query('AmpacheSessionMapper'),
				$c->query('AlbumMapper'),
				$c->query('ArtistMapper'),
				$c->query('TrackMapper'),
				$c->query('AmpacheUser'),
				$c->query('RootFolder')
			);
		});

		$container->registerService('ApiController', function($c) {
			return new ApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('URLGenerator'),
				$c->query('TrackBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('Cache'),
				$c->query('Scanner'),
				$c->query('UserId'),
				$c->query('L10N'),
				$c->query('UserFolder')
			);
		});

		$container->registerService('PageController', function($c) {
			return new PageController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('Scanner')
			);
		});

		$container->registerService('PlaylistApiController', function($c) {
			return new PlaylistApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('URLGenerator'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('UserFolder'),
				$c->query('UserId'),
				$c->query('L10N')
			);
		});

		$container->registerService('LogController', function($c) {
			return new LogController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Logger')
			);
		});

		$container->registerService('SettingController', function($c) {
			return new SettingController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AmpacheUserMapper'),
				$c->query('Scanner'),
				$c->query('UserId'),
				$c->query('UserFolder'),
				$c->query('Config'),
				$c->query('SecureRandom'),
				$c->query('L10N')
			);
		});


		/**
		 * Business Layer
		 */

		$container->registerService('TrackBusinessLayer', function($c) {
			return new TrackBusinessLayer(
				$c->query('TrackMapper'),
				$c->query('Logger')
			);
		});

		$container->registerService('ArtistBusinessLayer', function($c) {
			return new ArtistBusinessLayer(
				$c->query('ArtistMapper'),
				$c->query('Logger')
			);
		});

		$container->registerService('AlbumBusinessLayer', function($c) {
			return new AlbumBusinessLayer(
				$c->query('AlbumMapper'),
				$c->query('Logger')
			);
		});

		$container->registerService('PlaylistBusinessLayer', function($c) {
			return new PlaylistBusinessLayer(
				$c->query('PlaylistMapper'),
				$c->query('Logger')
			);
		});

		/**
		 * Mappers
		 */

		$container->registerService('AlbumMapper', function(IAppContainer $c) {
			return new AlbumMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('AmpacheSessionMapper', function(IAppContainer $c) {
			return new AmpacheSessionMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('AmpacheUserMapper', function(IAppContainer $c) {
			return new AmpacheUserMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('ArtistMapper', function(IAppContainer $c) {
			return new ArtistMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('Cache', function(IAppContainer $c) {
			return new Cache(
					$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('PlaylistMapper', function(IAppContainer $c) {
			return new PlaylistMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		$container->registerService('TrackMapper', function(IAppContainer $c) {
			return new TrackMapper(
				$c->getServer()->getDatabaseConnection()
			);
		});

		/**
		 * Core
		 */

		$container->registerService('Config', function($c){
			return $c->getServer()->getConfig();
		});

		$container->registerService('Db', function(IAppContainer $c) {
			return $c->getServer()->getDatabaseConnection();
		});

		$container->registerService('L10N', function($c) {
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});

		$container->registerService('Logger', function($c) {
			return new Logger(
				$c->query('AppName')
			);
		});

		$container->registerService('URLGenerator', function($c) {
			return $c->getServer()->getURLGenerator();
		});

		$container->registerService('UserFolder', function($c){
			return $c->getServer()->getUserFolder();
		});

		$container->registerService('RootFolder', function($c){
			return $c->getServer()->getRootFolder();
		});

		$container->registerService('UserId', function() {
			return \OCP\User::getUser();
		});

		$container->registerService('SecureRandom', function($c) {
			return $c->getServer()->getSecureRandom();
		});

		/**
		 * Utility
		 */

		$container->registerService('AmpacheUser', function() {
			return new AmpacheUser();
		});

		$container->registerService('ExtractorGetID3', function($c) {
			return new ExtractorGetID3(
				$c->query('Logger')
			);
		});

		$container->registerService('Helper', function(IAppContainer $c) {
			return new Helper(
				$c->query('Db')
			);
		});

		$container->registerService('Scanner', function($c) {
			return new Scanner(
				$c->query('ExtractorGetID3'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('Cache'),
				$c->query('Logger'),
				$c->query('Db'),
				$c->query('UserId'),
				$c->query('Config'),
				$c->query('AppName'),
				$c->query('UserFolder')
			);
		});

		/**
		 * Middleware
		 */

		$container->registerService('AmpacheMiddleware', function($c) {
			return new AmpacheMiddleware(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AmpacheSessionMapper'),
				$c->query('AmpacheUser')
			);
		});

		$container->registerMiddleWare('AmpacheMiddleware');

		/**
		 * Hooks
		 */
		$container->registerService('FileHooks', function($c) {
			return new FileHooks(
				$c->query('ServerContainer')->getRootFolder()
			);
		});
	}
}
