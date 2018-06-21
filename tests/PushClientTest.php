<?php
namespace Notify\Tests;

use Notify\Config;
use Notify\PushClient;
use Notify\Tests\AbstractPushTestCase;

class PushClientTest extends AbstractPushTestCase {
	/**
	* @return array Actual notification filters
	*/
	private function getActualResults() {
		$results = $this->getPdo()->query('
			SELECT type, filter, platform
			FROM notification_filters, notification_platforms
			WHERE filter_id = notification_filters.id');
		return $results->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	* @return array Expected notification filters
	*/
	private function getExpectedResults($platforms, $entities) {
		$results = [];
		foreach (Config::EntityTypes as $type) {
			$entitiesOfType = empty($entities[$type]) ? [''] : $entities[$type];
			foreach ($entitiesOfType as $filter) {
				foreach (array_unique($platforms) as $platform) {
					$results[] = [
						'type' => $type,
						'filter' => $filter,
						'platform' => $platform
					];
				}
			}
		}
		return $results;
	}

	public function testAdd() {
		// PushClient::add overwrites existing filters for a given subscription.
		// Define all tests here to retain state for better coverage.
		$e = Config::EntityTypes;
		$p = Config::Platforms;
		$tests = [
			'One of each' => [
				[$p[0]],
				[
					$e[0] => ['a1'],
					$e[1] => ['b1']
				],
				2
			],
			'Multi-platform, empty filterB' => [
				[$p[0], $p[1]],
				[
					$e[0] => ['a2']
				],
				2
			],
			'Non-unique platforms, empty filterA' => [
				[$p[0], $p[0]],
				[
					$e[1] => ['b2', 'b3'],
				],
				3
			]
		];
		foreach ($tests as $message => [$platforms, $entities, $filterCount]) {
			$pushClient = new PushClient($this->getDb());
			$pushClient->setPostParams($this->addPostParams($platforms, $entities));
			$pushClient->add();
	
			$actual = $this->getActualResults();
			$expected = $this->getExpectedResults($platforms, $entities);
			$this->assertUnorderedArrayEqual($expected, $actual, 1, $message);
			$this->assertTableRowCount('notification_filters', $filterCount, $message);
		}
	}

	/**
	* @depends testAdd
	*/
	public function testDelete() {
		$e = Config::EntityTypes;
		$p = Config::Platforms;
		$tests = [
			'Delete selected platform' => [
				[$p[0], $p[1]],
				[$p[0], $p[1]],
				2
			],
			'Delete non-existent platform' => [
				[$p[0]],
				[$p[1]],
				0
			],
			'Delete all platforms' => [
				$p,
				[null],
				2
			],
		];
		foreach ($tests as $message => [$platforms, $platformsDelete, $expectDeleted]) {
			$pushClient = new PushClient($this->getDb());
			$pushClient->setPostParams($this->addPostParams($platforms, []));
			$pushClient->add();

			foreach ($platformsDelete as $platform) {
				$pushClient = new PushClient($this->getDb());
				$pushClient->setPostParams($this->deletePostParams());
				$pushClient->setReqParams($this->deleteReqParams($platform));
				$deleted = $pushClient->delete();
				$this->assertSame($expectDeleted, $deleted, $message);

				$platforms = $platform === null ? [] : array_diff($platforms, [$platform]);
				$actual = $this->getActualResults();
				$expected = $this->getExpectedResults($platforms, []);
				$this->assertUnorderedArrayEqual($expected, $actual, 1, $message);
			}
		}
	}

	/**
	* @return array URL parameters for delete requests
	*/
	private function deleteReqParams($platform) {
		return [ 'platform' => $platform ];
	}

	/**
	* @return array POST parameters for delete requests
	*/
	private function deletePostParams() {
		return [ 'endpoint' => $this->getSubscription()['endpoint'] ];
	}
}
