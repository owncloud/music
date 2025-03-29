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

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig;

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

use OCA\Music\Utility\AmpacheImageService;
use OCA\Music\Utility\CollectionHelper;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\DetailsHelper;
use OCA\Music\Utility\ExtractorGetID3;
use OCA\Music\Utility\LastfmService;
use OCA\Music\Utility\LibrarySettings;
use OCA\Music\Utility\PlaylistFileService;
use OCA\Music\Utility\PodcastService;
use OCA\Music\Utility\RadioService;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Scanner;
use OCA\Music\Utility\StreamTokenService;

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

		// NC26+ no longer ships OCP\AppFramework\Db\Mapper. Create a class alias which refers to this OCP class if available
		// or to our own ponyfill if not (created by copying the said class from NC25).
		if (!\class_exists('\OCA\Music\AppFramework\Db\CompatibleMapper')) {
			if (\class_exists('\OCP\AppFramework\Db\Mapper')) {
				\class_alias(\OCP\AppFramework\Db\Mapper::class, '\OCA\Music\AppFramework\Db\CompatibleMapper');
			} else {
				\class_alias(\OCA\Music\AppFramework\Db\OldNextcloudMapper::class, '\OCA\Music\AppFramework\Db\CompatibleMapper');
			}
		}

		// Create a class alias which refers to the TimedJob either from OC or OCP namespace. The OC version is available
		// on ownCloud and on Nextcloud versions <29. The OCP version is available on NC15+.
		if (!\class_exists('\OCA\Music\BackgroundJob\TimedJob')) {
			if (\class_exists('\OCP\BackgroundJob\TimedJob')) {
				\class_alias(\OCP\BackgroundJob\TimedJob::class, '\OCA\Music\BackgroundJob\TimedJob');
			} else {
				\class_alias(\OC\BackgroundJob\TimedJob::class, '\OCA\Music\BackgroundJob\TimedJob');
			}
		}

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

		 $context->registerService('AdvSearchController', function (IAppContainer $c) {
			return new AdvSearchController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('BookmarkBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('PodcastEpisodeBusinessLayer'),
				$c->query('RadioStationBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('UserId'),
				$c->query('Random'),
				$c->query('Logger')
			);
		});

		$context->registerService('AmpacheController', function (IAppContainer $c) {
			return new AmpacheController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Config'),
				$c->query('L10N'),
				$c->query('URLGenerator'),
				$c->query('UserManager'),
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('BookmarkBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('PodcastEpisodeBusinessLayer'),
				$c->query('RadioStationBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('Library'),
				$c->query('PodcastService'),
				$c->query('AmpacheImageService'),
				$c->query('CoverHelper'),
				$c->query('DetailsHelper'),
				$c->query('LastfmService'),
				$c->query('LibrarySettings'),
				$c->query('Random'),
				$c->query('Logger')
			);
		});

		$context->registerService('AmpacheImageController', function (IAppContainer $c) {
			return new AmpacheImageController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AmpacheImageService'),
				$c->query('CoverHelper'),
				$c->query('LibrarySettings'),
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('Logger')
			);
		});

		$context->registerService('MusicApiController', function (IAppContainer $c) {
			return new MusicApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('TrackBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('Scanner'),
				$c->query('CollectionHelper'),
				$c->query('CoverHelper'),
				$c->query('DetailsHelper'),
				$c->query('LastfmService'),
				$c->query('Maintenance'),
				$c->query('LibrarySettings'),
				$c->query('UserId'),
				$c->query('Logger')
			);
		});

		$context->registerService('CoverApiController', function (IAppContainer $c) {
			return new CoverApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('URLGenerator'),
				$c->query('RootFolder'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('CoverHelper'),
				$c->query('UserId'),
				$c->query('Logger')
			);
		});

		$context->registerService('FavoritesController', function (IAppContainer $c) {
			return new FavoritesController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('PodcastEpisodeBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('UserId')
			);
		});

		$context->registerService('PageController', function (IAppContainer $c) {
			return new PageController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N')
			);
		});

		$context->registerService('PlaylistApiController', function (IAppContainer $c) {
			return new PlaylistApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('URLGenerator'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('CoverHelper'),
				$c->query('PlaylistFileService'),
				$c->query('UserId'),
				$c->query('UserFolder'),
				$c->query('Config'),
				$c->query('Logger')
			);
		});

		$context->registerService('PodcastApiController', function (IAppContainer $c) {
			return new PodcastApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Config'),
				$c->query('URLGenerator'),
				$c->query('PodcastService'),
				$c->query('UserId'),
				$c->query('Logger')
			);
		});

		$context->registerService('LogController', function (IAppContainer $c) {
			return new LogController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Logger')
			);
		});

		$context->registerService('RadioApiController', function (IAppContainer $c) {
			return new RadioApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Config'),
				$c->query('URLGenerator'),
				$c->query('RadioStationBusinessLayer'),
				$c->query('RadioService'),
				$c->query('StreamTokenService'),
				$c->query('PlaylistFileService'),
				$c->query('UserId'),
				$c->query('UserFolder'),
				$c->query('Logger')
			);
		});

		$context->registerService('SettingController', function (IAppContainer $c) {
			return new SettingController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('AmpacheSessionMapper'),
				$c->query('AmpacheUserMapper'),
				$c->query('Scanner'),
				$c->query('UserId'),
				$c->query('LibrarySettings'),
				$c->query('SecureRandom'),
				$c->query('URLGenerator'),
				$c->query('Logger')
			);
		});

		$context->registerService('ShareController', function (IAppContainer $c) {
			return new ShareController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Scanner'),
				$c->query('PlaylistFileService'),
				$c->query('Logger'),
				$c->query('ShareManager')
			);
		});

		$context->registerService('ShivaApiController', function (IAppContainer $c) {
			return new ShivaApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('URLGenerator'),
				$c->query('TrackBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('DetailsHelper'),
				$c->query(('Scanner')),
				$c->query('UserId'),
				$c->query('L10N'),
				$c->query('Logger')
			);
		});

		$context->registerService('SubsonicController', function (IAppContainer $c) {
			return new SubsonicController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('URLGenerator'),
				$c->query('UserManager'),
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('BookmarkBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('PodcastEpisodeBusinessLayer'),
				$c->query('RadioStationBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('LibrarySettings'),
				$c->query('CoverHelper'),
				$c->query('DetailsHelper'),
				$c->query('LastfmService'),
				$c->query('PodcastService'),
				$c->query('AmpacheImageService'),
				$c->query('Random'),
				$c->query('Logger')
			);
		});

		/**
		 * Business Layer
		 */

		$context->registerService('TrackBusinessLayer', function (IAppContainer $c) {
			return new TrackBusinessLayer(
				$c->query('TrackMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('ArtistBusinessLayer', function (IAppContainer $c) {
			return new ArtistBusinessLayer(
				$c->query('ArtistMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('GenreBusinessLayer', function (IAppContainer $c) {
			return new GenreBusinessLayer(
				$c->query('GenreMapper'),
				$c->query('TrackMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('AlbumBusinessLayer', function (IAppContainer $c) {
			return new AlbumBusinessLayer(
				$c->query('AlbumMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('PlaylistBusinessLayer', function (IAppContainer $c) {
			return new PlaylistBusinessLayer(
				$c->query('PlaylistMapper'),
				$c->query('TrackMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('PodcastChannelBusinessLayer', function (IAppContainer $c) {
			return new PodcastChannelBusinessLayer(
				$c->query('PodcastChannelMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('PodcastEpisodeBusinessLayer', function (IAppContainer $c) {
			return new PodcastEpisodeBusinessLayer(
				$c->query('PodcastEpisodeMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('BookmarkBusinessLayer', function (IAppContainer $c) {
			return new BookmarkBusinessLayer(
				$c->query('BookmarkMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('RadioStationBusinessLayer', function ($c) {
			return new RadioStationBusinessLayer(
				$c->query('RadioStationMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('Library', function (IAppContainer $c) {
			return new Library(
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('CoverHelper'),
				$c->query('URLGenerator'),
				$c->query('L10N'),
				$c->query('Logger')
			);
		});

		/**
		 * Mappers
		 */

		$context->registerService('AlbumMapper', function (IAppContainer $c) {
			return new AlbumMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('AmpacheSessionMapper', function (IAppContainer $c) {
			return new AmpacheSessionMapper(
				$c->query('Db')
			);
		});

		$context->registerService('AmpacheUserMapper', function (IAppContainer $c) {
			return new AmpacheUserMapper(
				$c->query('Db')
			);
		});

		$context->registerService('ArtistMapper', function (IAppContainer $c) {
			return new ArtistMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('DbCache', function (IAppContainer $c) {
			return new Cache(
				$c->query('Db')
			);
		});

		$context->registerService('GenreMapper', function (IAppContainer $c) {
			return new GenreMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('PlaylistMapper', function (IAppContainer $c) {
			return new PlaylistMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('PodcastChannelMapper', function (IAppContainer $c) {
			return new PodcastChannelMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('PodcastEpisodeMapper', function (IAppContainer $c) {
			return new PodcastEpisodeMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('TrackMapper', function (IAppContainer $c) {
			return new TrackMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('BookmarkMapper', function (IAppContainer $c) {
			return new BookmarkMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		$context->registerService('RadioStationMapper', function (IAppContainer $c) {
			return new RadioStationMapper(
				$c->query('Db'),
				$c->query('Config')
			);
		});

		/**
		 * Core
		 */

		$context->registerService('Config', function (IAppContainer $c) {
			return $c->getServer()->getConfig();
		});

		$context->registerService('Db', function (IAppContainer $c) {
			return $c->getServer()->getDatabaseConnection();
		});

		$context->registerService('FileCache', function (IAppContainer $c) {
			return $c->getServer()->getCache();
		});

		$context->registerService('L10N', function (IAppContainer $c) {
			return $c->getServer()->getL10N($c->query('AppName'));
		});

		$context->registerService('L10NFactory', function (IAppContainer $c) {
			return $c->getServer()->getL10NFactory();
		});

		$context->registerService('Logger', function (IAppContainer $c) {
			// NC 31 removed the getLogger method but the Psr alternative is not available on OC
			if (\method_exists($c->getServer(), 'getLogger')) {
				$innerLogger = $c->getServer()->getLogger();
			} else {
				$innerLogger = $c->query(\Psr\Log\LoggerInterface::class);
			}
			return new Logger(
				$c->query('AppName'),
				$innerLogger
			);
		});

		$context->registerService('MimeTypeLoader', function (IappContainer $c) {
			return $c->getServer()->getMimeTypeLoader();
		});

		$context->registerService('URLGenerator', function (IAppContainer $c) {
			return $c->getServer()->getURLGenerator();
		});

		$context->registerService('UserFolder', function (IAppContainer $c) {
			return $c->getServer()->getUserFolder();
		});

		$context->registerService('RootFolder', function (IAppContainer $c) {
			return $c->getServer()->getRootFolder();
		});

		$context->registerService('UserId', function (IAppContainer $c) {
			$user = $c->getServer()->getUserSession()->getUser();
			return $user ? $user->getUID() : null;
		});

		$context->registerService('SecureRandom', function (IAppContainer $c) {
			return $c->getServer()->getSecureRandom();
		});

		$context->registerService('UserManager', function (IAppContainer $c) {
			return $c->getServer()->getUserManager();
		});

		$context->registerService('GroupManager', function (IAppContainer $c) {
			return $c->getServer()->getGroupManager();
		});

		$context->registerService('ShareManager', function (IAppContainer $c) {
			return $c->getServer()->getShareManager();
		});

		/**
		 * Utility
		 */

		$context->registerService('AmpacheImageService', function (IAppContainer $c) {
			return new AmpacheImageService(
				$c->query('AmpacheUserMapper'),
				$c->query('Logger')
			);
		});

		$context->registerService('CollectionHelper', function (IAppContainer $c) {
			return new CollectionHelper(
				$c->query('Library'),
				$c->query('FileCache'),
				$c->query('DbCache'),
				$c->query('Logger'),
				$c->query('UserId')
			);
		});

		$context->registerService('CoverHelper', function (IAppContainer $c) {
			return new CoverHelper(
				$c->query('ExtractorGetID3'),
				$c->query('DbCache'),
				$c->query('AlbumBusinessLayer'),
				$c->query('Config'),
				$c->query('L10N'),
				$c->query('Logger')
			);
		});

		$context->registerService('DetailsHelper', function (IAppContainer $c) {
			return new DetailsHelper(
				$c->query('ExtractorGetID3'),
				$c->query('Logger')
			);
		});

		$context->registerService('ExtractorGetID3', function (IAppContainer $c) {
			return new ExtractorGetID3(
				$c->query('Logger')
			);
		});

		$context->registerService('LastfmService', function (IAppContainer $c) {
			return new LastfmService(
				$c->query('AlbumBusinessLayer'),
				$c->query('ArtistBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('Config'),
				$c->query('Logger')
			);
		});

		$context->registerService('Maintenance', function (IAppContainer $c) {
			return new Maintenance(
				$c->query('Db'),
				$c->query('Logger')
			);
		});

		$context->registerService('PlaylistFileService', function (IAppContainer $c) {
			return new PlaylistFileService(
				$c->query('PlaylistBusinessLayer'),
				$c->query('RadioStationBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('StreamTokenService'),
				$c->query('Logger')
			);
		});

		$context->registerService('PodcastService', function (IAppContainer $c) {
			return new PodcastService(
				$c->query('PodcastChannelBusinessLayer'),
				$c->query('PodcastEpisodeBusinessLayer'),
				$c->query('Logger')
			);
		});

		$context->registerService('RadioService', function (IAppContainer $c) {
			return new RadioService(
				$c->query('URLGenerator'),
				$c->query('StreamTokenService'),
				$c->query('Logger')
			);
		});

		$context->registerService('Random', function (IAppContainer $c) {
			return new Random(
				$c->query('DbCache'),
				$c->query('Logger')
			);
		});

		$context->registerService('Scanner', function (IAppContainer $c) {
			return new Scanner(
				$c->query('ExtractorGetID3'),
				$c->query('ArtistBusinessLayer'),
				$c->query('AlbumBusinessLayer'),
				$c->query('TrackBusinessLayer'),
				$c->query('PlaylistBusinessLayer'),
				$c->query('GenreBusinessLayer'),
				$c->query('DbCache'),
				$c->query('CoverHelper'),
				$c->query('Logger'),
				$c->query('Maintenance'),
				$c->query('LibrarySettings'),
				$c->query('RootFolder'),
				$c->query('Config'),
				$c->query('L10NFactory')
			);
		});

		$context->registerService('StreamTokenService', function (IAppContainer $c) {
			return new StreamTokenService(
				$c->query('DbCache')
			);
		});
	
		$context->registerService('LibrarySettings', function (IAppContainer $c) {
			return new LibrarySettings(
				$c->query('AppName'),
				$c->query('Config'),
				$c->query('RootFolder'),
				$c->query('Logger')
			);
		});

		/**
		 * Middleware
		 */

		$context->registerService('AmpacheMiddleware', function (IAppContainer $c) {
			return new AmpacheMiddleware(
				$c->query('Request'),
				$c->query('Config'),
				$c->query('AmpacheSessionMapper'),
				$c->query('AmpacheUserMapper'),
				$c->query('Logger'),
				$c->query('UserId')
			);
		});
		$context->registerMiddleWare('AmpacheMiddleware');

		$context->registerService('SubsonicMiddleware', function (IAppContainer $c) {
			return new SubsonicMiddleware(
				$c->query('Request'),
				$c->query('AmpacheUserMapper'), /* not a mistake, the mapper is shared between the APIs */
				$c->query('Logger')
			);
		});
		$context->registerMiddleWare('SubsonicMiddleware');

		/**
		 * Hooks
		 */
		$context->registerService('FileHooks', function (IAppContainer $c) {
			return new FileHooks(
				$c->getServer()->getRootFolder()
			);
		});

		$context->registerService('ShareHooks', function (/** @scrutinizer ignore-unused */ IAppContainer $c) {
			return new ShareHooks();
		});

		$context->registerService('UserHooks', function (IAppContainer $c) {
			return new UserHooks(
				$c->query('ServerContainer')->getUserManager(),
				$c->query('Maintenance')
			);
		});
	}

	/**
	 * This gets called on Nextcloud but not on ownCloud
	 */
	public function register(/*\OCP\AppFramework\Bootstrap\IRegistrationContext*/ $context) : void {
		$this->registerServices($context);
		$context->registerDashboardWidget(\OCA\Music\Dashboard\MusicWidget::class);
	}

	/**
	 * This gets called on Nextcloud but not on ownCloud
	 */
	public function boot(/*\OCP\AppFramework\Bootstrap\IBootContext*/ $context) : void {
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
		$container->query('FileHooks')->register();
		$container->query('ShareHooks')->register();
		$container->query('UserHooks')->register();
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
		$config = $container->query('Config');
		$radioSources = $config->getSystemValue('music.allowed_stream_src', []);

		if (\is_string($radioSources)) {
			$radioSources = [$radioSources];
		}

		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();

		foreach ($radioSources as $source) {
			$policy->addAllowedMediaDomain($source);
		}

		// The media sources 'data:' and 'blob:' are needed for HLS streaming
		if (self::hlsEnabled($config, $container->query('UserId'))) {
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
