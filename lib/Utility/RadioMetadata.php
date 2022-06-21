<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moahmed-Ismail MEJRI <imejri@hotmail.com>
 * @copyright Moahmed-Ismail MEJRI 2022
 */

namespace OCA\Music\Utility;

/**
 * MetaData radio utility functions
 */
class RadioMetadata {

	public static function fetchUrlData($url) : array {
		$content = \file_get_contents($url);
		list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
		return [$content, $status_code, $msg];
	}

	public static function fetchStreamData($url, $maxattempts, $maxredirect) {
		$parse_url = parse_url($url);
		$port = 80;
		if (isset($parse_url['port'])) {
			$port = $parse_url['port'];
		} else if ($parse_url['scheme'] == "https") {
			$port = 443;
		}
		$hostname = $parse_url['host'];
		$pathname = $parse_url['path'];
		if (isset($parse_url['query'])) {
			$pathname .= "?" . $parse_url['query'];
		}
		if ($parse_url['scheme'] == "https") {
			$sockadd = "ssl://" . $hostname;
		} else {
			$sockadd = $hostname;
		}
		$streamTitle = "";
		if (($sockadd)&&($port)) {
			$fp = fsockopen($sockadd, $port, $errno, $errstr, 15);
			if ($fp != false) {
				$out = "GET " . $pathname . " HTTP/1.1\r\n";
				$out .= "Host: ". $hostname . "\r\n";
				$out .= "Accept: */*\r\n"; /* test */
				$out .= "User-Agent: OCMusic/1.52\r\n";
				$out .= "Icy-MetaData: 1\r\n";
				$out .= "Connection: Close\r\n\r\n";
				fwrite($fp, $out);

				$header = fread($fp, 1024);
				$headers = array();
				$headers = explode("\n", $header);

				if (strstr($headers[0], "200 OK") !== false) {
					$interval = 0;
					foreach ($headers as $value) {
						if (strstr($value, "icy-metaint:") !== false) {
							$val = explode(':', $value);
							$interval = trim($val[1]);
							break;
						}
					}

					if (($interval)&&($interval<64001)) {
						$attempts = 0;
						while ($attempts < $maxattempts) {
							for ($j = 0; $j < $interval; $j++) {
								fread($fp, 1);
							}

							$meta_length = ord(fread($fp, 1)) * 16;
							if ($meta_length) {
								$metadatas = explode(';', fread($fp, $meta_length));
                        					foreach ($metadatas as $metadata) {
			        		                        if (strstr($metadata, "StreamTitle") !== false) {
										$streamTitle = trim(explode('=', $metadata)[1], "'");
										break;
                                					}
								}
							}
							$attempts++;
						}
					}
				} else if (($maxredirect>0)&&(strstr($headers[0], "302 Found") !== false)) {
					foreach ($headers as $value) {
						$val = strstr($value, "Location:");
						if ($val) {
							$location = trim(substr($val, 10), "\r");
							$streamTitle = RadioMetadata::fetchStreamData($location, $maxattempts, $maxredirect-1);
							break;
						}
					}
				}
				fclose($fp);
			}
		}
		return $streamTitle;
	}

}
