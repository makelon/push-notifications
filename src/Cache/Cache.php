<?php
namespace Notify\Cache;

use Notify\Config;

class Cache {
	/**
	* @var Cache handler
	*/
	private static $handler;

	/**
	* Initialize cache handler
	*/
	public static function init() {
		if (self::$handler === null) {
			if (empty(Config::CacheHandler)) {
				self::$handler = false;
				return;
			}
			try {
				$handler = Config::CacheHandler;
				self::$handler = new $handler();
			}
			catch (\Exception $e) {
				self::$handler = false;
			}
		}
	}

	/**
	* @param string $key
	* @return mixed Cached value
	*/
	public static function get(string $key) {
		return self::$handler
			? self::$handler->get(Config::CacheNamespace . $key)
			: false;
	}

	/**
	* @param string $key
	* @param mixed $value
	*/
	public static function set(string $key, $value, int $ttl = 0): bool {
		return self::$handler
			? self::$handler->set(Config::CacheNamespace . $key, $value, $ttl)
			: false;
	}

	/**
	* @param string $key
	* @param mixed $value
	*/
	public static function add(string $key, $value, int $ttl = 0): bool {
		return self::$handler
			? self::$handler->add(Config::CacheNamespace . $key, $value, $ttl)
			: false;
	}

	/**
	* @param string $key
	*/
	public static function delete(string $key): bool {
		return self::$handler
			? self::$handler->delete(Config::CacheNamespace . $key)
			: false;
	}
}
