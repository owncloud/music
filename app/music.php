<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017, 2018
 */


namespace OCA\Music\App;

use \OCP\AppFramework\App;
use \OCP\AppFramework\IAppContainer;

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

use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Db\AmpacheUserMapper;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\Cache;
use \OCA\Music\Db\Maintenance;
use \OCA\Music\Db\PlaylistMapper;
use \OCA\Music\Db\TrackMapper;

use \OCA\Music\Hooks\FileHooks;
use \OCA\Music\Hooks\ShareHooks;
use \OCA\Music\Hooks\UserHooks;

use \OCA\Music\Middleware\AmpacheMiddleware;

use \OCA\Music\Utility\AmpacheUser;
use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\ExtractorGetID3;
use \OCA\Music\Utility\Scanner;

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
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('AmpacheUser'),
				$c->query('RootFolder'),
				$c->query('CoverHelper'),
				$c->query('Logger')
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
				$c->query('CoverHelper'),
				$c->query('UserId'),
				$c->query('L10N'),
				$c->query('UserFolder'),
				$c->query('Logger')
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
				$c->query('URLGenerator')
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
			return $c->getServer()->getL10N($c->query('AppName'));
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

		$container->registerService('UserManager', function($c) {
			return $c->getServer()->getUserManager();
		});

		$container->registerService('GroupManager', function($c) {
			return $c->getServer()->getGroupManager();
		});

		/**
		 * Utility
		 */

		$container->registerService('AmpacheUser', function() {
			return new AmpacheUser();
		});

		$container->registerService('CoverHelper', function($c) {
			return new CoverHelper(
				$c->query('AlbumBusinessLayer'),
				$c->query('ExtractorGetID3'),
				$c->query('Cache'),
				$c->query('Logger')
			);
		});

		$container->registerService('ExtractorGetID3', function($c) {
			return new ExtractorGetID3(
				$c->query('Logger')
			);
		});

		$container->registerService('Maintenance', function(IAppContainer $c) {
			return new Maintenance(
				$c->query('Db'),
				$c->query('Logger')
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
				$c->query('CoverHelper'),
				$c->query('Logger'),
				$c->query('Maintenance'),
				$c->query('Config'),
				$c->query('AppName'),
				$c->query('RootFolder')
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
				$c->getServer()->getRootFolder()
			);
		});

		$container->registerService('ShareHooks', function($c) {
			return new ShareHooks();
		});

		$container->registerService('UserHooks', function($c) {
			return new UserHooks(
				$c->query('ServerContainer')->getUserManager(),
				$c->query('Maintenance')
			);
		});
	}
}
