<?php
namespace Notify;

use Minishlink\WebPush\WebPush;
use Notify\Config;

class WebPushWrapper {
	/**
	* @var WebPush instance
	*/
	private static $webPush;

	/**
	* @param WebPush $webPush
	*/
	public function __construct(WebPush $webPush = null) {
		if ($webPush) {
			self::$webPush = $webPush;
		}
	}

	/**
	* @return WebPush
	*/
	public function get() {
		if (self::$webPush === null) {
			self::$webPush = new WebPush([
				'VAPID' => [
					'subject' => Config::PushServerEmail,
					'publicKey' => Config::PushServerPubKey,
					'privateKey' => Config::PushServerPrivKey
				]
			]);
			self::$webPush->setAutomaticPadding(Config::WebPushPadding);
		}
		return self::$webPush;
	}
}
