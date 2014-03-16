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
$path.on('click', function() {
	$path.prop('disabled', true);
	OC.dialogs.filepicker(
		t('music', 'Path to your music collection'),
		function (path) {
			if ($path.val() == path) $path.prop('disabled', false);
			else {
				$path.val(path);
				$.post(OC.Router.generate('music_settings_user_path'), { value: path }, function(data) {
					if (!data.success) $path[0].setCustomValidity(t('music', 'Invalid path'));
					else $path[0].setCustomValidity('');
					$path.prop('disabled', false);
				});
			}
		},
		false,
		'httpd/unix-directory',
		true
	);
});

});
