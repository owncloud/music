#!/usr/bin/env bash
# this downloads test data from jamendo that is then moved to the data folder
# and then caan be scanned by the ownCloud filescanner

urls="https://storage-new.newjamendo.com/download/a141233/mp32/
https://storage-new.newjamendo.com/download/a131278/mp32/
https://storage-new.newjamendo.com/download/a130324/mp32/
https://storage-new.newjamendo.com/download/a129252/mp32/
https://storage-new.newjamendo.com/download/a126850/mp32/
https://storage-new.newjamendo.com/download/a123905/mp32/
https://storage-new.newjamendo.com/download/a123663/mp32/
https://storage-new.newjamendo.com/download/a123543/mp32/
https://storage-new.newjamendo.com/download/a123495/mp32/
https://storage-new.newjamendo.com/download/a49216/mp32/
https://storage-new.newjamendo.com/download/a19098/mp32/
https://storage-new.newjamendo.com/download/a3311/mp32/"

if [ ! -d downloadedData ];
then
    mkdir downloadedData
    cd downloadedData

    for url in $urls
    do
        name=`echo $url | cut -d "/" -f 5`
        wget $url -O archive.zip
        unzip archive.zip -d $name
        rm archive.zip
    done

    cd ..
fi

mkdir -p $1/$2/files/
cp -r downloadedData $1/$2/files/music
