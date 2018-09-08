/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2018
 */

module.exports = function(grunt) {

	// load needed modules
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-wrap');
	grunt.loadNpmTasks('grunt-angular-gettext');


	grunt.initConfig({

		meta: {
			pkg: grunt.file.readJSON('package.json'),
			version: '<%= meta.pkg.version %>',
			productionJs: '../js/public/',
			productionCss: '../css/public/'
		},

		concat: {
			options: {
				// remove license headers
				stripBanners: true
			},
			appJs: {
				src: [
					'../js/config/app.js',
					'../js/app/**/*.js',
					'../js/l10n/*.js',
					'../js/shared/*.js'
				],
				dest: '<%= meta.productionJs %>app.js'
			},
			embeddedJs: {
				src: [
					'../js/embedded/*.js',
					'../js/shared/*.js'
				],
				dest: '<%= meta.productionJs %>files-music-player.js'
			},
			style: {
				src: [
					'../css/*.css'
				],
				dest: '<%= meta.productionCss %>app.css'
			}
		},

		wrap: {
			app: {
				src: ['<%= meta.productionJs %>app.js'],
				dest: '<%= meta.productionJs %>app.js',
				wrapper: [
					'(function(angular, $, oc_requesttoken, undefined){\n\n\'use strict\';\n\n',
					'\n})(angular, jQuery, oc_requesttoken);'
				]
			}
		},

		jshint: {
			files: [
				'Gruntfile.js',
				'../js/app/**/*.js',
				'../js/config/*.js',
				'../js/embedded/*.js',
				'../js/l10n/*.js',
				'../js/shared/*.js',
				'../tests/js/unit/**/*.js'
			],
			options: {
				laxbreak: true // switch off jshint's stupid default rule for location of linebreaks
			}
		},

		watch: {
			// this watches for changes in the app directory and runs the concat
			// and wrap tasks if something changed
			concat: {
				files: [
					'../js/shared/*.js',
					'../js/app/**/*.js',
					'../js/config/*.js',
					'../js/embedded/*.js',
					'../js/l10n/*.js',
					'../css/*.css'
				],
				tasks: ['build']
			},
		},

		nggettext_extract: {
			pot: {
				files: {
					'../l10n/templates/music.pot': ['../templates/**/*.php', '../js/public/*.js']
				}
			},
		},

		nggettext_compile: {
			all: {
				options: {
					module: 'Music'
				},
				files: {
					'../js/l10n/translations.js': ['../l10n/**/music.po']
				}
			},
		}

	});

	// make tasks available under simpler commands
	grunt.registerTask('build', ['jshint', 'concat', 'wrap']);

};
