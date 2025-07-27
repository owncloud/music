<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\AppInfo;

use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\BookmarkBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\Library;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;

use OCA\Music\Controller\AdvSearchController;
use OCA\Music\Controller\AmpacheController;
use OCA\Music\Controller\AmpacheImageController;
use OCA\Music\Controller\CoverApiController;
use OCA\Music\Controller\FavoritesController;
use OCA\Music\Controller\LogController;
use OCA\Music\Controller\MusicApiController;
use OCA\Music\Controller\PageController;
use OCA\Music\Controller\PlaylistApiController;
use OCA\Music\Controller\PodcastApiController;
use OCA\Music\Controller\RadioApiController;
use OCA\Music\Controller\SettingController;
use OCA\Music\Controller\ShareController;
use OCA\Music\Controller\ShivaApiController;
use OCA\Music\Controller\SubsonicController;

use OCA\Music\Db\AlbumMapper;
use OCA\Music\Db\AmpacheSessionMapper;
use OCA\Music\Db\AmpacheUserMapper;
use OCA\Music\Db\ArtistMapper;
use OCA\Music\Db\BookmarkMapper;
use OCA\Music\Db\Cache;
use OCA\Music\Db\GenreMapper;
use OCA\Music\Db\Maintenance;
use OCA\Music\Db\PlaylistMapper;
use OCA\Music\Db\PodcastChannelMapper;
use OCA\Music\Db\PodcastEpisodeMapper;
use OCA\Music\Db\RadioStationMapper;
use OCA\Music\Db\TrackMapper;

use OCA\Music\Hooks\FileHooks;
use OCA\Music\Hooks\ShareHooks;
use OCA\Music\Hooks\UserHooks;

use OCA\Music\Middleware\AmpacheMiddleware;
use OCA\Music\Middleware\SubsonicMiddleware;

use OCA\Music\Service\AmpacheImageService;
use OCA\Music\Service\CollectionService;
use OCA\Music\Service\CoverService;
use OCA\Music\Service\DetailsService;
use OCA\Music\Service\ExtractorGetID3;
use OCA\Music\Service\LastfmService;
use OCA\Music\Service\LibrarySettings;
use OCA\Music\Service\PlaylistFileService;
use OCA\Music\Service\PodcastService;
use OCA\Music\Service\RadioService;
use OCA\Music\Service\Scanner;
use OCA\Music\Service\StreamTokenService;

use OCA\Music\Utility\Random;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\ICache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

// The IBootstrap interface is not available on ownCloud. Create a thin base class to hide this difference
// from the actual Application class.
function useOwncloudBootstrapping() : bool {
	return (\OCA\Music\Utility\AppInfo::getVendor() == 'owncloud');
}

if (useOwncloudBootstrapping()) {
	class ApplicationBase extends App {}
} else {
	abstract class ApplicationBase extends App implements \OCP\AppFramework\Bootstrap\IBootstrap {}
}

class Application extends ApplicationBase {
	public function __construct(array $urlParams=[]) {
		parent::__construct('music', $urlParams);

		\mb_internal_encoding('UTF-8');

		// On ownCloud, the registrations must happen already within the constructor
		if (useOwncloudBootstrapping()) {
			$this->registerServices($this->getContainer());
		}
	}

	/**
	 * @param mixed $context On Nextcloud, this is \OCP\AppFramework\Bootstrap\IRegistrationContext.
	 *                       On ownCloud, this is \OCP\AppFramework\IAppContainer.
	 */
	private function registerServices($context) : void {
		/**
		 * Controllers
		 */

		 $context->registerService(AdvSearchController::class, function (IAppContainer $c) {
			return new AdvSearchController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(BookmarkBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(PodcastEpisodeBusinessLayer::class),
				$c->query(RadioStationBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query('userId'),
				$c->query(Random::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(AmpacheController::class, function (IAppContainer $c) {
			return new AmpacheController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IConfig::class),
				$c->query(IL10N::class),
				$c->query(IURLGenerator::class),
				$c->query(IUserManager::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(BookmarkBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(PodcastEpisodeBusinessLayer::class),
				$c->query(RadioStationBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(Library::class),
				$c->query(PodcastService::class),
				$c->query(AmpacheImageService::class),
				$c->query(CoverService::class),
				$c->query(DetailsService::class),
				$c->query(LastfmService::class),
				$c->query(LibrarySettings::class),
				$c->query(Random::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(AmpacheImageController::class, function (IAppContainer $c) {
			return new AmpacheImageController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(AmpacheImageService::class),
				$c->query(CoverService::class),
				$c->query(LibrarySettings::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(MusicApiController::class, function (IAppContainer $c) {
			return new MusicApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(Scanner::class),
				$c->query(CollectionService::class),
				$c->query(CoverService::class),
				$c->query(DetailsService::class),
				$c->query(LastfmService::class),
				$c->query(Maintenance::class),
				$c->query(LibrarySettings::class),
				$c->query('userId'),
				$c->query(Logger::class)
			);
		});

		$context->registerService(CoverApiController::class, function (IAppContainer $c) {
			return new CoverApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IURLGenerator::class),
				$c->query(IRootFolder::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(CoverService::class),
				$c->query('userId'),
				$c->query(Logger::class)
			);
		});

		$context->registerService(FavoritesController::class, function (IAppContainer $c) {
			return new FavoritesController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(PodcastEpisodeBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query('userId')
			);
		});

		$context->registerService(PageController::class, function (IAppContainer $c) {
			return new PageController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IL10N::class)
			);
		});

		$context->registerService(PlaylistApiController::class, function (IAppContainer $c) {
			return new PlaylistApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IURLGenerator::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(CoverService::class),
				$c->query(PlaylistFileService::class),
				$c->query('userId'),
				$c->query('userFolder'),
				$c->query(IConfig::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PodcastApiController::class, function (IAppContainer $c) {
			return new PodcastApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IConfig::class),
				$c->query(IURLGenerator::class),
				$c->query(IRootFolder::class),
				$c->query(PodcastService::class),
				$c->query('userId'),
				$c->query(Logger::class)
			);
		});

		$context->registerService(LogController::class, function (IAppContainer $c) {
			return new LogController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(RadioApiController::class, function (IAppContainer $c) {
			return new RadioApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IConfig::class),
				$c->query(IURLGenerator::class),
				$c->query(RadioStationBusinessLayer::class),
				$c->query(RadioService::class),
				$c->query(StreamTokenService::class),
				$c->query(PlaylistFileService::class),
				$c->query('userId'),
				$c->query('userFolder'),
				$c->query(Logger::class)
			);
		});

		$context->registerService(SettingController::class, function (IAppContainer $c) {
			return new SettingController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(AmpacheSessionMapper::class),
				$c->query(AmpacheUserMapper::class),
				$c->query(Scanner::class),
				$c->query('userId'),
				$c->query(LibrarySettings::class),
				$c->query(ISecureRandom::class),
				$c->query(IURLGenerator::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(ShareController::class, function (IAppContainer $c) {
			return new ShareController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(Scanner::class),
				$c->query(PlaylistFileService::class),
				$c->query(Logger::class),
				$c->query(\OCP\Share\IManager::class)
			);
		});

		$context->registerService(ShivaApiController::class, function (IAppContainer $c) {
			return new ShivaApiController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IURLGenerator::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(DetailsService::class),
				$c->query(('Scanner')),
				$c->query('userId'),
				$c->query(IL10N::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(SubsonicController::class, function (IAppContainer $c) {
			return new SubsonicController(
				$c->query('appName'),
				$c->query(IRequest::class),
				$c->query(IL10N::class),
				$c->query(IURLGenerator::class),
				$c->query(IUserManager::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(BookmarkBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(PodcastEpisodeBusinessLayer::class),
				$c->query(RadioStationBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(LibrarySettings::class),
				$c->query(CoverService::class),
				$c->query(DetailsService::class),
				$c->query(LastfmService::class),
				$c->query(PodcastService::class),
				$c->query(AmpacheImageService::class),
				$c->query(Random::class),
				$c->query(Logger::class)
			);
		});

		/**
		 * Business Layer
		 */

		$context->registerService(TrackBusinessLayer::class, function (IAppContainer $c) {
			return new TrackBusinessLayer(
				$c->query(TrackMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(ArtistBusinessLayer::class, function (IAppContainer $c) {
			return new ArtistBusinessLayer(
				$c->query(ArtistMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(GenreBusinessLayer::class, function (IAppContainer $c) {
			return new GenreBusinessLayer(
				$c->query(GenreMapper::class),
				$c->query(TrackMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(AlbumBusinessLayer::class, function (IAppContainer $c) {
			return new AlbumBusinessLayer(
				$c->query(AlbumMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PlaylistBusinessLayer::class, function (IAppContainer $c) {
			return new PlaylistBusinessLayer(
				$c->query(PlaylistMapper::class),
				$c->query(TrackMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PodcastChannelBusinessLayer::class, function (IAppContainer $c) {
			return new PodcastChannelBusinessLayer(
				$c->query(PodcastChannelMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PodcastEpisodeBusinessLayer::class, function (IAppContainer $c) {
			return new PodcastEpisodeBusinessLayer(
				$c->query(PodcastEpisodeMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(BookmarkBusinessLayer::class, function (IAppContainer $c) {
			return new BookmarkBusinessLayer(
				$c->query(BookmarkMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(RadioStationBusinessLayer::class, function ($c) {
			return new RadioStationBusinessLayer(
				$c->query(RadioStationMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(Library::class, function (IAppContainer $c) {
			return new Library(
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(CoverService::class),
				$c->query(IURLGenerator::class),
				$c->query(IL10N::class),
				$c->query(Logger::class)
			);
		});

		/**
		 * Mappers
		 */

		$context->registerService(AlbumMapper::class, function (IAppContainer $c) {
			return new AlbumMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(AmpacheSessionMapper::class, function (IAppContainer $c) {
			return new AmpacheSessionMapper(
				$c->query(IDBConnection::class)
			);
		});

		$context->registerService(AmpacheUserMapper::class, function (IAppContainer $c) {
			return new AmpacheUserMapper(
				$c->query(IDBConnection::class)
			);
		});

		$context->registerService(ArtistMapper::class, function (IAppContainer $c) {
			return new ArtistMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(Cache::class, function (IAppContainer $c) {
			return new Cache(
				$c->query(IDBConnection::class)
			);
		});

		$context->registerService(GenreMapper::class, function (IAppContainer $c) {
			return new GenreMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(PlaylistMapper::class, function (IAppContainer $c) {
			return new PlaylistMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(PodcastChannelMapper::class, function (IAppContainer $c) {
			return new PodcastChannelMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(PodcastEpisodeMapper::class, function (IAppContainer $c) {
			return new PodcastEpisodeMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(TrackMapper::class, function (IAppContainer $c) {
			return new TrackMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(BookmarkMapper::class, function (IAppContainer $c) {
			return new BookmarkMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		$context->registerService(RadioStationMapper::class, function (IAppContainer $c) {
			return new RadioStationMapper(
				$c->query(IDBConnection::class),
				$c->query(IConfig::class)
			);
		});

		/**
		 * Core
		 */

		$context->registerService(IConfig::class, function (IAppContainer $c) {
			return $c->getServer()->getConfig();
		});

		$context->registerService(IDBConnection::class, function (IAppContainer $c) {
			return $c->getServer()->getDatabaseConnection();
		});

		$context->registerService(ICache::class, function (IAppContainer $c) {
			return $c->getServer()->getCache();
		});

		$context->registerService(IL10N::class, function (IAppContainer $c) {
			return $c->getServer()->getL10N($c->query('appName'));
		});

		$context->registerService(\OCP\L10N\IFactory::class, function (IAppContainer $c) {
			return $c->getServer()->getL10NFactory();
		});

		$context->registerService(Logger::class, function (IAppContainer $c) {
			return new Logger(
				$c->query('appName'),
				$c->query(IServerContainer::class)
			);
		});

		$context->registerService(IMimeTypeLoader::class, function (IAppContainer $c) {
			return $c->getServer()->getMimeTypeLoader();
		});

		$context->registerService(IURLGenerator::class, function (IAppContainer $c) {
			return $c->getServer()->getURLGenerator();
		});

		$context->registerService('userFolder', function (IAppContainer $c) {
			return $c->getServer()->getUserFolder();
		});

		$context->registerService(IRootFolder::class, function (IAppContainer $c) {
			return $c->getServer()->getRootFolder();
		});

		$context->registerService('userId', function (IAppContainer $c) {
			$user = $c->getServer()->getUserSession()->getUser();
			return $user ? $user->getUID() : null;
		});

		$context->registerService(ISecureRandom::class, function (IAppContainer $c) {
			return $c->getServer()->getSecureRandom();
		});

		$context->registerService(IUserManager::class, function (IAppContainer $c) {
			return $c->getServer()->getUserManager();
		});

		$context->registerService(IGroupManager::class, function (IAppContainer $c) {
			return $c->getServer()->getGroupManager();
		});

		$context->registerService(\OCP\Share\IManager::class, function (IAppContainer $c) {
			return $c->getServer()->getShareManager();
		});

		/**
		 * Utility
		 */

		$context->registerService(AmpacheImageService::class, function (IAppContainer $c) {
			return new AmpacheImageService(
				$c->query(AmpacheUserMapper::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(CollectionService::class, function (IAppContainer $c) {
			return new CollectionService(
				$c->query(Library::class),
				$c->query(ICache::class),
				$c->query(Cache::class),
				$c->query(Logger::class),
				$c->query('userId')
			);
		});

		$context->registerService(CoverService::class, function (IAppContainer $c) {
			return new CoverService(
				$c->query(ExtractorGetID3::class),
				$c->query(Cache::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(IConfig::class),
				$c->query(IL10N::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(DetailsService::class, function (IAppContainer $c) {
			return new DetailsService(
				$c->query(ExtractorGetID3::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(ExtractorGetID3::class, function (IAppContainer $c) {
			return new ExtractorGetID3(
				$c->query(Logger::class)
			);
		});

		$context->registerService(LastfmService::class, function (IAppContainer $c) {
			return new LastfmService(
				$c->query(AlbumBusinessLayer::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(IConfig::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(Maintenance::class, function (IAppContainer $c) {
			return new Maintenance(
				$c->query(IDBConnection::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PlaylistFileService::class, function (IAppContainer $c) {
			return new PlaylistFileService(
				$c->query(PlaylistBusinessLayer::class),
				$c->query(RadioStationBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(StreamTokenService::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(PodcastService::class, function (IAppContainer $c) {
			return new PodcastService(
				$c->query(PodcastChannelBusinessLayer::class),
				$c->query(PodcastEpisodeBusinessLayer::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(RadioService::class, function (IAppContainer $c) {
			return new RadioService(
				$c->query(IURLGenerator::class),
				$c->query(StreamTokenService::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(Random::class, function (IAppContainer $c) {
			return new Random(
				$c->query(Cache::class),
				$c->query(Logger::class)
			);
		});

		$context->registerService(Scanner::class, function (IAppContainer $c) {
			return new Scanner(
				$c->query(ExtractorGetID3::class),
				$c->query(ArtistBusinessLayer::class),
				$c->query(AlbumBusinessLayer::class),
				$c->query(TrackBusinessLayer::class),
				$c->query(PlaylistBusinessLayer::class),
				$c->query(GenreBusinessLayer::class),
				$c->query(Cache::class),
				$c->query(CoverService::class),
				$c->query(Logger::class),
				$c->query(Maintenance::class),
				$c->query(LibrarySettings::class),
				$c->query(IRootFolder::class),
				$c->query(IConfig::class),
				$c->query(\OCP\L10N\IFactory::class)
			);
		});

		$context->registerService(StreamTokenService::class, function (IAppContainer $c) {
			return new StreamTokenService(
				$c->query(Cache::class)
			);
		});
	
		$context->registerService(LibrarySettings::class, function (IAppContainer $c) {
			return new LibrarySettings(
				$c->query('appName'),
				$c->query(IConfig::class),
				$c->query(IRootFolder::class),
				$c->query(Logger::class)
			);
		});

		/**
		 * Middleware
		 */

		$context->registerService(AmpacheMiddleware::class, function (IAppContainer $c) {
			return new AmpacheMiddleware(
				$c->query(IRequest::class),
				$c->query(IConfig::class),
				$c->query(AmpacheSessionMapper::class),
				$c->query(AmpacheUserMapper::class),
				$c->query(Logger::class),
				$c->query('userId')
			);
		});
		$context->registerMiddleWare(AmpacheMiddleware::class);

		$context->registerService(SubsonicMiddleware::class, function (IAppContainer $c) {
			return new SubsonicMiddleware(
				$c->query(IRequest::class),
				$c->query(AmpacheUserMapper::class), /* not a mistake, the mapper is shared between the APIs */
				$c->query(Logger::class)
			);
		});
		$context->registerMiddleWare(SubsonicMiddleware::class);

		/**
		 * Hooks
		 */
		$context->registerService(FileHooks::class, function (IAppContainer $c) {
			return new FileHooks(
				$c->getServer()->getRootFolder()
			);
		});

		$context->registerService(ShareHooks::class, function (/** @scrutinizer ignore-unused */ IAppContainer $c) {
			return new ShareHooks();
		});

		$context->registerService(UserHooks::class, function (IAppContainer $c) {
			return new UserHooks(
				$c->query(IUserManager::class),
				$c->query(Maintenance::class)
			);
		});
	}

	/**
	 * This gets called on Nextcloud but not on ownCloud
	 * @param \OCP\AppFramework\Bootstrap\IRegistrationContext $context
	 */
	public function register($context) : void {
		$this->registerServices($context);
		$context->registerDashboardWidget(\OCA\Music\Dashboard\MusicWidget::class);
	}

	/**
	 * This gets called on Nextcloud but not on ownCloud
	 * @param \OCP\AppFramework\Bootstrap\IBootContext $context
	 */
	public function boot($context) : void {
		$this->init();
		$this->registerEmbeddedPlayer();
	}

	public function init() : void {
		$this->registerHooks();

		// Adjust the CSP if loading the Music app proper or the NC dashboard
		$url = $this->getRequestUrl();
		if (\preg_match('%/apps/music/?$%', $url) || \preg_match('%/apps/dashboard/?$%', $url)) {
			$this->adjustCsp();
		}
	}

	/**
	 * Load embedded music player for Files and Sharing apps
	 */
	public function loadEmbeddedMusicPlayer() : void {
		\OCA\Music\Utility\HtmlUtil::addWebpackScript('files_music_player');
		\OCA\Music\Utility\HtmlUtil::addWebpackStyle('files_music_player');
		$this->adjustCsp();
	}

	private function getRequestUrl() : string {
		$request = $this->getContainer()->getServer()->getRequest();
		$url = $request->server['REQUEST_URI'] ?? '';
		$url = \explode('?', $url)[0]; // get rid of any query args
		$url = \explode('#', $url)[0]; // get rid of any hash part
		return $url;
	}

	private function registerHooks() : void {
		$container = $this->getContainer();
		$container->query(FileHooks::class)->register();
		$container->query(ShareHooks::class)->register();
		$container->query(UserHooks::class)->register();
	}

	private function registerEmbeddedPlayer() : void {
		$dispatcher = $this->getContainer()->query(\OCP\EventDispatcher\IEventDispatcher::class);

		// Files app
		$dispatcher->addListener(\OCA\Files\Event\LoadAdditionalScriptsEvent::class, function() {
			$this->loadEmbeddedMusicPlayer();
		});

		// Files_Sharing app
		$dispatcher->addListener(\OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent::class, function(\OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent $event) {
			// don't load the embedded player on the authentication page of password-protected share, and only load it for shared folders (not individual files)
			if ($event->getScope() != \OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH
					&& $event->getShare()->getNodeType() == 'folder') {
				$this->loadEmbeddedMusicPlayer();
			}
		});
	}

	/**
	 * Set content security policy to allow streaming media from the configured external sources
	 */
	private function adjustCsp() : void {
		$container = $this->getContainer();

		/** @var IConfig $config */
		$config = $container->query(IConfig::class);
		$radioSources = $config->getSystemValue('music.allowed_stream_src', []);

		if (\is_string($radioSources)) {
			$radioSources = [$radioSources];
		}

		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();

		foreach ($radioSources as $source) {
			$policy->addAllowedMediaDomain($source);
		}

		// The media sources 'data:' and 'blob:' are needed for HLS streaming
		if (self::hlsEnabled($config, $container->query('userId'))) {
			$policy->addAllowedMediaDomain('data:');
			$policy->addAllowedMediaDomain('blob:');
		}

		$container->getServer()->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
	}

	private static function hlsEnabled(IConfig $config, ?string $userId) : bool {
		$enabled = $config->getSystemValue('music.enable_radio_hls', true);
		if (empty($userId)) {
			$enabled = (bool)$config->getSystemValue('music.enable_radio_hls_on_share', $enabled);
		}
		return $enabled;
	}
}
