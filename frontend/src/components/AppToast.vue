<script setup>
import Button from 'primevue/button';
import Toast from 'primevue/toast';
</script>

<template>
    <Toast>
        <template #message="slotProps">
            <div class="p-toast-message-text">
                <span class="p-toast-summary">{{ slotProps.message.summary }}</span>
                <div class="p-toast-detail">{{ slotProps.message.detail }}</div>
                <div v-if="slotProps.message.data?.actionLabel" class="mt-2">
                    <Button
                        size="small"
                        severity="primary"
                        icon="pi pi-print"
                        :label="slotProps.message.data.actionLabel"
                        @click="() => {
                            slotProps.message.data.action?.();
                            if (typeof slotProps.closeCallback === 'function') {
                                slotProps.closeCallback();
                            } else if (typeof slotProps.close === 'function') {
                                slotProps.close();
                            }
                        }"
                    />
                </div>
            </div>
        </template>
    </Toast>
</template>