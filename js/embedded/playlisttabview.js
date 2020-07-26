OCA.Music = OCA.Music || {};

function initPlaylistTabView() {
	if (typeof OCA.Files.DetailTabView != 'undefined') {
		OCA.Music.PlaylistTabView = OCA.Files.DetailTabView.extend({
			id: 'musicPlaylistTabView',
			className: 'tab musicPlaylistTabView',

			getLabel: function() {
				return t('music', 'Playlist');
			},

			getIcon: function() {
				return 'icon-music';
			},

			render: function() {
				var self = this;

				var container = this.$el;
				container.empty(); // erase any previous content

				if (OCA.Music.Utils.darkThemeActive()) {
					container.addClass('dark-theme');
				}

				var fileInfo = this.getFileInfo();

				if (fileInfo) {

					var loadIndicator = $(document.createElement('div')).attr('class', 'loading');
					container.append(loadIndicator);

					var onPlaylistLoaded = function(data) {
						loadIndicator.hide();

						var list = $(document.createElement('ol'));
						container.append(list);

						for (var i = 0; i < data.files.length; ++i) {
							list.append($(document.createElement('li'))
										.attr('id', 'music-playlist-item-' + i)
										.text(OCA.Music.Utils.titleFromFilename(data.files[i].name)));
						}

						// click handler
						list.on('click', 'li', function(event) {
							var id = event.target.id;
							var idx = parseInt(id.split('-').pop());
							self.trigger('playlistItemClick', fileInfo.id, fileInfo.attributes.name, idx);
						});

						if (data.invalid_paths.length > 0) {
							container.append($(document.createElement('p')).text(t('music', 'Some files on the playlist were not found') + ':'));
							var failList = $(document.createElement('ul'));
							container.append(failList);

							for (i = 0; i < data.invalid_paths.length; ++i) {
								failList.append($(document.createElement('li')).text(data.invalid_paths[i]));
							}
						}

						self.trigger('rendered');
					};

					var onError = function(error) {
						loadIndicator.hide();
						container.append($(document.createElement('p')).text(t('music', 'Error reading playlist file')));
					};

					OCA.Music.playlistFileService.readFile(fileInfo.id, onPlaylistLoaded, onError);
				}
			},

			canDisplay: function(fileInfo) {
				if (!fileInfo || fileInfo.isDirectory()) {
					return false;
				}
				var mimetype = fileInfo.get('mimetype') || '';

				return (['audio/mpegurl'].indexOf(mimetype) > -1);
			},

			setCurrentTrack: function(playlistId, trackIndex) {
				this.$el.find('ol li.current').removeClass('current');
				var fileInfo = this.getFileInfo();
				if (fileInfo && fileInfo.id == playlistId) {
					this.$el.find('ol li#music-playlist-item-' + trackIndex).addClass('current');
				}
			}
		});
		_.extend(OCA.Music.PlaylistTabView.prototype, OC.Backbone.Events);
		OCA.Music.playlistTabView = new OCA.Music.PlaylistTabView();

		OC.Plugins.register('OCA.Files.FileList', {
			attach: function(fileList) {
				fileList.registerTabView(OCA.Music.playlistTabView);
			}
		});
	}
}
