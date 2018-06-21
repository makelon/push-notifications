CREATE SEQUENCE notification_subscription_id_seq;

CREATE SEQUENCE notification_filter_id_seq;

CREATE TABLE notification_errors (
	"time" timestamptz NOT NULL DEFAULT current_timestamp,
	source text,
	message text,
	details text
);

CREATE TABLE notification_subscriptions (
	id integer NOT NULL PRIMARY KEY DEFAULT nextval('notification_subscription_id_seq'),
	endpoint text NOT NULL UNIQUE,
	p256dh text NOT NULL,
	auth text NOT NULL,
	expiration bigint NULL
);

CREATE TABLE notification_filters (
	id integer NOT NULL PRIMARY KEY DEFAULT nextval('notification_filter_id_seq'),
	subscription_id integer NOT NULL REFERENCES notification_subscription (id) ON DELETE CASCADE,
	type text NOT NULL,
	filter text NOT NULL
);

CREATE TABLE notification_platforms (
	filter_id integer NOT NULL REFERENCES notification_filters (id) ON DELETE CASCADE,
	platform text NOT NULL
);

CREATE TABLE notification_history (
	"time" timestamptz NOT NULL DEFAULT current_timestamp,
	subscription_id integer,
	payload text NOT NULL
);
