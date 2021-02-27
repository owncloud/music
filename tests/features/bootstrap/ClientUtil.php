<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

/**
 * Common utilities for AmpacheClient and SubsonicClient
 */
class ClientUtil {

	/**
	 * Get XML from HTTP response. There used to be method $response->xml() in Guzzle 5.x but not
	 * anymore in 6.x. The logic below is a modified copy from the old xml() method.
	 */
	public static function getXml($response, array $config = []) {
		if (\PHP_VERSION_ID < 80000) {
			// libxml_disable_entity_loader is useless and deprecated on PHP 8.0 but important
			// for security reasons on any older PHP version
			$disableEntities = \libxml_disable_entity_loader(true);
		}
		$internalErrors = \libxml_use_internal_errors(true);
		try {
			// Allow XML to be retrieved even if there is no response body
			$xml = new \SimpleXMLElement(
					(string) $response->getBody() ?: '<root />',
					isset($config['libxml_options']) ? $config['libxml_options'] : LIBXML_NONET,
					false,
					isset($config['ns']) ? $config['ns'] : '',
					isset($config['ns_is_prefix']) ? $config['ns_is_prefix'] : false
			);
			if (\PHP_VERSION_ID < 80000) {
				\libxml_disable_entity_loader($disableEntities);
			}
			\libxml_use_internal_errors($internalErrors);
		} catch (\Exception $e) {
			if (\PHP_VERSION_ID < 80000) {
				\libxml_disable_entity_loader($disableEntities);
			}
			\libxml_use_internal_errors($internalErrors);
			throw new Exception(
					'Unable to parse response body into XML: ' . $e->getMessage() .
					'; libxml error: ' . \libxml_get_last_error()->message
					);
		}
		return $xml;
	}

}