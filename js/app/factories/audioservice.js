// based on a demo from https://github.com/zohararad/audio5js
angular.module('Music').factory('AudioService', function () {
	"use strict";

	var params = {
		swf_path:OC.linkTo('music', '3rdparty/audio5/swf/audio5js.swf'),
		format_time:false
	};

	var audio5js = new Audio5js(params);

	return audio5js;
});
