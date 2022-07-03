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

use OCA\Music\AppFramework\Core\Logger;

/**
 * MetaData radio utility functions
 */
class RadioMetadata {

	private $logger;

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	private static function findStr(array $data, string $str) : string {
		$ret = "";
		foreach ($data as $value) {
			$find = \strstr($value, $str);
			if ($find !== false) {
				$ret = $find;
				break;
			}
		}
		return $ret;
	}

	private static function parseStreamUrl(string $url) : array {
		$ret = [];
		$parse_url = \parse_url($url);

		$ret['port'] = 80;
		if (isset($parse_url['port'])) {
			$ret['port'] = $parse_url['port'];
		} else if ($parse_url['scheme'] == "https") {
			$ret['port'] = 443;
		}

		$ret['scheme'] = $parse_url['scheme'];
		$ret['hostname'] = $parse_url['host'];
		$ret['pathname'] = $parse_url['path'];

		if (isset($parse_url['query'])) {
			$ret['pathname'] .= "?" . $parse_url['query'];
		}

		if ($parse_url['scheme'] == "https") {
			$ret['sockAddress'] = "ssl://" . $ret['hostname'];
		} else {
			$ret['sockAddress'] = $ret['hostname'];
		}

		return $ret;
	}

	public function fetchUrlData(string $url) : ?string {
		$title = null;
		list('content' => $content, 'status_code' => $status_code, 'message' => $message) = HttpUtil::loadFromUrl($url);

		if ($status_code == 200) {
			$data = \explode(',', $content);
			$title = $data[6] ?? null; // the title field is optional
		} else {
			$this->logger->log("Failed to read $url: $status_code $message", 'debug');
		}

		return $title;
	}

	public function fetchStreamData(string $url, int $maxattempts, int $maxredirect) : ?string {
		$timeout = 10;
		$streamTitle = null;
		$pUrl = self::parseStreamUrl($url);
		if ($pUrl['sockAddress'] && $pUrl['port']) {
			$fp = \fsockopen($pUrl['sockAddress'], $pUrl['port'], $errno, $errstr, $timeout);
			if ($fp !== false) {
				$out = "GET " . $pUrl['pathname'] . " HTTP/1.1\r\n";
				$out .= "Host: ". $pUrl['hostname'] . "\r\n";
				$out .= "Accept: */*\r\n";
				$out .= HttpUtil::userAgentHeader() . "\r\n";
				$out .= "Icy-MetaData: 1\r\n";
				$out .= "Connection: Close\r\n\r\n";
				\fwrite($fp, $out);
				\stream_set_timeout($fp, $timeout);

				$header = \fread($fp, 1024);
				$headers = \explode("\n", $header);

				if (\strpos($headers[0], "200 OK") !== false) {
					$interval = 0;
					$line = self::findStr($headers, "icy-metaint:");
					if ($line) {
						$interval = (int)\trim(explode(':', $line)[1]);
					}

					if ($interval && $interval < 64001) {
						$attempts = 0;
						while ($attempts < $maxattempts) {
							for ($j = 0; $j < $interval; $j++) {
								\fread($fp, 1);
							}

							$meta_length = \ord(\fread($fp, 1)) * 16;
							if ($meta_length) {
								$metadatas = \explode(';', \fread($fp, $meta_length));
								$metadata = self::findStr($metadatas, "StreamTitle");
								if ($metadata) {
									$streamTitle = Util::truncate(trim(explode('=', $metadata)[1], "'"), 256);
									break;
								}
							}
							$attempts++;
						}
					} else {
						$streamTitle = $this->fetchUrlData($pUrl['scheme'] . '://' . $pUrl['hostname'] . ':' . $pUrl['port'] . '/7.html');
					}
				} else if ($maxredirect > 0 && strpos($headers[0], "302 Found") !== false) {
					$value = self::findStr($headers, "Location:");
					if ($value) {
						$location = \trim(\substr($value, 10), "\r");
						$streamTitle = $this->fetchStreamData($location, $maxattempts, $maxredirect-1);
					}
				}
				\fclose($fp);
			}
		}

		return $streamTitle === '' ? null : $streamTitle;
	}

}
