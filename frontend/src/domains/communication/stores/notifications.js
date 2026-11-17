import { defineStore } from 'pinia';

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        notifications: []
    }),

    getters: {
        unreadCount: (state) => state.notifications.filter((item) => item.status === 'non_vu').length
    },

    actions: {
        setNotifications(items = []) {
            this.notifications = Array.isArray(items) ? items : [];
        },

        addNotification(notification) {
            if (!notification?.id) return;

            const index = this.notifications.findIndex((item) => item.id === notification.id);
            if (index >= 0) {
                this.notifications[index] = { ...this.notifications[index], ...notification };
                return;
            }

            this.notifications.unshift(notification);
            this.notifications = this.notifications.slice(0, 50);
        },

        markAsRead(ids = []) {
            if (!Array.isArray(ids) || !ids.length) return;
            const bucket = new Set(ids.map((id) => String(id)));
            this.notifications = this.notifications.map((item) => (bucket.has(String(item.id)) ? { ...item, status: 'vu' } : item));
        },

        markAllAsRead() {
            this.notifications = this.notifications.map((item) => ({ ...item, status: 'vu' }));
        }
    }
});
