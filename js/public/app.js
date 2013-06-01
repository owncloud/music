// compiled with grunt

(function(angular, $, undefined){

'use strict';

angular.module('Test', ['OC']).
	config(
		['$routeProvider', '$interpolateProvider',
		function ($routeProvider, $interpolateProvider) {

	$routeProvider.when('/', {
		templateUrl: 'main.html',
		controller: 'MainController'
	}).when('/:id', {
		templateUrl: 'main.html',
		controller: 'MainController'
	}).otherwise({
		redirectTo: '/'
	});

	// because twig already uses {{}}
	$interpolateProvider.startSymbol('[[');
	$interpolateProvider.endSymbol(']]');
}]);
angular.module('Test').controller('MainController',
	['$scope', '$routeParams', function ($scope, $routeParams) {

	$scope.id = $routeParams.id;

}]);
})(angular, jQuery);