$(document).ready(function() {

	if($('#body-login').exists() || !$('#filestable').exists() || $('#isPublic').val()) {
			return true; //deactivate on login page and public-share pages - and #filestable have to be available
	}

	var openInMusic = function (filename) {
		var id = $('#fileList').find('[data-file="'+filename+'"]').data('id');
		window.location = OC.generateUrl('apps/music/#/file/{id}', {id: id});
	};

	if(typeof FileActions !== 'undefined') {
		FileActions.register('audio', 'Play', OC.PERMISSION_READ, '', openInMusic);
		FileActions.register('application/ogg', 'Play', OC.PERMISSION_READ, '', openInMusic);
		FileActions.setDefault('audio', 'Play');
		FileActions.setDefault('application/ogg', 'Play');
	}
});
