<script setup>
import Dialog from 'primevue/dialog';
import { computed, useSlots } from 'vue';
import DialogFooter from '@/components/layout/DialogFooter.vue';
import DialogHeader from '@/components/layout/DialogHeader.vue';

defineOptions({
    inheritAttrs: false
});

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: null
    },
    icon: {
        type: String,
        default: null
    },
    iconTone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral'].includes(value)
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
    },
    width: {
        type: String,
        default: null
    },
    maximizable: {
        type: Boolean,
        default: false
    },
    closable: {
        type: Boolean,
        default: true
    },
    dismissableMask: {
        type: Boolean,
        default: true
    },
    draggable: {
        type: Boolean,
        default: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    confirmDisabled: {
        type: Boolean,
        default: false
    },
    cancelLabel: {
        type: String,
        default: 'Annuler'
    },
    confirmLabel: {
        type: String,
        default: null
    },
    confirmIcon: {
        type: String,
        default: 'pi pi-check'
    },
    confirmSeverity: {
        type: String,
        default: 'primary'
    },
    showFooter: {
        type: Boolean,
        default: undefined
    },
    contentClass: {
        type: String,
        default: ''
    },
    contentPadding: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:visible', 'cancel', 'confirm', 'hide']);

const slots = useSlots();

const sizeStyles = {
    sm: { width: 'min(30rem, 95vw)' },
    md: { width: 'min(40rem, 95vw)' },
    lg: { width: 'min(50rem, 95vw)' },
    xl: { width: 'min(60rem, 95vw)' },
    full: { width: 'min(96vw, 1100px)' }
};

const dialogStyle = computed(() => {
    if (props.width) {
        return { width: props.width };
    }
    return sizeStyles[props.size] || sizeStyles.md;
});

const hasDefaultFooterActions = computed(() => Boolean(props.confirmLabel || props.cancelLabel));

const shouldShowFooter = computed(() => {
    if (props.showFooter === false) return false;
    if (props.showFooter === true) return true;
    return Boolean(slots.footer || slots.footerStart || props.confirmLabel);
});

const dialogPt = computed(() => ({
    root: 'rounded-2xl overflow-hidden',
    header:
        'px-6 py-4 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 border-b border-surface-200 dark:border-surface-700',
    content: [
        props.contentPadding ? 'px-6 py-4' : 'p-0',
        'bg-surface-0 dark:bg-surface-900',
        props.contentClass
    ]
        .filter(Boolean)
        .join(' '),
    footer: shouldShowFooter.value
        ? 'px-6 py-4 bg-surface-50 dark:bg-surface-800 border-t border-surface-200 dark:border-surface-700'
        : undefined
}));

const onUpdateVisible = (value) => {
    emit('update:visible', value);
};

const onHide = () => {
    emit('hide');
};

const onCancel = () => {
    emit('cancel');
    emit('update:visible', false);
};

const onConfirm = () => {
    emit('confirm');
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :maximizable="maximizable"
        :closable="closable"
        :dismissable-mask="dismissableMask"
        :draggable="draggable"
        :style="dialogStyle"
        :pt="dialogPt"
        v-bind="$attrs"
        @update:visible="onUpdateVisible"
        @hide="onHide"
    >
        <template #header>
            <slot name="header">
                <DialogHeader :title="title" :subtitle="subtitle" :icon="icon" :icon-tone="iconTone">
                    <template v-if="$slots.headerExtra" #extra>
                        <slot name="headerExtra" />
                    </template>
                </DialogHeader>
            </slot>
        </template>

        <slot />

        <template v-if="shouldShowFooter" #footer>
            <slot name="footer">
                <DialogFooter
                    :cancel-label="cancelLabel"
                    :confirm-label="confirmLabel"
                    :confirm-icon="confirmIcon"
                    :confirm-severity="confirmSeverity"
                    :loading="loading"
                    :confirm-disabled="confirmDisabled"
                    :show-cancel="hasDefaultFooterActions"
                    :show-confirm="Boolean(confirmLabel)"
                    @cancel="onCancel"
                    @confirm="onConfirm"
                >
                    <template v-if="$slots.footerStart" #start>
                        <slot name="footerStart" />
                    </template>
                </DialogFooter>
            </slot>
        </template>
    </Dialog>
</template>
