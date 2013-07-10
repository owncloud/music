
/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


module.exports = function(grunt) {

	// load needed modules
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-wrap');
	grunt.loadNpmTasks('grunt-karma');
	grunt.loadNpmTasks('grunt-phpunit');


	grunt.initConfig({

		meta: {
			pkg: grunt.file.readJSON('package.json'),
			version: '<%= meta.pkg.version %>',
			production: 'public/'
		},

		concat: {
			options: {
				// remove license headers
				stripBanners: true
			},
			dist: {
				src: [
					'config/app.js',
					'app/controllers/**/*.js',
					'app/directives/**/*.js',
					'app/filters/**/*.js',
					'app/services/**/*.js'
				],
				dest: '<%= meta.production %>app.js'
			}
		},

		wrap: {
			app: {
				src: ['<%= meta.production %>app.js'],
				dest: '',
				wrapper: [
					'(function(angular, $, undefined){\n\n\'use strict\';\n\n',
					'\n})(angular, jQuery);'
				]
			}
		},

		jshint: {
			files: [
				'Gruntfile.js',
				'app/controllers/**/*.js',
				'app/directives/**/*.js',
				'app/filters/**/*.js',
				'app/services/**/*.js',
				'app/tests/**/*.js',
				'config/*.js'],
			options: {
				// options here to override JSHint defaults
				globals: {
					console: true
				}
			}
		},

		watch: {
			// this watches for changes in the app directory and runs the concat
			// and wrap tasks if something changed
			concat: {
				files: [
					'app/controllers/**/*.js',
					'app/directives/**/*.js',
					'app/filters/**/*.js',
					'app/services/**/*.js',
					'config/*.js'
				],
				tasks: ['build']
			}
		},

		karma: {
			unit: {
				configFile: 'config/karma.js'
			},
			continuous: {
				configFile: 'config/karma.js',
				singleRun: true,
				browsers: ['PhantomJS'],
				reporters: ['progress', 'junit'],
				junitReporter: {
					outputFile: 'test-results.xml'
				}
			}
		}

	});

	// make tasks available under simpler commands
	grunt.registerTask('build', ['jshint', 'concat', 'wrap']);
	grunt.registerTask('watchjs', ['watch:concat']);
	grunt.registerTask('ci', ['karma:continuous']);
	grunt.registerTask('testjs', ['karma:unit']);

};