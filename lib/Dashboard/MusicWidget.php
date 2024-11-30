<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

namespace OCA\Music\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Widget for the Nextcloud Dashboard. This class is not used on ownCloud.
 */
class MusicWidget implements IWidget
{
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @return string Unique id that identifies the widget, e.g. the app id
	 */
	public function getId() : string
	{
		return 'music';
	}

	/**
	 * @return string User facing title of the widget
	 */
	public function getTitle() : string
	{
		return $this->l10n->t('Music');
	}

	/**
	 * @return int Initial order for widget sorting in the range of 10-100, 0-9 are reserved for shipped apps
	 */
	public function getOrder() : int
	{
		return 10;
	}

	/**
	 * @return string css class that displays an icon next to the widget title
	 */
	public function getIconClass() : string
	{
		return 'icon-music-app';
	}

	/**
	 * @return string|null The absolute url to the apps own view
	 */
	public function getUrl() : ?string
	{
		return $this->urlGenerator->linkToRouteAbsolute('music.page.index');
	}

	/**
	 * Execute widget bootstrap code like loading scripts and providing initial state
	 */
	public function load() : void
	{
		\OCA\Music\Utility\HtmlUtil::addWebpackScript('dashboard_music_widget');
		\OCA\Music\Utility\HtmlUtil::addWebpackStyle('dashboard_music_widget');
	}
}
