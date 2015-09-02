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
	 * @class OCA.Music.AlbumView
	 */
	var AlbumView = OC.Backbone.View.extend({
		initialize: function(options) {
			this.collection = options.collection;
			this.collection.on('change sync', this.render, this);
		},
		template: function(data) {
			if (!this._template) {
				this._template = Handlebars.compile($('#album-template').html());
			}
			return this._template(data);
		},
		render: function() {
			this.$el.html(this.template(this.collection.toJSON()));
		}
	});

	OCA.Music = OCA.Music || {};
	OCA.Music.AlbumView = AlbumView;
})();

