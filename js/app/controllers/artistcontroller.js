angular.module('Music').controller('ArtistController', ['$scope', '$routeParams', 'Artists', function($scope, $routeParams, Artists) {
  Artists.then(function(artists){
    for( var i = 0; i < artists.length; i++ ) {
      if ( artists[i].id == $routeParams.id ) {
        $scope.artist = artists[i];
        break;
      }
    }
  });
}]);