// copy of core/js/js.js:205
OC.generateUrl = function(url, params) {
	var _build = function (text, vars) {
		return text.replace(/{([^{}]*)}/g,
			function (a, b) {
				var r = vars[b];
				return typeof r === 'string' || typeof r === 'number' ? r : a;
			}
		);
	};
	if (url.charAt(0) !== '/') {
		url = '/' + url;

	}
	return OC.webroot + '/index.php' + _build(url, params);
};
