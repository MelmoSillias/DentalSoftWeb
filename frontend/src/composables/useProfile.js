import { computed, ref } from 'vue';
import { apiPrefix } from '@/config';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';

const buildAuthHeaders = (token, isJson = true) => {
    const headers = {};
    if (isJson) headers['Content-Type'] = 'application/json';
    if (token) headers['Authorization'] = `Bearer ${token}`;
    return headers;
};

export function useProfile() {
    const auth = useAuthStore();
    const profile = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const notifications = computed(() => profile.value?.notifications || []);
    const activity = computed(() => profile.value?.activity || []);
    const stats = computed(() => profile.value?.stats || {});
    const user = computed(() => profile.value?.user || {});
    const employee = computed(() => profile.value?.employee || null);
    const unreadCount = computed(() => profile.value?.notificationsUnreadCount || 0);

    const fetchProfile = async () => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/me`, {
                headers: buildAuthHeaders(auth.token)
            });
            profile.value = res.data;
            if (auth.user && profile.value?.user) {
                auth.user = { ...auth.user, ...profile.value.user };
            }
            return profile.value;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateProfile = async (payload) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.put(`${apiPrefix}/me`, payload, {
                headers: buildAuthHeaders(auth.token)
            });
            const data = res.data;
            await fetchProfile();
            return data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const changePassword = async (payload) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.patch(`${apiPrefix}/me/change-password`, payload, {
                headers: buildAuthHeaders(auth.token)
            });
            return res.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchNotifications = async (filter = 'all') => {
        const res = await http.get(`${apiPrefix}/me/notifications?filter=${filter}`, {
            headers: buildAuthHeaders(auth.token, false)
        });
        const data = res.data;
        profile.value = {
            ...profile.value,
            notifications: data.items || []
        };
        return data.items || [];
    };

    const markNotificationsRead = async (ids = []) => {
        const res = await http.post(
            `${apiPrefix}/me/notifications/mark-read`,
            { ids },
            { headers: buildAuthHeaders(auth.token) }
        );
        const data = res.data;
        await fetchProfile();
        return data;
    };

    const markAllNotificationsRead = async () => {
        const res = await http.post(
            `${apiPrefix}/me/notifications/mark-all`,
            {},
            { headers: buildAuthHeaders(auth.token, false) }
        );
        const data = res.data;
        await fetchProfile();
        return data;
    };

    const setNotificationsEnabled = async (enabled) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.put(
                `${apiPrefix}/me`,
                { notificationsEnabled: Boolean(enabled) },
                { headers: buildAuthHeaders(auth.token) }
            );
            await fetchProfile();
            if (auth.user) {
                auth.user = { ...auth.user, notificationsEnabled: Boolean(enabled) };
            }
            return res.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        profile,
        user,
        employee,
        notifications,
        activity,
        stats,
        unreadCount,
        loading,
        error,
        fetchProfile,
        updateProfile,
        changePassword,
        fetchNotifications,
        markNotificationsRead,
        markAllNotificationsRead,
        setNotificationsEnabled
    };
}
