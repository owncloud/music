/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2020
 */

module.exports = function(grunt) {

	// load needed modules
	grunt.loadNpmTasks('grunt-angular-gettext');


	grunt.initConfig({

		nggettext_extract: {
			pot: {
				files: {
					'../l10n/templates/music.pot': ['../templates/**/*.php', '../js/app/**/*.js']
				}
			},
		},

		nggettext_compile: {
			all: {
				options: {
					module: 'Music'
				},
				files: {
					'../js/app/l10n/translations.js': ['../l10n/**/music.po']
				}
			},
		}

	});

};
