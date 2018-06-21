<?php
namespace Notify;

use Notify\Config;
use Notify\Common\Db;
use Notify\Common\InputException;
use Notify\Common\Utils;

abstract class AbstractHandler {
	/**
	* @var Db
	*/
	protected $db;

	/**
	* @var array POST parameters
	*/
	protected $postParams;

	/**
	* @var array Request parameters, usually provided by the router
	*/
	protected $reqParams;

	public function __construct(Db $db) {
		$this->db = $db;
	}

	/**
	* Validate and return push subscription
	*/
	protected function getSubscription() {
		if (!isset($this->postParams['subscription'])) {
			throw new InputException(['subscription' => 'Missing subscription parameter']);
		}
		Utils::validateSubscription($this->postParams['subscription']);
		$subscription = $this->postParams['subscription'];
		return [
			'auth' => $subscription['keys']['auth'],
			'p256dh' => $subscription['keys']['p256dh'],
			'endpoint' => $subscription['endpoint'],
			'expiration' => $subscription['expirationTime'] ?? null
		];
	}

	/**
	* @param array $postParams
	*/
	public function setPostParams(?array $postParams = null): void {
		if ($postParams) {
			$this->postParams = $postParams;
		}
		else {
			$post = file_get_contents('php://input');
			if ($post !== '') {
				$this->postParams = json_decode($post, true);
				if ($this->postParams === null) {
					throw new InputException('Invalid input data');
				}
			}
		}
	}

	/**
	* @param array $reqParams
	*/
	public function setReqParams(array $reqParams): void {
		$this->reqParams = $reqParams;
	}

	/**
	* Write a notification error
	* @param string $source
	* @param string $message
	* @param string $details
	*/
	protected function logError(string $source, string $message, string $details): void {
		$statement = $this->db->getPdo()->prepare('
			INSERT INTO notification_errors
				(source, message, details)
			VALUES
				(?, ?, ?)');
		try {
			$this->db->executeStatement($statement, [$source, $message, $details]);
		}
		catch (\Exception $e) {
			if (Config::ErrorLog) {
				$log = sprintf(
					"%s (%s)\n" . "%s: %s\n" . "%s\n\n",
					$e->getMessage(),
					$e->getDetails(),
					$source,
					$message,
					$details
				);
				error_log($log, 3, Config::ErrorLog);
			}
			else {
				$log = sprintf("%s (%s)", $e->getMessage(), $e->getDetails());
				error_log($log);
			}
		}
	}
}
