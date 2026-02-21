import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['counter', 'list', 'empty'];

    static values = {
        endpoint: String,
        stream: String,
        readEndpoint: String,
        limit: { type: Number, default: 20 }
    };

    connect() {
        this.notifications = [];
        this.eventSource = null;
        this.lastEventId = null;
        this.reconnectDelay = 2000;

        this.fetchInitial();
        this.openStream();
    }

    disconnect() {
        this.closeStream();
    }

    fetchInitial() {
        if (!this.hasEndpointValue) {
            return;
        }

        fetch(this.endpointValue, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : []))
            .then((data) => {
                if (!Array.isArray(data)) {
                    return;
                }
                this.notifications = data;
                this.lastEventId = this.notifications.length ? this.notifications[0].id : null;
                this.render();
            })
            .catch(() => {});
    }

    openStream() {
        if (!this.hasStreamValue) {
            return;
        }

        const url = new URL(this.streamValue, window.location.origin);
        if (this.lastEventId) {
            url.searchParams.set('lastEventId', this.lastEventId);
        }

        this.closeStream();
        this.eventSource = new EventSource(url);
        this.eventSource.addEventListener('notification', (event) => this.handleNotification(event));
        this.eventSource.onerror = () => {
            this.closeStream();
            setTimeout(() => this.openStream(), this.reconnectDelay);
            this.reconnectDelay = Math.min(this.reconnectDelay * 2, 30000);
        };
        this.reconnectDelay = 2000;
    }

    closeStream() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    handleNotification(event) {
        let payload;
        try {
            payload = JSON.parse(event.data);
        } catch (error) {
            return;
        }

        payload.id = Number(payload.id);
        payload.read = Boolean(payload.read);
        payload.priority = payload.priority || 'info';
        this.lastEventId = Number(event.lastEventId || payload.id || this.lastEventId);

        this.notifications = [payload, ...this.notifications.filter((entry) => entry.id !== payload.id)];
        if (this.notifications.length > this.limitValue) {
            this.notifications.length = this.limitValue;
        }
        this.render();
    }

    render() {
        if (!this.hasListTarget) {
            return;
        }

        if (this.notifications.length === 0) {
            this.showEmptyState(true);
            this.listTarget.innerHTML = '';
            this.updateCounter(0);
            return;
        }

        this.showEmptyState(false);
        const fragment = document.createDocumentFragment();
        this.notifications.forEach((notification) => {
            fragment.appendChild(this.buildItem(notification));
        });
        this.listTarget.innerHTML = '';
        this.listTarget.appendChild(fragment);
        this.updateCounter(this.notifications.filter((notification) => !notification.read).length);
    }

    buildItem(notification) {
        const item = document.createElement('a');
        item.className = `dropdown-item d-flex align-items-center ${notification.read ? '' : 'bg-light'}`;
        item.href = notification.link || '#';
        item.dataset.notificationId = notification.id;

        const tone = notification.priority || notification.type;
        const iconCircle = document.createElement('div');
        iconCircle.className = `icon-circle bg-${this.resolveColor(tone)}`;
        const icon = document.createElement('i');
        icon.className = `${this.resolveIcon(tone)} text-white`;
        iconCircle.appendChild(icon);

        const iconWrapper = document.createElement('div');
        iconWrapper.className = 'mr-3';
        iconWrapper.appendChild(iconCircle);

        const textWrapper = document.createElement('div');
        textWrapper.innerHTML = `
            <div class="small text-gray-500">${this.formatDate(notification.date)}</div>
            <span class="font-weight-bold">${notification.message}</span>
        `;

        item.appendChild(iconWrapper);
        item.appendChild(textWrapper);

        item.addEventListener('click', (event) => {
            if (!notification.link) {
                event.preventDefault();
            }
            if (!notification.read) {
                this.markAsRead([notification.id]);
                notification.read = true;
                this.render();
            }
        });

        return item;
    }

    updateCounter(value) {
        if (!this.hasCounterTarget) {
            return;
        }

        this.counterTarget.textContent = value > 9 ? '9+' : value;
        this.counterTarget.style.display = value > 0 ? 'inline-block' : 'none';
    }

    showEmptyState(visible) {
        if (!this.hasEmptyTarget) {
            return;
        }
        this.emptyTarget.classList.toggle('d-none', !visible);
    }

    markAllRead(event) {
        if (event) {
            event.preventDefault();
        }

        const unreadIds = this.notifications.filter((notification) => !notification.read).map((notification) => notification.id);
        if (unreadIds.length === 0) {
            return;
        }

        this.notifications = this.notifications.map((notification) => ({ ...notification, read: true }));
        this.render();
        this.markAsRead(unreadIds, true);
    }

    markAsRead(ids, markAll = false) {
        if (!this.hasReadEndpointValue) {
            return;
        }

        const body = markAll ? { all: true } : { ids };

        fetch(this.readEndpointValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).catch(() => {});
    }

    resolveColor(type) {
        const dictionary = {
            critical: 'danger',
            success: 'success',
            danger: 'danger',
            warning: 'warning',
            error: 'danger',
            info: 'primary'
        };
        return dictionary[type] || 'primary';
    }

    resolveIcon(type) {
        const dictionary = {
            critical: 'fas fa-exclamation-triangle',
            success: 'fas fa-check',
            danger: 'fas fa-exclamation-triangle',
            error: 'fas fa-times',
            warning: 'fas fa-exclamation',
            info: 'fas fa-info-circle'
        };
        return dictionary[type] || 'fas fa-bell';
    }

    formatDate(value) {
        if (!value) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: 'short'
            }).format(new Date(value));
        } catch (error) {
            return value;
        }
    }
}
