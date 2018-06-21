<?php
namespace Notify\Cache;

interface CacheInterface {
	public function get(string $key);
	public function set(string $key, $value, int $ttl = 0): bool;
	public function add(string $key, $value, int $ttl = 0): bool;
	public function delete(string $key);
}
