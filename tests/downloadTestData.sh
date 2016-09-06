#!/usr/bin/env bash
# this downloads test data from jamendo that is then moved to the data folder
# and then can be scanned by the ownCloud filescanner

urls="https://mp3d.jamendo.com/download/track/391005/mp32/
https://mp3d.jamendo.com/download/track/391013/mp32/
https://mp3d.jamendo.com/download/track/391014/mp32/
https://mp3d.jamendo.com/download/track/391010/mp32/
https://mp3d.jamendo.com/download/track/391002/mp32/
https://mp3d.jamendo.com/download/track/23975/mp32/
https://mp3d.jamendo.com/download/track/23969/mp32/
https://mp3d.jamendo.com/download/track/23976/mp32/
https://mp3d.jamendo.com/download/track/1048293/mp32/
https://mp3d.jamendo.com/download/track/1048300/mp32/
https://mp3d.jamendo.com/download/track/1050078/mp32/
https://mp3d.jamendo.com/download/track/1050077/mp32/
https://mp3d.jamendo.com/download/track/1048292/mp32/"

if [ ! -d /tmp/downloadedData ];
then
    mkdir -p /tmp/downloadedData
fi

cd /tmp/downloadedData

for url in $urls
do
    name=`echo $url | cut -d "/" -f 6`
    if [ ! -d "$name" ];
    then
        echo "Downloading $name ..."
        wget $url -q --no-check-certificate -O $name.mp3
        if [ $? -ne 0 ];
        then
            sleep 5
            wget $url --no-check-certificate -O $name.mp3
            if [ $? -ne 0 ];
            then
                sleep 5
                wget $url --no-check-certificate -O $name.mp3
                if [ $? -ne 0 ];
                then
                    exit 1
                fi
            fi
        fi
    else
        echo "$name is already available"
    fi
done

# go back to the old folder
cd -

mkdir -p $1/files/
cp -r /tmp/downloadedData $1/files/music
