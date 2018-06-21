<?php
namespace Notify\Tests;

use Minishlink\WebPush\WebPush;
use Notify\Tests\AbstractPushTestCase;
use Notify\Config;
use Notify\PushClient;
use Notify\PushServer;
use Notify\WebPushWrapper;

class PushServerTest extends AbstractPushTestCase {
	/**
	* Add some notification filters to the database
	*/
	private function preparePushTest() {
		$e = Config::EntityTypes;
		$p = Config::Platforms;
		$pushClient = new PushClient($this->getDb());
		$pushClient->setPostParams($this->addPostParams(
			[$p[0]],
			[
				$e[0] => [
					'go words',
					're re re'
				]
			]
		));
		$pushClient->add();
	}

	public function testTest() {
		$pushServer = $this->getPushServer();
		$payload = [
			'title' => 'Test title',
			'body' => 'Test body'
		];
		$pushServer->setPostParams([
			'subscription' => $this->getSubscription(),
			'payload' => $payload
		]);
		$actual = $pushServer->test();
		$this->assertEquals([$payload], $actual);
	}

	/**
	* @expectedException Notify\Common\InputException
	* @expectedExceptionCode 403
	*/
	public function testAuthFail() {
		$pushServer = $this->getPushServer();
		$pushServer->setPostParams([ 'secret' => 'x' . Config::PushServerSecret ]);
		$pushServer->setReqParams([ 'platform' => Config::Platforms[0] ]);
		$pushServer->push();
	}

	public function testPush() {
		$this->preparePushTest();
		$pushServer = $this->getPushServer();
		foreach ($this->getPushTestData() as $message => [$platform, $entities, $expectedMatches]) {
			$pushServer->setPostParams($this->pushPostParams($entities));
			$pushServer->setReqParams(['platform' => $platform]);
			$results = $pushServer->push();
			$expectedResults = $this->getExpectedPushResults($entities, $expectedMatches);
			$this->assertUnorderedArrayEqual($expectedResults, $results, -1, $message);
		}
	}

	public function testPushFail() {
		$testData = $this->getPushTestData();
		[$platform, $entities, $expectedMatches] = array_shift($testData);
		$this->preparePushTest();
		$pushServer = $this->getPushServer(true);
		$pushServer->setPostParams($this->pushPostParams($entities));
		$pushServer->setReqParams(['platform' => $platform]);
		$pushServer->push();

		// An expired subscription should trigger a delete cascade
		$this->assertTableRowCount('notification_subscriptions', 0, 'Subscriptions table');
		$this->assertTableRowCount('notification_filters', 0, 'Filter table');
		$this->assertTableRowCount('notification_platforms', 0, 'Platform table');

		// History table should still be updated
		$expectedHistory = array_map('json_encode', $this->getExpectedPushResults($entities, $expectedMatches));
		$history = $this->getPdo()
			->query('SELECT payload FROM notification_history')
			->fetchAll(\PDO::FETCH_COLUMN, 0);
		$this->assertUnorderedArrayEqual($expectedHistory, $history, 1, 'History table');

		// Error log should have details about the failure
		$failData = $this->getPushFailTestData()[0];
		$expectedErrors = [
			[
				'source' => 'WebPush::flush',
				'message' => "$failData[statusCode] $failData[reasonPhrase]",
				'details' => "$failData[message]",
			]
		];
		$errors = $this->getPdo()
			->query('SELECT source, message, details FROM notification_errors')
			->fetchAll(\PDO::FETCH_ASSOC);
		$this->assertUnorderedArrayEqual($expectedErrors, $errors, 1, 'Error table');
	}

	/**
	* @return array Expected push notifications
	*/
	private function getExpectedPushResults($entities, $expectedMatches) {
		$results = [];
		foreach ($entities as $type => $entitiesOfType) {
			$matchesOfType = $expectedMatches[$type];
			foreach ($entitiesOfType as $i => $entity) {
				if ($matchesOfType[$i]) {
					$results[0][$type][] = $entity['info'] ?? 'New notification';
				}
			}
		}
		return $results;
	}

	/**
	* @return array POST parameters for push requests
	*/
	private function pushPostParams($entities) {
		$params = [ 'key' => Config::PushServerSecret ];
		foreach ($entities as $type => $entitiesOfType) {
			$params[$type] = $entitiesOfType;
		}
		return $params;
	}

	/**
	* Mock WebPush to control endpoint responses
	*
	* @param bool $fail Whether WebPush::flush returns true or an error response
	* @return WebPush instance
	*/
	private function getPushServer(bool $fail = false) {
		$return = $fail
			? $this->getPushFailTestData()
			: true;

		$mockWebPush = $this->createMock(WebPush::class);
		$mockWebPush->method('flush')
			->will($this->returnValue($return));

		return new PushServer($this->getDb(), new WebPushWrapper($mockWebPush));
	}

	private function getPushTestData() {
		$e = Config::EntityTypes;
		$p = Config::Platforms;
		$prefixMatch = [ 'tags' => 'three good words' ];
		$prefixMiss = [ 'tags' => 'three bad words' ];
		$repeatMiss = [ 'tags' => 'no repeats' ];
		$repeatMatch = [ 'tags' => 'repeatedly repeated repetitions' ];
		return [
			'Prefix match and miss' => [
				$p[0],
				[
					$e[0] => [ $prefixMatch, $prefixMiss ]
				],
				[
					$e[0] => [ true, false ]
				]
			],
			'Repeated prefix miss' => [
				$p[0],
				[
					$e[0] => [ $repeatMiss ]
				],
				[
					$e[0] => [ false ]
				]
			],
			'Repeated prefix match' => [
				$p[0],
				[
					$e[0] => [ $repeatMatch ]
				],
				[
					$e[0] => [ true ]
				]
			],
			'Empty filter match' => [
				$p[0],
				[
					$e[1] => [ $prefixMiss ],
				],
				[
					$e[1] => [ true ]
				]
			],
			'Multiple matches' => [
				$p[0],
				[
					$e[0] => [ $repeatMatch, $prefixMatch ]
				],
				[
					$e[0] => [ true, true ]
				]
			],
			'Platform miss' => [
				$p[1],
				[
					$e[0] => [ $prefixMatch ]
				],
				[
					$e[0] => [ false ]
				]
			]
		];
	}

	public function getPushFailTestData() {
		return [
			[
	            'success' => false,
	            'endpoint' => $this->getSubscription()['endpoint'],
	            'message' => 'test_message',
	            'statusCode' => 418,
	            'reasonPhrase' => "I'm a teapot",
	            'expired' => true,
	            'content' => 'test_content',
	            'headers' => 'test_headers'
			]
		];
	}
}
