#!/bin/bash
#
# ownCloud Music
#
# @author Pauli Järvinen
# @copyright 2021 - 2024 Pauli Järvinen <pauli.jarvinen@gmail.com>
#

# Create the base package from the files stored in git
cd ..
git archive HEAD --format=zip --prefix=music/ > music.zip

# Add the generated webpack files to the previously created package
cd ..
zip -g music/music.zip music/dist/*.js
zip -g music/music.zip music/dist/*.css
zip -g music/music.zip music/dist/*.json
zip -g music/music.zip music/dist/img/**

# Remove the front-end source files from the package as those are not needed to run the app
zip -d music/music.zip "music/css/*.css"
zip -d music/music.zip "music/css/*/"
zip -d music/music.zip "music/img/*.svg"
zip -d music/music.zip "music/img/*/*"
zip -d music/music.zip "music/js/*.js*"
zip -d music/music.zip "music/js/*/*"
zip -d music/music.zip "music/l10n/*/*"

# Add the application icon back to the zip as that is still needed by the cloud core
zip -g music/music.zip music/img/music.svg

# Remove also files related to building, testing, and code analysis
zip -d music/music.zip "music/build/*"
zip -d music/music.zip "music/stubs/*"
zip -d music/music.zip "music/tests/*"
zip -d music/music.zip "music/composer.*"
zip -d music/music.zip "music/package*.json"
zip -d music/music.zip "music/phpstan*.*"
zip -d music/music.zip "music/webpack.config.js"

# Fork the package to own versions for Nextcloud and ownCloud.
# Different mechanism is used on each cloud to define the database schema and for bootstrapping.
cp music/music.zip music/music-nc.zip
mv music/music.zip music/music-oc.zip
zip -d music/music-nc.zip "music/appinfo/app.php"
zip -d music/music-nc.zip "music/appinfo/database.xml"
zip -d music/music-oc.zip "music/lib/Migration/Version*Date*.php"
