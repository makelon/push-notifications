<?php
namespace Notify;

use Minishlink\WebPush\Subscription;
use Notify\Config;
use Notify\Cache\Cache;
use Notify\Common\Db;
use Notify\Common\InputException;
use Notify\Common\Utils;
use Notify\AbstractHandler;
use Notify\WebPushWrapper;

class PushServer extends AbstractHandler {
	/**
	* @var WebPush instance
	*/
	private $webPush;

	/**
	* @var array Map of [endpoint => subscriptionId] entries for subscriptions with notifications
	*/
	private static $subscriptionIds;

	public function __construct(Db $db, WebPushWrapper $webPush) {
		parent::__construct($db);
		Cache::init();
		$this->webPush = $webPush;
	}

	/**
	* Handler for /push requests. Reads input from request body
	* and sends push notifications to all subscriptions with matching filters
	*
	* @return int|array Depending on config, notification payloads or number of successful notifications
	*/
	public function push() {
		$this->authorize();
		Utils::validatePlatform($this->reqParams['platform']);

		$entities = $this->getEntities();
		if (!empty($entities)) {
			$matches = $this->findMatches($entities);
		}

		$notifications = [];
		if (!empty($matches)) {
			$subscriptionIds = array_keys($matches);
			$placeholders = implode(', ', array_fill(0, count($subscriptionIds), '?'));
			$statement = $this->db->getPdo()->prepare("
				SELECT id, endpoint, p256dh, auth
				FROM notification_subscriptions
				WHERE id IN ($placeholders)");
			$this->db->executeStatement($statement, $subscriptionIds);
	
			while ($subscription = $statement->fetch(\PDO::FETCH_ASSOC)) {
				$notifications[] = self::createNotification($subscription, $matches[$subscription['id']]);
			}
		}
		return $this->sendNotifications($notifications);
	}

	/**
	* Handler for /test requests. Send a push notification to the provided subscription
	*
	* @return int|array Depending on config, notification payloads or number of successful notifications
	*/
	public function test() {
		$subscription = $this->getSubscription();
		if (isset($this->postParams['payload'])) {
			$payload = $this->postParams['payload'];
		}
		else {
			$payload = [
				'title' => 'Test notification',
				'body' => 'If you can read this, the test was most likely successful.'
			];
		}
		if (Config::PushTestMinDelay) {
			$cacheKey = "/test:$subscription[endpoint]";
			$prevTest = Cache::get($cacheKey);
			if ($prevTest && $prevTest > time() - Config::PushTestMinDelay) {
				$message = 'Rate limit of one request per ' . Config::PushTestMinDelay . ' seconds exceeded';
				throw new \InputException($message, 429);
			}
			Cache::set($cacheKey, time(), Config::PushTestMinDelay);
		}
		$notification = self::createNotification($subscription, $payload);
		return $this->sendNotifications([$notification]);
	}

	/**
	* Test if the correct shared secret was provided
	*/
	private function authorize(): void {
		if (!isset($this->postParams['key']) || $this->postParams['key'] !== Config::PushServerSecret) {
			throw new InputException('Permission denied', 403);
		}
	}

	/**
	* Validate and return a list of the provided entities
	*/
	private function getEntities() {
		$entities = [];
		foreach (Config::EntityTypes as $entityType) {
			if (!empty($this->postParams[$entityType])) {
				try {
					$entities[$entityType] = $this->getEntitiesOfType($this->postParams[$entityType]);
				}
				catch (\Exception $e) {
					throw new InputException([$entityType => "Invalid $entityType parameter (" . $e->getMessage() . ')']);
				}
			}
		}
		return $entities;
	}

	/**
	* Pre-process entity tags for future filter matching
	*
	* @param array $entities
	*/
	private function getEntitiesOfType($entities) {
		if (!is_array($entities)) {
			throw new \Exception('Expected an array');
		}
		$retEntities = [];
		foreach ($entities as $entity) {
			if (!isset($entity['tags'])) {
				throw new \Exception('Missing tags property');
			}
			$entityTags = $entity['tags'];
			if (!is_string($entityTags)) {
				throw new \Exception('Expected a string as tags property');
			}
			$retEntities[] = [
				'search' => self::prepareSearchStrings($entityTags),
				'info' => $entity['info'] ?? 'New notification'
			];
		}
		return $retEntities;
	}

	/**
	* Split strings into lowercase words
	*
	* @param array $strings Array of strings to process
	* @return array Two-dimensional array of lowercase words
	*/
	private static function prepareSearchStrings($string): array {
		$stringParts = [];
		foreach (explode(' ', strtolower($string)) as $stringPart) {
			$stringPart = trim($stringPart);
			if ($stringPart !== '') {
				$stringParts[] = $stringPart;
			}
		}
		return $stringParts;
	}

	/**
	* Find subscriptions with filters that match the provided entities
	*
	* @param array $entities Entities to create notifications for
	* @return array
	*/
	private function findMatches($entities): array {
		$query = '
			SELECT subscription_id, type, filter
			FROM notification_filters, notification_platforms
			WHERE notification_filters.id = filter_id
				AND platform = :platform';
		$dbParams = ['platform' => $this->reqParams['platform']];
		if (count($entities) < count(Config::EntityTypes)) {
			$typePlaceholders = [];
			foreach (array_keys($entities) as $i => $entityType) {
				$typePlaceholders[] = ":t{$i}";
				$dbParams["t{$i}"] = $entityType;
			}
			$typePlaceholders = implode(', ', $typePlaceholders);
			$query .= " AND type IN ($typePlaceholders)";
		}
		$statement = $this->db->getPdo()->prepare($query);
		$this->db->executeStatement($statement, $dbParams);

		$matches = [];
		while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
			$subscriptionId = $row['subscription_id'];
			$type = $row['type'];
			$filter = $row['filter'];
			if (empty($entities[$type])) {
				continue;
			}
			$filterParts = [];
			foreach (explode(' ', strtolower($filter)) as $filterPart) {
				$filterParts[] = [$filterPart, strlen($filterPart)];
			}
			foreach ($entities[$type] as $entity) {
				if (count($filterParts) === 0 || self::checkFilter($filterParts, $entity['search'])) {
					$matches[$subscriptionId][$type][] = $entity['info'];
				}
			}
		}
		return $matches;
	}

	/**
	* Test if each part of a filter has a match
	*
	* @param array $filterParts Array of filter words
	* @param array $searchParts Array of words to match against filter parts
	* @return bool True if each filter part has a match
	*/
	private static function checkFilter(array $filterParts, array $searchParts): bool {
		foreach ($filterParts as $filterPart) {
			$found = false;
			for ($i = 0, $end = count($searchParts); $i < $end; ++$i) {
				$searchPart = $searchParts[$i];
				if ($searchPart && !strncmp($searchPart, $filterPart[0], $filterPart[1])) {
					$found = true;
					$searchParts[$i] = null; // Filter "har har" should not trigger on "five hares"
					break;
				}
			}
			if (!$found) {
				return false;
			}
		}
		return true;
	}

	/**
	* Create a push notification to send to the Web Push library and update the $subscriptionId map
	*
	* @param array $subscription Subscriber info
	* @param mixed $payload Data to include in the push notification
	* @return array Push notification
	*/
	private static function createNotification(array $subscription, $payload): array {
		$subscriptionId = $subscription['id'] ?? null;
		self::$subscriptionIds[$subscription['endpoint']] = $subscriptionId;
		return [
			'subscriptionId' => $subscriptionId,
			'subscription' => Subscription::create([
				'endpoint' => $subscription['endpoint'],
				'publicKey' => $subscription['p256dh'],
				'authToken' => $subscription['auth']
			]),
			'payload' => $payload
		];
	}

	/**
	* Send the push notifications
	*
	* @param array $notifications Array of push notifications to send
	* @return int|array Depending on config, notification payloads or number of successful notifications
	*/
	private function sendNotifications(array $notifications) {
		if (!Config::DryRun && count($notifications) > 0) {
			$this->recordHistory($notifications);
			$numSuccess = 0;
			$webPush = $this->webPush->get();
			foreach ($notifications as $notification) {
				$payload = json_encode($notification['payload']);
				try {
					$webPush->sendNotification($notification['subscription'], $payload);
					++$numSuccess;
				}
				catch (\Exception $e) {
					$this->logError(
						"WebPush::sendNotification: Subscription $notification[subscriptionId]",
						$e->getMessage(),
						$payload
					);
				}
			}
			$results = $webPush->flush();
			if ($results !== true) {
				$invalidSubscriptionIds = [];
				foreach ($results as $notification) {
					if (!$notification['success']) {
						--$numSuccess;
						if ($notification['expired']) {
							$invalidSubscriptionIds[] = self::$subscriptionIds[$notification['endpoint']];
						}
						$this->logError(
							"WebPush::flush",
							"$notification[statusCode] $notification[reasonPhrase]",
							"$notification[message]"
						);
					}
				}
				$this->removeSubscriptions($invalidSubscriptionIds);
			}
		}
		return Config::ServerResponseType === 'full'
			? array_column($notifications, 'payload')
			: $numSuccess;
	}

	/**
	* Remove subscriptions and their associated filters from the database
	*
	* @param array $subscriptionIds IDs of subscriptions to remove
	*/
	private function removeSubscriptions(array $subscriptionIds): void {
		if (count($subscriptionIds) > 0) {
			$placeholders = implode(', ', array_fill(0, count($subscriptionIds), '?'));
			$statement = $this->db->getPdo()->prepare("DELETE FROM notification_subscriptions WHERE id IN ($placeholders)");
			$this->db->executeStatement($statement, $subscriptionIds);
		}
	}

	/**
	* Add notification history records
	*
	* @param array $notifications
	*/
	private function recordHistory(array $notifications): void {
		if (count($notifications) > 0) {
			$dbParams = [];
			$placeholders = [];
			foreach ($notifications as $i => $notification) {
				$placeholders[] = "(:s{$i}, :p{$i})";
				$dbParams["s{$i}"] = $notification['subscriptionId'];
				$dbParams["p{$i}"] = json_encode($notification['payload']);
			}
			$placeholders = implode(', ', $placeholders);
			$statement = $this->db->getPdo()->prepare("
				INSERT INTO notification_history
					(subscription_id, payload)
				VALUES
					$placeholders");
			$this->db->executeStatement($statement, $dbParams);
		}
	}
}
?>
