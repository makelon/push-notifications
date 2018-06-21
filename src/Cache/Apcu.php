<?php
namespace Notify\Cache;

class Apcu implements CacheInterface {
	public function __construct() {
		if (!extension_loaded('apcu')) {
			throw new \Exception();
		}
	}

	/**
	* @param string $key
	* @return mixed Cached value
	*/
	public function get(string $key) {
		return apcu_fetch($key);
	}

	/**
	* @param string $key
	* @param mixed $value
	* @return bool
	*/
	public function set(string $key, $value, int $ttl = 0): bool {
		return apcu_store($key, $value, $ttl);
	}

	/**
	* @param string $key
	* @param mixed $value
	* @return bool
	*/
	public function add(string $key, $value, int $ttl = 0): bool {
		return apcu_add($key, $value, $ttl);
	}

	/**
	* @param string $key
	* @return bool
	*/
	public function delete(string $key) {
		return apcu_delete($key);
	}
}
