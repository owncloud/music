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

# building the javascript
build: deps
	npm run build

build-dev: deps
	npm run build-dev

watch: deps
	npm run watch

# general
deps:
	npm install --deps

# localization
l10n-clone:
	python ./duplicate-lang.py --path=../l10n bg_BG:bg cs_CZ:cs de:de_AT de:de_CH de:de_DE fi_FI:fi hu_HU:hu nb_NO:nb sk_SK:sk th_TH:th

l10n-extract:
	npm run l10n-extract

l10n-compile:
	npm run l10n-compile
	cd ../l10n && perl l10n.pl music write

# releasing
release:
	./release.sh
