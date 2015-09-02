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
	 * @class OCA.Music.ArtistCollection
	 */
	var ArtistCollection = OC.Backbone.Collection.extend({
		model: OCA.Music.Artist,
		url: OC.generateUrl('apps/music/api/artists')
	});

	OCA.Music.ArtistCollection = ArtistCollection;
})();

