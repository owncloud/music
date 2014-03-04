angular.module('Music').controller('ArtistController', ['$scope', '$routeParams', 'Artist', function($scope, $routeParams, Artist) {
  Artist.get($routeParams.id).then(function(artist){
    $scope.artist = artist;
  });
}]);