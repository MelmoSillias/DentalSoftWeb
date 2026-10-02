<script setup>
import Button from 'primevue/button';
import Toast from 'primevue/toast';

/**
 * Official PrimeVue Toast `breakpoints` API:
 * injects @media rules with !important for width on small viewports.
 * Position (top/left/right) is handled in `_toast.scss` to clear the topbar.
 * @see https://primevue.org/toast/
 */
const toastBreakpoints = {
    '991.98px': {
        width: 'min(24rem, calc(100vw - 1.5rem))'
    },
    '640px': {
        width: 'calc(100vw - 1rem)'
    }
};

function onToastMouseEnter() {}

function onToastMouseLeave() {}
</script>

<template>
    <Toast
        position="top-right"
        class="app-toast"
        :breakpoints="toastBreakpoints"
        :onMouseEnter="onToastMouseEnter"
        :onMouseLeave="onToastMouseLeave"
    >
        <template #message="slotProps">
            <div class="p-toast-message-text app-toast__body">
                <span class="p-toast-summary">{{ slotProps.message.summary }}</span>
                <div v-if="slotProps.message.detail" class="p-toast-detail">{{ slotProps.message.detail }}</div>
                <div v-if="slotProps.message.data?.actionLabel" class="app-toast__action">
                    <Button
                        size="small"
                        severity="primary"
                        icon="pi pi-print"
                        class="app-toast__action-btn"
                        :label="slotProps.message.data.actionLabel"
                        @click="
                            () => {
                                slotProps.message.data.action?.();
                                if (typeof slotProps.closeCallback === 'function') {
                                    slotProps.closeCallback();
                                } else if (typeof slotProps.close === 'function') {
                                    slotProps.close();
                                }
                            }
                        "
                    />
                </div>
            </div>
        </template>
    </Toast>
</template>
