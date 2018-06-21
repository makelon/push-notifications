<?php
namespace Notify;

use Notify\Config;
use Notify\Common\InputException;
use Notify\Common\Utils;
use Notify\AbstractHandler;
use Notify\PushServer;

class PushClient extends AbstractHandler {
	/**
	* Add a subscription and its associated filters
	*/
	public function add(): int {
		$platforms = $this->getPlatforms();
		$filters = $this->getFilters();

		$subscription = $this->getSubscription();
		$pdo = $this->db->getPdo();
		$this->db->query("BEGIN");
		$subscriptionId = $this->getSubscriptionId($subscription['endpoint']);
		if ($subscriptionId === false) {
			// New subscription
			$statement = $pdo->prepare('
				INSERT INTO notification_subscriptions
					(endpoint, auth, p256dh, expiration)
				VALUES
					(:endpoint, :auth, :p256dh, :expiration)');
			$this->db->executeStatement($statement, $subscription);
			$subscriptionId = $pdo->lastInsertId('notification_subscription_id_seq');
		}
		else {
			// Old subscription. Remove old filters and add the new ones later
			$statement = $pdo->prepare('DELETE FROM notification_filters WHERE subscription_id = ?');
			$this->db->executeStatement($statement, [$subscriptionId]);
		}

		$statement = $pdo->prepare("
			INSERT INTO notification_filters
				(subscription_id, type, filter)
			VALUES
				($subscriptionId, ?, ?)");
		$numFilters = 0;
		foreach ($filters as $filter) {
			$this->db->executeStatement($statement, [$filter['type'], $filter['value']]);
			$filterId = $pdo->lastInsertId('notification_filter_id_seq');
			$platformValues = implode(', ', array_fill(0, count($platforms), "($filterId, ?)"));
			$platformStatement = $pdo->prepare("
				INSERT INTO notification_platforms
					(filter_id, platform)
				VALUES
					$platformValues");
			$this->db->executeStatement($platformStatement, $platforms);
			++$numFilters;
		}
		$this->db->query("COMMIT");
		return $numFilters;
	}

	/**
	* Delete the subscriber's filters for the selected platform
	*/
	public function delete(): int {
		if (!isset($this->postParams['endpoint']) || !is_string($this->postParams['endpoint'])) {
			throw new InputException(['endpoint' => 'Missing endpoint parameter']);
		}
		return empty($this->reqParams['platform'])
			? $this->removeSubscription($this->postParams['endpoint'])
			: $this->removeSubscriptionPlatform($this->postParams['endpoint'], $this->reqParams['platform']);
	}

	public function get(): array {
		try {
			$endpoint = Utils::decodeUrlBase64($this->reqParams['endpoint']);
		}
		catch (\Exception $e) {
			throw new InputException(['endpoint' => 'Invalid endpoint parameter']);
		}
		Utils::validateEndpoint($endpoint);

		$statement = $this->db->getPdo()->prepare('
			SELECT type, filter
			FROM notification_filters, notification_subscriptions
			WHERE subscription_id = notification_subscriptions.id
				AND endpoint = ?');
		$this->db->executeStatement($statement, [$endpoint]);
		$filters = [];
		while ($filter = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$type = $filter['type'];
			$value = $filter['filter'];
			$filters[$type][] = $value;
		}
		return $filters;
	}

	/**
	* Generate filter list
	*
	* @param mixed $argFilters POST request data that should be a list of filters
	* @return array An array of [filter string, filter type] pairs
	*/
	private function getFilters(): array {
		$filters = [];
		foreach (Config::EntityTypes as $filterType) {
			try {
				$filters = array_merge($filters, $this->getFiltersForType($filterType));
			}
			catch (\Exception $e) {
				throw new InputException([$filterType => 'Parameter must be an array of strings']);
			}
		}
		return $filters;
	}

	/**
	* Generate and validate filter list for a selected type
	*
	* @param string $type Filter type
	* @return array An array of [filter string, filter type] pairs
	*/
	private function getFiltersForType($filterType): array {
		$filters = [];
		if (isset($this->postParams[$filterType])) {
			if (!is_array($this->postParams[$filterType])) {
				throw new \Exception();
			}
			foreach ($this->postParams[$filterType] as $filter) {
				if (!is_string($filter)) {
					throw new \Exception();
				}
				$filter = trim($filter);
				if ($filter !== '') {
					$filters[] = [
						'type' => $filterType,
						'value' => $filter
					];
				}
			}
			if (count($filters) === 0) {
				$filters[] = [
					'type' => $filterType,
					'value' => ''
				];
			}
		}
		return $filters;
	}

	/**
	* Generate and validate list of selected platforms
	*
	* @return array Platforms
	*/
	private function getPlatforms(): array {
		if (empty($this->postParams['platforms'])) {
			throw new InputException(['platforms' => 'Missing platforms parameter']);
		}
		$platforms = [];
		foreach ($this->postParams['platforms'] as $platform) {
			$platform = trim($platform);
			Utils::validatePlatform($platform);
			$platforms[] = $platform;
		}
		return array_unique($platforms);
	}

	/**
	* Return ID of a given endpoint
	*
	* @param string $endpoint The subscription endpoint
	* @return int|false The endpoint ID or false if not found
	*/
	private function getSubscriptionId(string $endpoint) {
		$statement = $this->db->getPdo()->prepare('SELECT id FROM notification_subscriptions WHERE endpoint = ?');
		$this->db->executeStatement($statement, [$endpoint]);
		return $statement->fetchColumn(0);
	}

	/**
	* Remove a platform associated with a subscription
	*
	* @param string $endpoint The subscription endpoint
	* @param string $platform Platform
	* @return int Number of removed records
	*/
	protected function removeSubscriptionPlatform(string $endpoint, string $platform): int {
		Utils::validateEndpoint($endpoint);
		Utils::validatePlatform($platform);
		$statement = $this->db->getPdo()->prepare('
			DELETE FROM notification_platforms
			WHERE platform = :platform
				AND filter_id IN (
					SELECT filter_id
					FROM notification_filters, notification_subscriptions
					WHERE subscription_id = notification_subscriptions.id
						AND endpoint = :endpoint
				)');
		$dbParams = [
			'endpoint' => $endpoint,
			'platform' => $platform
		];
		$this->db->executeStatement($statement, $dbParams);
		return $statement->rowCount();
	}

	/**
	* Remove a subscription
	*
	* @param string $endpoint The subscription endpoint
	* @return int Number of removed records
	*/
	protected function removeSubscription(string $endpoint): int {
		$statement = $this->db->getPdo()->prepare('
			DELETE FROM notification_filters
				USING notification_subscriptions
			WHERE subscription_id = notification_subscriptions.id
				AND endpoint = ?');
		$this->db->executeStatement($statement, [$endpoint]);
		return $statement->rowCount();
	}
}
?>
