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


$(document).ready(function(){
	var musicSettings = {
		save: function() {
			var data = {
					ampacheEnabled: $('#music-enable-ampache').attr('checked') === "checked"
				},
				// dirty route creation, but there is no JS function that provide this URL format
				route = OC.webroot + '/index.php/apps/music/api/admin/settings';
			$.post(route, data, musicSettings.afterSave);
		},
		afterSave: function(result) {
			if(result.success !== true) {
				// revert changes on failure
				if($('#music-enable-ampache').attr('checked') === 'checked') {
					$('#music-enable-ampache').removeAttr('checked');
				} else {
					$('#music-enable-ampache').attr('checked', 'checked');
				}
			}
		}
	}
	$('#music-enable-ampache').change(musicSettings.save);
});