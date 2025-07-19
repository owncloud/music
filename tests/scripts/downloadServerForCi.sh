#!/usr/bin/env bash
#
# ownCloud Music
#
# @author Pauli Järvinen
# @copyright 2025 Pauli Järvinen <pauli.jarvinen@gmail.com>
#

CLOUD=$1
VERSION=$2

mkdir -p /tmp/oc_music_ci
cd /tmp/oc_music_ci

# download the cloud and setup folders
wget https://download.nextcloud.com/server/releases/$CLOUD-$VERSION.zip
unzip $CLOUD-$VERSION.zip
mv $CLOUD server
