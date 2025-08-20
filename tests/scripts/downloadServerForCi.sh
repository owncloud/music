#!/usr/bin/env bash
#
# ownCloud Music
#
# @author Pauli Järvinen
# @copyright 2025 Pauli Järvinen <pauli.jarvinen@gmail.com>
#

if [ "$#" -ne 2 ]; then
    echo "Usage: $0 <owncloud|nextcloud> <cloud_version>"
    exit 1
fi

CLOUD=$1
VERSION=$2

mkdir -p /tmp/oc_music_ci
cd /tmp/oc_music_ci

# download the cloud and setup folders
if [ $CLOUD == 'owncloud' ]; then
    URL=https://download.owncloud.com/server/stable
elif [[ $VERSION == *"beta"* ]]; then
    URL=https://download.nextcloud.com/server/prereleases
else
    URL=https://download.nextcloud.com/server/releases
fi

wget $URL/$CLOUD-$VERSION.zip
unzip $CLOUD-$VERSION.zip
mv $CLOUD server
