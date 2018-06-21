<?php
namespace Notify\Tests;

use PHPUnit\Framework\TestCase;
use Notify\Config;
use Notify\Cache\Cache;

class CacheTest extends TestCase {
	private $key = 'testKey';
	private $value = [true, 1, 'a', null];

	public static function setUpBeforeClass() {
		Cache::init();
	}

	public function setUp() {
		if (!ini_get('apc.enable_cli')) {
			$this->markTestSkipped('APCu disabled in CLI mode');
		}
	}

	public function testSet() {
		$this->assertTrue(Cache::set($this->key, $this->value));
	}

	/**
	* @depends testSet
	*/
	public function testAdd() {
		$this->assertFalse(Cache::add($this->key, $this->value));
		$this->assertTrue(Cache::add('x' . $this->key, $this->value));
	}

	/**
	* @depends testAdd
	*/
	public function testGet() {
		$this->assertEquals($this->value, Cache::get($this->key));
	}

	/**
	* @depends testGet
	*/
	public function testDelete($value) {
		$this->assertTrue(Cache::delete($this->key));
		$this->assertFalse(Cache::delete($this->key));
	}
}
?>
