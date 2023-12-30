/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2023
 */

OCA.Music = OCA.Music || {};

OCA.Music.initPlaylistTabView = function(playlistMimes) {

	class PlaylistTabView {
		id = 'musicPlaylistTabView';
		name = t('music', 'Playlist');
		icon = 'icon-music';
		$el = null;
		fileInfo = null;

		enabled(fileInfo) {
			if (!fileInfo || fileInfo.isDirectory()) {
				return false;
			}
			const mimetype = fileInfo.get('mimetype');
			return (mimetype && playlistMimes.indexOf(mimetype) > -1);
		}

		populate(fileInfo) {
			this.$el.empty(); // erase any previous content
			this.fileInfo = fileInfo;

			if (fileInfo) {
	
				let loadIndicator = $(document.createElement('div')).attr('class', 'loading');
				this.$el.append(loadIndicator);
	
				let onPlaylistLoaded = (data) => {
					loadIndicator.hide();
	
					let list = $(document.createElement('ol'));
					this.$el.append(list);
	
					let titleForFile = function(file) {
						return file.caption || OCA.Music.Utils.titleFromFilename(file.name);
					};
	
					let tooltipForFile = function(file) {
						return file.path ? `${file.path}/${file.name}` : file.url;
					};
	
					for (let i = 0; i < data.files.length; ++i) {
						list.append($(document.createElement('li'))
									.attr('id', 'music-playlist-item-' + i)
									.text(titleForFile(data.files[i]))
									.prop('title', tooltipForFile(data.files[i])));
					}
	
					// click handler
					list.on('click', 'li', (event) => {
						let id = event.target.id;
						let idx = parseInt(id.split('-').pop());
						this.trigger('playlistItemClick', fileInfo.id, fileInfo.attributes?.name ?? fileInfo.name, idx);
					});
	
					if (data.invalid_paths.length > 0) {
						this.$el.append($(document.createElement('p')).text(t('music', 'Some files on the playlist were not found') + ':'));
						let failList = $(document.createElement('ul'));
						this.$el.append(failList);
	
						for (let i = 0; i < data.invalid_paths.length; ++i) {
							failList.append($(document.createElement('li')).text(data.invalid_paths[i]));
						}
					}
	
					this.trigger('rendered');
				};
	
				let onError = function(_error) {
					loadIndicator.hide();
					this.$el.append($(document.createElement('p')).text(t('music', 'Error reading playlist file')));
				};
	
				OCA.Music.PlaylistFileService.readFile(fileInfo.id, onPlaylistLoaded, onError);
			}
		}

		setCurrentTrack(playlistId, trackIndex) {
			this.$el.find('ol li.current').removeClass('current');
			if (this.fileInfo && this.fileInfo.id == playlistId) {
				this.$el.find('ol li#music-playlist-item-' + trackIndex).addClass('current');
			}
		}
	}
	_.extend(PlaylistTabView.prototype, OC.Backbone.Events);

	// Registration before NC28
	if (OCA.Files?.DetailTabView) {
		OCA.Music.playlistTabView = new PlaylistTabView();

		const WrappedPlaylistTabView = OCA.Files.DetailTabView.extend({
			id: OCA.Music.playlistTabView.id,
			className: 'tab musicPlaylistTabView',
			getLabel: () => OCA.Music.playlistTabView.name,
			getIcon: () => OCA.Music.playlistTabView.icon,
			canDisplay: OCA.Music.playlistTabView.enabled,
			render: function() {
				OCA.Music.playlistTabView.$el = this.$el;
				OCA.Music.playlistTabView.populate(this.getFileInfo());
			}
		});
		OC.Plugins.register('OCA.Files.FileList', {
			attach: function(fileList) {
				fileList.registerTabView(new WrappedPlaylistTabView());
			}
		});
	}

	// Registration after NC28
	else if (OCA.Files?.Sidebar) {
		OCA.Music.playlistTabView = new PlaylistTabView();

		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
			id: OCA.Music.playlistTabView.id,
			name: OCA.Music.playlistTabView.name,
			icon: OCA.Music.playlistTabView.icon,

			async mount(el, fileInfo, _context) {
				OCA.Music.playlistTabView.$el = $(el);
				OCA.Music.playlistTabView.$el.addClass('musicPlaylistTabView');
				OCA.Music.playlistTabView.populate(fileInfo);
			},
			update(fileInfo) {
				OCA.Music.playlistTabView.populate(fileInfo);
			},
			destroy() {
			},
			enabled(fileInfo) {
				return OCA.Music.playlistTabView.enabled(fileInfo);
			},
		}));
	}
};
