#!/usr/bin/env bash
# this downloads test data from github that is then moved to the data folder
# and then can be scanned by the ownCloud filescanner

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <path to ownCloud user dir>"
    exit 1
fi

url="https://github.com/paulijar/music/files/2364060/testcontent.zip"

if [ ! -d /tmp/downloadedData ];
then
    mkdir -p /tmp/downloadedData
fi

cd /tmp/downloadedData

name=`echo $url | cut -d "/" -f 8`
if [ ! -f "$name" ];
then
    echo "Downloading $name ..."
    wget $url -q --no-check-certificate -O $name
    if [ $? -ne 0 ];
    then
        sleep 5
        wget $url --no-check-certificate -O $name
        if [ $? -ne 0 ];
        then
            sleep 5
            wget $url --no-check-certificate -O $name
            if [ $? -ne 0 ];
            then
                exit 1
            fi
        fi
    fi
else
    echo "$name is already available"
fi

# extract
unzip -o $name -d .

# go back to the old folder
cd -

mkdir -p $1/files/music
cp -r /tmp/downloadedData/* $1/files/music
