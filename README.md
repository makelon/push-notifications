# Push Notifications

PHP backend for push notifications with filter support. Includes a client that saves user settings and a server that sends push notifications to the right subscriptions.

## Installation

**Requirements**: PHP >= 7.1 with Composer, PostgreSQL

1. Read the [WebPush library docs](https://github.com/web-push-libs/web-push-php) for performance tips and an explanation of how push notifications work. Generate VAPID keys by following the instructions on that page.

2. Install dependencies.

```sh
composer install
```

3. Create PostgreSQL user and database.

```sql
CREATE USER new-db-user PASSWORD 'pick-a-password';
CREATE DATABASE new-db-name;
\c new-db-name
ALTER DEFAULT PRIVILEGES GRANT SELECT, INSERT, UPDATE ON TABLES TO new-db-user;
ALTER DEFAULT PRIVILEGES GRANT SELECT, UPDATE ON SEQUENCES TO new-db-user;
```

```sh
psql -d new-db-name -f res/postgres.sql
```

4. Copy `conf/config.dist.php` to `conf/config.php` and edit the values. The VAPID keys that were created earlier will be used here.

5. Point your favorite web server to public/index.php.

## API

See `public/notify.html` and `public/svcwrk.dist.js` for a sample implementation.

### Get filters for a given subscription

**URL**: `GET /<endpoint>`

**Parameters**

* `endpoint` - Base64url-encoded string of the browsers <pushManager.getSubscription().endpoint> data

**Return value**

```
{
	<entity type 1>: [
		filter value 1,
		<...>
	],
	<...>
}
```

### Add or update a subscription

**URL**: `POST /add`

**Payload**:

```
{
	subscription: The browser's <pushManager.getSubscription()> data,
	<entity type 1>: [
		filter value 1,
		<...>
	],
	<...>
}
```

### Remove a subscription

**URL**: `POST /<platform>/delete`

**Parameters**:

* `platform` - *Optional*. The platform to remove from a given subscription. If omitted, all of the subscriber's filters are removed.

**Payload**:

```
{
	endpoint: The browser's <pushManager.getSubscription().endpoint> data,
}
```

### Send a test notification

**URL**: `POST /test`

**Payload**:

```
{
	subscription: The browser's <pushManager.getSubscription()> data,
	payload: {
		title: Notification title,
		body: Notification body
	}
}
```

### Send notifications to subscriptions

**URL**: `POST /<platform>/push`

**Parameters**

* `platform` - The platform to send notifications for

**Payload**:

```
{
	key: <Config::PushServerSecret>,
	<entity type 1>: [
		{
			tags: Tags to use for filter matching,
			info: Notification body
		},
		<...>
	]
}
```
