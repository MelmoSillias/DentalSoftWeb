import { defineStore } from 'pinia';

export const useRealtimeStore = defineStore('realtime', {
    state: () => ({
        connectionState: 'disconnected',
        lastConnectedAt: null,
        lastErrorAt: null
    }),

    getters: {
        isConnected: (state) => state.connectionState === 'connected',
        isConnecting: (state) => state.connectionState === 'connecting',
        hasConnectionIssue: (state) => ['disconnected', 'error'].includes(state.connectionState)
    },

    actions: {
        setConnectionState(state) {
            this.connectionState = state;

            if (state === 'connected') {
                this.lastConnectedAt = new Date().toISOString();
                this.lastErrorAt = null;
            }

            if (state === 'error') {
                this.lastErrorAt = new Date().toISOString();
            }
        },

        reset() {
            this.connectionState = 'disconnected';
            this.lastConnectedAt = null;
            this.lastErrorAt = null;
        }
    }
});
