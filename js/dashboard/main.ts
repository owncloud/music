/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('music', (el : HTMLElement) => {
		loadArtists(el);
	});
});

function loadArtists(container : HTMLElement) {
	let url = OC.generateUrl('apps/music/ampache/server/json.server.php');
	$.get(url, {action: 'list', type: 'artist', auth: 'internal'}, (result : any) => {
		let $select = $('<select>').appendTo($(container));
		//let $options = $.map(result.list, (artist : any) => $('<option/>', {text: artist.name}));
		//$select.html($options);

		$(result.list).each(function() {
			$select.append($("<option/>").attr('value', this.id).text(this.name));
		});
	}).fail((error) => {
		console.error(error)
	});
}