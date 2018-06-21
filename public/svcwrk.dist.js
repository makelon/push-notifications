'use strict';

const icon = '', // Icon (preferably 192x192 or higher resolution) that's displayed in notification popups
	badge = '', // Badge that's displayed in the system bar when there are unread notifications
	titlePrefix = 'Animal Factory: ',
	entityTypes = {
		'cat': 'Cats',
		'dog': 'Dogs'
	};

// Message from the browser
function handleMessage(event) {
	console.log('Got message event', event);
	if (!(self.Notification && self.Notification.permission === 'granted')) {
		return;
	}
	for (var notification of event.data) {
		if ('title' in notification) {
			showNotification(notification.title, notification.body);
		}
		else {
			showAlertNotifications(notification);
		}
	}
}

// Message from the vendor push service
function handlePush(event) {
	console.log('Got push event', event);
	if (!(self.Notification && self.Notification.permission === 'granted')) {
		return;
	}
	try {
		var notification = event.data.json();
		if (notification) {
			showAlertNotifications(notification);
		}
	}
	catch(err) {}
}

function showNotification(title, body) {
	self.registration.showNotification(title, {
		body: body,
		icon: icon,
		badge: badge
	})
}

function showAlertNotifications(notification) {
	for (var entityType in notification) {
		const body = notification[entityType].join(', \n'),
			title = titlePrefix + (entityType in entityTypes
				? entityTypes[entityType] + ' available'
				: 'New notification');
		showNotification(title, body);
	}
}

self.onmessage = handleMessage;
self.onpush = handlePush;
