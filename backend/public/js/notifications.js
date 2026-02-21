// (function () {
//     document.addEventListener('DOMContentLoaded', function () {
//         var container = document.querySelector('[data-notifications-root]');
//         if (!container) {
//             return;
//         }

//         var endpoint = container.dataset.endpoint || '';
//         var stream = container.dataset.stream || '';
//         var readEndpoint = container.dataset.readEndpoint || '';
//         var limit = parseInt(container.dataset.limit || '20', 10);

//         var counterEl = container.querySelector('.js-notifications-counter');
//         var listEl = container.querySelector('.js-notifications-list');
//         var emptyEl = container.querySelector('.js-notifications-empty');
//         var markAllBtn = container.querySelector('.js-notifications-mark-all');

//         var notifications = [];
//         var eventSource = null;
//         var lastEventId = null;
//         var reconnectDelay = 50000;
//         var pollIntervalId = null;
//         var pollDelay = 150000;

//         function fetchInitial() {
//             if (!endpoint) {
//                 return;
//             }

//             fetch(endpoint, { headers: { Accept: 'application/json' } })
//                 .then(function (response) {
//                     return response.ok ? response.json() : [];
//                 })
//                 .then(function (data) {
//                     if (!Array.isArray(data)) {
//                         return;
//                     }
//                     notifications = data;
//                     lastEventId = notifications.length ? Number(notifications[0].id) : null;
//                     render();
//                 })
//                 .catch(function () {});
//         }

//         function openStream() {
//             if (!stream || typeof EventSource === 'undefined') {
//                 startPolling();
//                 return;
//             }

//             var url = new URL(stream, window.location.origin);
//             if (lastEventId) {
//                 url.searchParams.set('lastEventId', String(lastEventId));
//             }

//             closeStream();
//             eventSource = new EventSource(url);
//             eventSource.addEventListener('notification', handleNotification);
//             eventSource.onerror = function () {
//                 closeStream();
//                 startPolling();
//                 setTimeout(openStream, reconnectDelay);
//                 reconnectDelay = Math.min(reconnectDelay * 2, 30000);
//             };
//             reconnectDelay = 2000;
//             stopPolling();
//         }

//         function closeStream() {
//             if (eventSource) {
//                 eventSource.close();
//                 eventSource = null;
//             }
//         }

//         function startPolling() {
//             if (pollIntervalId !== null) {
//                 return;
//             }
//             pollIntervalId = window.setInterval(fetchInitial, pollDelay);
//         }

//         function stopPolling() {
//             if (pollIntervalId === null) {
//                 return;
//             }
//             clearInterval(pollIntervalId);
//             pollIntervalId = null;
//         }

//         function handleNotification(event) {
//             var payload;
//             try {
//                 payload = JSON.parse(event.data);
//             } catch (error) {
//                 return;
//             }

//             payload.id = Number(payload.id);
//             payload.read = Boolean(payload.read);
//             payload.priority = payload.priority || 'info';
//             lastEventId = Number(event.lastEventId || payload.id || lastEventId || 0);

//             notifications = [payload].concat(notifications.filter(function (entry) {
//                 return entry.id !== payload.id;
//             }));

//             if (notifications.length > limit) {
//                 notifications.length = limit;
//             }

//             render();
//         }

//         function render() {
//             if (!listEl) {
//                 return;
//             }

//             if (!notifications.length) {
//                 showEmptyState(true);
//                 listEl.innerHTML = '';
//                 updateCounter(0);
//                 return;
//             }

//             showEmptyState(false);
//             var fragment = document.createDocumentFragment();
//             notifications.forEach(function (notification) {
//                 fragment.appendChild(buildItem(notification));
//             });
//             listEl.innerHTML = '';
//             listEl.appendChild(fragment);
//             var unreadCount = notifications.filter(function (notification) {
//                 return !notification.read;
//             }).length;
//             updateCounter(unreadCount);
//         }

//         function buildItem(notification) {
//             var item = document.createElement('a');
//             item.className = 'dropdown-item d-flex align-items-center ' + (notification.read ? '' : 'bg-light');
//             item.href = notification.link || '#';
//             item.dataset.notificationId = String(notification.id);

//             var tone = notification.priority || notification.type;
//             var iconCircle = document.createElement('div');
//             iconCircle.className = 'icon-circle bg-' + resolveColor(tone);
//             var icon = document.createElement('i');
//             icon.className = resolveIcon(tone) + ' text-white';
//             iconCircle.appendChild(icon);

//             var iconWrapper = document.createElement('div');
//             iconWrapper.className = 'mr-3';
//             iconWrapper.appendChild(iconCircle);

//             var textWrapper = document.createElement('div');
//             textWrapper.innerHTML = '<div class="small text-gray-500">' + formatDate(notification.date) + '</div>' +
//                 '<span class="font-weight-bold">' + (notification.message || '') + '</span>';

//             item.appendChild(iconWrapper);
//             item.appendChild(textWrapper);

//             item.addEventListener('click', function (event) {
//                 if (!notification.link) {
//                     event.preventDefault();
//                 }
//                 if (!notification.read) {
//                     markAsRead([notification.id]);
//                     notification.read = true;
//                     render();
//                 }
//             });

//             return item;
//         }

//         function updateCounter(value) {
//             if (!counterEl) {
//                 return;
//             }
//             counterEl.textContent = value > 9 ? '9+' : String(value);
//             counterEl.style.display = value > 0 ? 'inline-block' : 'none';
//         }

//         function showEmptyState(visible) {
//             if (!emptyEl) {
//                 return;
//             }
//             emptyEl.classList.toggle('d-none', !visible);
//         }

//         function markAsRead(ids, markAll) {
//             if (!readEndpoint) {
//                 return;
//             }

//             var body = markAll ? { all: true } : { ids: ids };
//             fetch(readEndpoint, {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json',
//                     Accept: 'application/json'
//                 },
//                 body: JSON.stringify(body),
//                 credentials: 'same-origin'
//             }).catch(function () {});
//         }

//         function resolveColor(type) {
//             var dictionary = {
//                 critical: 'danger',
//                 success: 'success',
//                 danger: 'danger',
//                 warning: 'warning',
//                 error: 'danger',
//                 info: 'primary'
//             };
//             return dictionary[type] || 'primary';
//         }

//         function resolveIcon(type) {
//             var dictionary = {
//                 critical: 'fas fa-exclamation-triangle',
//                 success: 'fas fa-check',
//                 danger: 'fas fa-exclamation-triangle',
//                 error: 'fas fa-times',
//                 warning: 'fas fa-exclamation',
//                 info: 'fas fa-info-circle'
//             };
//             return dictionary[type] || 'fas fa-bell';
//         }

//         function formatDate(value) {
//             if (!value) {
//                 return '';
//             }
//             try {
//                 return new Intl.DateTimeFormat('fr-FR', {
//                     hour: '2-digit',
//                     minute: '2-digit',
//                     day: '2-digit',
//                     month: 'short'
//                 }).format(new Date(value));
//             } catch (error) {
//                 return value;
//             }
//         }

//         if (markAllBtn) {
//             markAllBtn.addEventListener('click', function (event) {
//                 event.preventDefault();
//                 var unreadIds = notifications
//                     .filter(function (notification) { return !notification.read; })
//                     .map(function (notification) { return notification.id; });
//                 if (!unreadIds.length) {
//                     return;
//                 }
//                 notifications = notifications.map(function (notification) {
//                     notification.read = true;
//                     return notification;
//                 });
//                 render();
//                 markAsRead(unreadIds, true);
//             });
//         }

//         window.addEventListener('beforeunload', function () {
//             closeStream();
//             stopPolling();
//         });

//         fetchInitial();
//         openStream();
//         startPolling();
//     });
// })();
