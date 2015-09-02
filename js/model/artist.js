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
	/**
	 * @class OCA.Music.Artist
	 *
	 */
	var Artist = OC.Backbone.Model.extend({
		urlRoot: OC.generateUrl('apps/music/api/artist')
	});

	OCA.Music = OCA.Music || {};
	OCA.Music.Artist = Artist;
})();

