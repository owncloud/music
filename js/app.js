

$(document).ready(function(){
	var collection = new OCA.Music.ArtistCollection(),
		view = new OCA.Music.ArtistView({
			collection: collection
		});

	$('#app').append(view.$el);

	collection.fetch();

});
