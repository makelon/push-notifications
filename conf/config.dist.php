<?php
namespace Notify;

class Config {

// Runtime configuration

	/**
	* Add Access-Control-Allow-Origin HTTP header to specify
	* permitted domains for Cross-Origin Resource Sharing.
	*/
	const Cors = '*';

	/**
	* Debug flag which enables more detailed and potentially sensitive error messages
	*/
	const Debug = false;

	/**
	* Return notifications as arrays instead of sending them to the endpoints
	*/
	const DryRun = false;

	/**
	* Fallback error log if database write fails
	*/
	const ErrorLog = '';

	/**
	* List of known entity types
	*/
	const EntityTypes = ['cat', 'dog'];

	/**
	* List of known platforms
	*/
	const Platforms = ['pc', 'ps4', 'xb1'];

	/**
	* Minimum delay between /test requests with the same endpoint (requires cache)
	*/
	const PushTestMinDelay = 30;

	/**
	* Type of response that the push server will return
	*   - 'full' - Array of notification payloads
	*   - 'count' - Number of sent notifications
	*/
	const ServerResponseType = 'full';

	/**
	* Push notification payload padding
	*   int - Pad to fixed size
	*   true - Automatic
	*   false - Disable padding
	*/
	const WebPushPadding = false;


// Server configuration

	/**
	* Credentials
	*/
	const DbHost = '127.0.0.1';
	const DbName = '';
	const DbUser = '';
	const DbPassword = '';

	const CacheHandler = Cache\Apcu::class;

	/**
	* Whether to use persistent connections (recommended)
	*/
	const DbOptions = [
		\PDO::ATTR_PERSISTENT => true
	];

	/**
	* Application cache isolation namespace
	*/
	const CacheNamespace = '';

	const PushServerSecret = '';

	const PushEndpointMaxLen = 2000;

	/**
	* VAPID
	*/
	const PushServerEmail = 'mailto:me@example.com';
	const PushServerPrivKey = '';
	const PushServerPubKey = '';
}
