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

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRssUrl()
 * @method setRssUrl(string $url)
 * @method string getRssHash()
 * @method setRssHash(string $hash)
 * @method string getContentHash()
 * @method setContentHash(string $hash)
 * @method string getUpdateChecked()
 * @method setUpdateChecked(string $timestamp)
 * @method string getSourceUpdated()
 * @method setSourceUpdated(string $timestamp)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getLinkUrl()
 * @method setLinkUrl(string $url)
 * @method string getLanguage()
 * @method void setLanguage(string $language)
 * @method string getCopyright()
 * @method setCopyright(string $copyright)
 * @method string getAuthor()
 * @method setAuthor(string $author)
 * @method string getDescription()
 * @method setDescription(string $description)
 * @method string getImageUrl()
 * @method setImageUrl(string $url)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 */
class PodcastChannel extends Entity {
	public $userId;
	public $rssUrl;
	public $rssHash;
	public $contentHash;
	public $updateChecked;
	public $sourceUpdated;
	public $title;
	public $linkUrl;
	public $language;
	public $copyright;
	public $author;
	public $description;
	public $imageUrl;
	public $category;
	public $created;
	public $updated;

	public function toApi() : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle()
		];
	}
}
