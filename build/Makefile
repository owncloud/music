#
# ownCloud scaffolder tool
#
# Copyright (C) 2013 Bernhard Posselt, <nukewhale@gmail.com>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.


# Makefile for building the project
app_name=music
project_dir=$(CURDIR)/../../$(app_name)
build_dir=$(CURDIR)/artifacts
appstore_dir=$(build_dir)/appstore
package_name=$(app_name)

# common directories
grunt_dir=$(CURDIR)/node_modules/grunt-cli/bin/grunt
gruntfile_dir=$(CURDIR)/Gruntfile.js

js_dir=$(CURDIR)/../js
js_public_dir=$(js_dir)/public

php_unit_tests_dir=$(CURDIR)/../tests/php/unit
php_integration_tests_dir=$(CURDIR)/../tests/php/integration
php_acceptance_tests_dir=$(CURDIR)/../tests/php/acceptance



# building the javascript
all: build

build: deps
	mkdir -p $(js_public_dir)
	$(grunt_dir) --config $(gruntfile_dir) build

watch: build
	$(grunt_dir) --config $(gruntfile_dir) watch:concat

# testing
tests: php-unit-tests php-integration-tests php-acceptance-tests

unit-tests: php-unit-tests

# testing php
php-unit-tests: deps
	phpunit $(php_unit_tests_dir)

php-unit-tests-coverage: deps
	phpunit --coverage-html coverage-html $(php_unit_tests_dir)

watch-php-unit-tests: deps
	$(grunt_dir) --config $(gruntfile_dir) watch:phpunit

php-integration-tests: deps
	phpunit $(php_integration_tests_dir)

php-acceptance-tests: deps
	cd $(php_acceptance_tests_dir); make headless


# general
deps:
	npm install --deps

clean:
	rm -rf $(CURDIR)/node_modules
	rm -rf $(build_dir)

dist: appstore

appstore: clean
	mkdir -p $(appstore_dir)
	tar cvzf $(appstore_dir)/$(package_name).tar.gz $(project_dir) \
	--exclude-vcs \
	--exclude=$(project_dir)/build/artifacts \
	--exclude=$(project_dir)/build/node_modules #\
	# --exclude=$(project_dir)/tests \
	#--exclude=$(project_dir)/.travis.yml

l10n-extract: deps
	$(grunt_dir) --config $(gruntfile_dir) nggettext_extract

l10n-compile: deps
	$(grunt_dir) --config $(gruntfile_dir) nggettext_compile
	cd ../l10n; ./l10n.pl music write
