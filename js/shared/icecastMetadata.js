/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moahmed-Ismail MEJRI <mmismailm@hotmail.com>
 * @copyright Moahmed-Ismail MEJRI 2022
 */

import IcecastReadableStream from './icecast-metadata-js/IcecastReadableStream.js';

OCA.Music = OCA.Music || {};

OCA.Music.ReadStreamMetaData = function(endpoint, onMetaDataCb, method) {
    var StreamSource = {
        SHOUTCAST_V1: 'SHOUTCAST_V1',
        SHOUTCAST_V2: 'SHOUTCAST_V2',
        STREAM: 'STREAM',
        ICECAST: 'ICECAST'
    };

    function getShoutcastV1Station(url, callback) {
        url = url + '/7.html';
        fetch(url, {
            method: 'GET'
        })
        .then(response => response.text())
        .then(body => {
            var csvArrayParsing = /<body>(.*)<\/body>/im.exec(body);

            if (!csvArrayParsing || typeof csvArrayParsing.length !== 'number') {
                return;
            }

            var csvArray = csvArrayParsing[1].split(',');
            var title = undefined;

            if (csvArray && csvArray.length == 7) {
                title = csvArray[6];
            } else {
                title = csvArray.slice(6).join(',');
            }

            if (title) {
                var station = {};
                station.listeners = csvArray[0];
                station.bitrate = csvArray[5];
                station.title = title;
                station.fetchsource = 'SHOUTCAST_V1';
                callback(station);
            }
        });
    }

    function getShoutcastV2Station(url, callback) {
        var parser = document.createElement('a');
        parser.href = url;
        var furl = parser.protocol + '://' + parser.hostname + ':' + parser.port + '/statistics';
        fetch(furl, {
            method: 'GET'
        })
        .then(response => response.json())
        .then(body => {
            var numberOfStreamsAvailable = body.SHOUTCASTSERVER.STREAMSTATS[0].STREAM.length;
            var stationStats = null;
            if (numberOfStreamsAvailable === 1) {
                stationStats = body.SHOUTCASTSERVER.STREAMSTATS[0].STREAM[0];
            } else {
                var streams = body.SHOUTCASTSERVER.STREAMSTATS[0].STREAM;
                for (var i = 0, mountCount = streams.length; i < mountCount; i++) {
                    var stream = streams[i];
                    var streamUrl = stream.SERVERURL[0];
                    if (streamUrl == url) {
                        stationStats = stream;
                    }
                }
            }
            if (stationStats != null && stationStats.SONGTITLE) {
                var station = {};
                station.listeners = stationStats.CURRENTLISTENERS[0];
                station.bitrate = stationStats.BITRATE[0];
                station.title = stationStats.SONGTITLE[0];
                station.fetchsource = 'SHOUTCAST_V2';
                callback(station);
            }
        });
    }

    function getIcecastStation(url, callback) {
        var parser = document.createElement('a');
        parser.href = url;
        var furl = parser.protocol + '://' + parser.hostname + ':' + parser.port + '/status-json.xsl';
        fetch(furl, {
            method: 'GET'
        })
        .then(response => response.json())
        .then(body => {
            if (
                !body.icestats ||
                !body.icestats.source ||
                body.icestats.source.length === 0
            ) {
                return;
            }
            var sources = body.icestats.source;
            for (var i = 0, mountCount = sources.length; i < mountCount; i++) {
                var source = sources[i];
                if (source.listenurl === url) {
                    var station = {};
                    station.listeners = source.listeners;
                    station.bitrate = source.bitrate;
                    station.title = source.title;
                    station.fetchsource = 'ICECAST';
                    callback(station);
                }
            }
        });
    }

    function getStreamStation(url, callback) {
        let controller = new AbortController();
        const options = {
            metadataTypes: ['icy'],
            icyCharacterEncoding: 'utf-8',
            icyMetaInt: 16000,
            icyDetectionTimeout: 2000,
            enableLogging: false,
            onStream: () => {},
            onMetadata: (value) => {var station = {};
                                   station.title = value.metadata.StreamTitle;
                                   station.fetchsource = 'STREAM';
                                   callback(station);
                                   controller.abort();},
            onError: () => {}
        };

        fetch(url, {
            method: 'GET',
            headers: {
                'Icy-MetaData': '1',
            },
            signal: controller.signal
        })
        .then(async (response) => {
            const icecast = new IcecastReadableStream(
                response,
                options
            );

            await icecast.startReading();
        });
    }

    var methodHandler = undefined;

    switch (method) {
        case StreamSource.SHOUTCAST_V1:
            methodHandler = getShoutcastV1Station;
            break;
        case StreamSource.SHOUTCAST_V2:
            methodHandler = getShoutcastV2Station;
            break;
        case StreamSource.ICECAST:
            methodHandler = getIcecastStation;
            break;
        case StreamSource.STREAM:
            methodHandler = getStreamStation;
            break;
        default:
    }

    if (methodHandler) {
        methodHandler(endpoint, onMetaDataCb);
    }
};
