<?php
namespace Notify\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\Operation\Factory;
use Makelon\UnorderedArrayEqual\UnorderedArrayEqual;
use Notify\Common\Db;
use Notify\Config;

abstract class AbstractPushTestCase extends TestCase {
	use TestCaseTrait;
	use UnorderedArrayEqual;

	static private $db = null;

	private $connection = null;

	final public function getConnection() {
		if ($this->connection === null) {
			$this->connection = $this->createDefaultDBConnection($this->getPdo());
		}
		return $this->connection;
	}

	public function getDb() {
		if (self::$db === null) {
			self::$db = new Db();
		}
		return self::$db;
	}

	public function getPdo() {
		return $this->getDb()->getPdo();
	}

	protected function getDataSet() {
		return $this->getConnection()->createDataSet();
	}

	protected function getSetUpOperation() {
		return Factory::TRUNCATE(true);
	}

	protected function getSubscription() {
		return [
			'endpoint' => 'test_endpoint',
			'keys' => [
				'p256dh' => 'test_p256dh',
				'auth' => 'test_auth'
			]
		];
	}

	protected function addPostParams($platforms, $entities) {
		$params = [
			'subscription' => $this->getSubscription(),
			'platforms' => $platforms
		];
		foreach ($entities as $key => $value) {
			$params[$key] = $value;
		}
		return $params;
	}
}
