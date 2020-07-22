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
				this.$el.empty(); // erase any previous content

				var fileInfo = this.getFileInfo();

				if (fileInfo) {
					var container = this.$el;

					var loadIndicator = $(document.createElement('div')).attr('class', 'loading');
					container.append(loadIndicator);

					var url = OC.generateUrl('apps/music/api/playlists/file/{fileId}', {'fileId': fileInfo.id});
					$.get(url, function(data) {
						loadIndicator.hide();

						var list = $(document.createElement('ol'));
						container.append(list);

						for (var i = 0; i < data.files.length; ++i) {
							list.append($(document.createElement('li')).text(data.files[i].name));
						}

						if (data.invalid_paths.length > 0) {
							container.append($(document.createElement('p')).text(t('music', 'Some files on the playlist were not found') + ':'));
							var failList = $(document.createElement('ul'));
							container.append(failList);

							for (i = 0; i < data.invalid_paths.length; ++i) {
								failList.append($(document.createElement('li')).text(data.invalid_paths[i]));
							}
						}

					}).fail(function() {
						loadIndicator.hide();
						container.append($(document.createElement('p')).text(t('music', 'Error reading playlist file')));
					});
				}
			},

			canDisplay: function(fileInfo) {
				if (!fileInfo || fileInfo.isDirectory()) {
					return false;
				}
				var mimetype = fileInfo.get('mimetype') || '';

				return (['audio/mpegurl'].indexOf(mimetype) > -1);
			}
		});
		OCA.Music.PlaylistTabView.id = 'musicPlaylistTabView';

		OC.Plugins.register('OCA.Files.FileList', {
			attach: function(fileList) {
				fileList.registerTabView(new OCA.Music.PlaylistTabView());
			}
		});
	}
}
