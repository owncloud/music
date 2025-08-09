<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023 - 2025
 */

namespace OCA\Music\Service;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Db\AmpacheUserMapper;

class AmpacheImageService {

	private AmpacheUserMapper $userMapper;
	private Logger $logger;

	public function __construct(
			AmpacheUserMapper $userMapper,
			Logger $logger) {
		$this->userMapper = $userMapper;
		$this->logger = $logger;
	}

	public function getToken(string $entityType, int $entityId, int $apiKeyId) : ?string {
		$keyHash = $this->userMapper->getPasswordHash($apiKeyId);

		if ($keyHash !== null) {
			$entityHash = \hash('sha256', "$entityType-$entityId-$keyHash");
			$truncatedHash = \substr($entityHash, 0, 32);
			return (string)$apiKeyId . '-' . $truncatedHash;
		} else {
			return null;
		}
	}

	public function getUserForToken(string $token, string $entityType, int $entityId) : ?string {
		$userId = null;
		$parts = \explode('-', $token, 2);
		if (\count($parts) === 2) {
			$apiKeyId = (int)$parts[0];
			$correctToken = $this->getToken($entityType, $entityId, $apiKeyId);

			if ($token === $correctToken) {
				$userId = $this->userMapper->getUserId($apiKeyId);
			}
		}
		return $userId;
	}

}