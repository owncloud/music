/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

$(document).ready(function() {

	/*
	 * Collection path
	 */
	var $path = $('#music-path');
	$path.on('click focus', function() {
		OC.dialogs.filepicker(
			t('music', 'Path to your music collection'),
			function (path) {
				if ($path.val() !== path) {
					$path.val(path);
					$.post(OC.generateUrl('apps/music/settings/user/path'), { value: path }, function(data) {
						if (!data.success) {
							$path[0].setCustomValidity(t('music', 'Invalid path'));
						} else {
							$path[0].setCustomValidity('');
						}
					});
				}
			},
			false,
			'httpd/unix-directory',
			true
		);
	});

	/**
	 * Add API key
	 */
	var addAPIKey = function(){
		var password = Math.random().toString(36).slice(-6) + Math.random().toString(36).slice(-6),
			description = $('#music-ampache-description').val(),
			templateRow = $('#music-ampache-template-row').clone(true); // clone with events

		$('#music-ampache-description').val('');
		templateRow.removeClass('hidden');
		templateRow.find('td:first').text(description);
		templateRow.appendTo('#music-ampache-keys');

		if($('#music-ampache-keys').hasClass('hidden')) {
			$('#music-ampache-keys').removeClass('hidden');
		}

		$.post(OC.generateUrl('apps/music/settings/userkey/add'), { password: password, description: description }, function(data) {
			if (data.success) {
				templateRow.find('a').data('id', data.id);
				templateRow.find('a').removeClass('icon-loading-small').addClass('icon-delete');
				$('#music-password-info').removeClass('hidden').find('span').text(password);
			} else {
				templateRow.remove();
				if($('#music-ampache-keys tr').length === 2) {
					$('#music-ampache-keys').addClass('hidden');
				}
			}
		});
	};

	$('#music-ampache-form').find('button').click(addAPIKey);
	$('#music-ampache-form').find('input').keypress(function(event){
		if(event.which === 13) {
			event.preventDefault();
			addAPIKey();
		}
	});

	var removeAPIKey = function(event) {
		event.preventDefault();
		var me = $(this),
			id = me.data('id');
		if(id === '' || me.hasClass('icon-loading')) {
			return;
		}
		me.removeClass('icon-delete').addClass('icon-loading-small');

		$.post(OC.generateUrl('apps/music/settings/userkey/remove'), { id: me.data('id') }, function(data) {
			if (data.success) {
				me.closest('tr').remove();
				if($('#music-ampache-keys tr').length === 2) {
					$('#music-ampache-keys').addClass('hidden');
				}
			} else {
				me.removeClass('icon-loading-small').addClass('icon-delete');
			}
		});
	};

	$('#music-ampache-keys').find('a').click(removeAPIKey);

});
