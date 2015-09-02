/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {

	OCA.Music = OCA.Music || {};

	/**
	 * @class OCA.Music.AlbumCollection
	 */
	var AlbumCollection = OC.Backbone.Collection.extend({
		model: OCA.Music.Album,
		url: OC.generateUrl('apps/music/api/albums')
	});

	OCA.Music.AlbumCollection = AlbumCollection;
})();

