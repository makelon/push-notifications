<?php
namespace Notify\Tests;

use PHPUnit\Framework\TestCase;
use Notify\Config;
use Notify\Common\Utils;
use Notify\Common\InputException;

class ValidateTest extends TestCase {
	/**
	* @dataProvider platformProvider
	*/
	public function testValidatePlatform($platform, $expectSuccess) {
		if (!$expectSuccess) {
			$this->expectException(InputException::class);
		}
		$this->assertNull(Utils::validatePlatform($platform));
	}

	public function platformProvider() {
		foreach (Config::Platforms as $p) {
			yield 'Valid platform' => [$p, true];
		}
		yield 'Invalid platform' => [implode('', Config::Platforms), false];
	}

	/**
	* @dataProvider subscriptionProvider
	*/
	public function testValidateSubscription($subscription, $expectSuccess) {
		if (!$expectSuccess) {
			$this->expectException(InputException::class);
		}
		$this->assertNull(Utils::validateSubscription($subscription));
	}

	public function subscriptionProvider() {
		return [
			'Valid' => [
				[
					'endpoint' => 'test_endpoint',
					'keys' => [
						'auth' => 'auth',
						'p256dh' => 'p256dh'
					]
				],
				true
			],
			'Missing endpoint' => [
				[
					'endpoint' => null,
					'keys' => [
						'auth' => 'auth',
						'p256dh' => 'p256dh'
					]
				],
				false
			],
			'Missing auth' => [
				[
					'endpoint' => 'test_endpoint',
					'keys' => [
						'auth' => null,
						'p256dh' => 'p256dh'
					]
				],
				false
			],
			'Missing p256dh' => [
				[
					'endpoint' => 'test_endpoint',
					'keys' => [
						'auth' => 'auth',
						'p256dh' => null
					]
				],
				false
			],
		];
	}

	/**
	* @dataProvider base64Provider
	*/
	public function testDecodeUrlBase64($input, $expected) {
		$this->assertEquals($expected, Utils::decodeUrlBase64($input));
	}

	public function base64Provider() {
		return [
			['R+5FRQ', "G\xEEEE"],
			['Rkb_Rg==', "FF\xFFF"],
		];
	}
}
