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
	 * @class OCA.Music.ArtistView
	 */
	var ArtistView = OC.Backbone.View.extend({
		initialize: function(options) {
			this.collection = options.collection;
			this.collection.on('add', this.onAddArtist, this);
		},
		template: function(data) {
			if (!this._template) {
				this._template = Handlebars.compile($('#artist-template').html());
			}
			return this._template(data);
		},
		render: function(artist) {
			var el = this.template(artist.toJSON());
			this.$el.append(el);
			return el;
		},
		onAddArtist: function(artist) {
			var el = this.render(artist),
				collection = new OCA.Music.AlbumCollection(),
				view = new OCA.Music.AlbumView({
					collection: collection
				});

			$(el).find('.album-area').append(view.$el);
		}
	});
	OCA.Music = OCA.Music || {};
	OCA.Music.ArtistView = ArtistView;
})();

