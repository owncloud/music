#!/usr/bin/env bash
#
# ownCloud Music
#
# @author Pauli Järvinen
# @copyright 2025 Pauli Järvinen <pauli.jarvinen@gmail.com>
#

# Prerequisite: The server to use is downloaded and extracted to /tmp/oc_music_ci/server

if [ "$#" -ne 2 ]; then
    echo "Usage: $0 <owncloud|nextcloud> <sqlite|mysql>"
    exit 1
fi

CLOUD=$1
DB=$2
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
REPO_DIR=$SCRIPT_DIR/../..

# copy the repository under server/apps
rm -rf /tmp/oc_music_ci/server/apps/music
cp -r $REPO_DIR /tmp/oc_music_ci/server/apps/music

# prepare the DB if necessary
if [[ $DB == 'mysql' ]]; then
    sudo /etc/init.d/mysql start
    mysql -uroot -proot -e 'CREATE DATABASE owncloud;'
    mysql -uroot -proot -e "CREATE USER 'oc_autotest'@'localhost' IDENTIFIED BY '';"
    mysql -uroot -proot -e "GRANT ALL ON owncloud.* TO 'oc_autotest'@'localhost';"
fi

# install the cloud
cd /tmp/oc_music_ci
rm -rf data
mkdir data
cd server
touch config/CAN_INSTALL
php occ maintenance:install --database-name owncloud --database-user oc_autotest --admin-user admin --admin-pass 0aVnqOWH1rurCrNdTJTM --database $DB --database-pass='' --data-dir=/tmp/oc_music_ci/data
OC_PASS=ampache123456 php occ user:add ampache --password-from-env

# set log level as 'info'
php occ config:system:set loglevel --type=integer --value=1

# remove the ownCloud-specific files of Music on Nextcloud
if [ $CLOUD == 'nextcloud' ]; then
    rm apps/music/appinfo/database.xml
    rm apps/music/appinfo/app.php
fi

# activate the Music app
php occ app:enable music

# download and scan the test content
./apps/music/tests/scripts/downloadTestData.sh /tmp/oc_music_ci/data/ampache
php occ files:scan ampache
php occ music:scan ampache

# setup the API key
SQL_QUERY='INSERT INTO oc_music_ampache_users (user_id, hash) VALUES ("ampache", "3e60b24e84cfa047e41b6867efc3239149c54696844fd3a77731d6d8bb105f18");'
if [ $DB == 'sqlite' ]; then
    sqlite3 /tmp/oc_music_ci/data/owncloud.db "$SQL_QUERY"
elif [ $DB == 'mysql' ]; then
    mysql -u oc_autotest -e "$SQL_QUERY" owncloud
else
    echo "Unsupported DB type $DB"
    exit 2
fi
