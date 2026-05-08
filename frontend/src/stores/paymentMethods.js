import { defineStore } from 'pinia';
import { fetchPaymentMethods } from '@/services/caisseService';

const DEFAULT_TTL_MS = 5 * 60 * 1000;

export const usePaymentMethodsStore = defineStore('paymentMethods', {
    state: () => ({
        items: [],
        loading: false,
        error: null,
        lastLoadedAt: 0,
        ttlMs: DEFAULT_TTL_MS
    }),

    getters: {
        isFresh: (state) => Date.now() - Number(state.lastLoadedAt || 0) < Number(state.ttlMs || DEFAULT_TTL_MS)
    },

    actions: {
        setItems(items = []) {
            this.items = Array.isArray(items) ? items : [];
            this.lastLoadedAt = Date.now();
            this.error = null;
        },

        invalidate({ clearItems = false } = {}) {
            this.lastLoadedAt = 0;
            if (clearItems) {
                this.items = [];
            }
        },

        async load(token, { force = false } = {}) {
            if (!force && this.items.length > 0 && this.isFresh) {
                return this.items;
            }

            this.loading = true;
            this.error = null;
            try {
                const data = await fetchPaymentMethods(token);
                this.setItems(data);
                return this.items;
            } catch (error) {
                this.error = error;
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
});
