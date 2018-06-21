<?php
namespace Notify\Common;

use Notify\Config;

class Utils {
	/**
	* @param string $platform
	*/
	public static function validatePlatform(string $platform): void  {
		if (!in_array($platform, Config::Platforms)) {
			throw new InputException(['platform' => "Unknown platform '$platform'"]);
		}
	}

	/**
	* @param mixed $subscription
	*/
	public static function validateSubscription($subscription): void  {
		if (
			!is_array($subscription)
			|| !is_string($subscription['endpoint'] ?? null)
			|| !is_array($subscription['keys'] ?? null)
			|| !is_string($subscription['keys']['p256dh'] ?? null)
			|| !is_string($subscription['keys']['auth'] ?? null)
		) {
			throw new InputException(['subscription' => 'Invalid subscription parameters']);
		}
		self::validateEndpoint($subscription['endpoint']);
	}

	/**
	* Check that the provided endpoint doesn't look unreasonable
	*
	* @param string $endpoint
	*/
	public static function validateEndpoint(string $endpoint): void {
		if (strlen($endpoint) > Config::PushEndpointMaxLen) {
			throw new InputException(['endpoint' => 'Parameter length exceeds ' . Config::PushEndpointMaxLen . ' characters']);
		}
		if (!mb_check_encoding($endpoint, 'UTF8')) {
			throw new InputException(['endpoint' => 'Parameter must be a valid UTF-8 string']);
		}
	}

	/**
	* Decode URL safe base64 string
	*
	* @return string
	*/
	public static function decodeUrlBase64($str): string {
		$str = strtr($str, '_-', '/+');
		$padding = strlen($str) % 4;
		if ($padding > 0) {
			$str .= str_repeat('=', 4 - $padding);
		}
		$str = base64_decode($str, true);
		if ($str === false) {
			throw new \Exception();
		}
		return $str;
	}
}
