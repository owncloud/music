$(document).ready(function() {

	if($('#body-login').exists() || !$('#filestable').exists() || $('#isPublic').val()) {
			return true; //deactivate on login page and public-share pages - and #filestable have to be available
	}

	var openInMusic = function (filename) {
		var id = $('#fileList').find('[data-file="'+filename+'"]').data('id');
		// TODO needs to be fixed once a proper solution for https://github.com/owncloud/core/issues/7307 is found
		// https://github.com/owncloud/core/pull/7494
		window.location = OC.webroot + '/index.php/apps/music/#/file/' + id;
	};

	if(typeof FileActions !== 'undefined') {
		FileActions.register('audio', 'Play', OC.PERMISSION_READ, '', openInMusic);
		FileActions.register('application/ogg', 'Play', OC.PERMISSION_READ, '', openInMusic);
		FileActions.setDefault('audio', 'Play');
		FileActions.setDefault('application/ogg', 'Play');
	}
});
