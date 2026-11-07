<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useMutationStatus } from '@/composables/useMutationStatus';
import { useToast } from 'primevue/usetoast';

const { pending, issue } = useMutationStatus();
const toast = useToast();
watch(issue, (value) => {
    if (value) toast.add({ severity: 'warn', summary: 'Confirmation incertaine', detail: value.message, life: 12000 });
});
const slow = ref(false);
const panel = ref(null);
const active = computed(() => pending.value > 0);
let timer;
let previousFocus;
watch(
    active,
    (value) => {
        clearTimeout(timer);
        slow.value = false;
        if (value) {
            previousFocus = document.activeElement;
            nextTick(() => panel.value?.focus());
            timer = setTimeout(() => {
                slow.value = true;
            }, 8000);
        } else if (previousFocus?.isConnected) {
            previousFocus.focus();
        }
    },
    { flush: 'sync' }
);

// Capture also protects teleported dialogs and submissions triggered with Enter.
const block = (event) => {
    if (!active.value) return;
    if (event.type === 'keydown' && !['Enter', ' ', 'Escape', 'Tab'].includes(event.key)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    panel.value?.focus();
};
const beforeUnload = (event) => {
    if (active.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};
onMounted(() => {
    for (const name of ['click', 'submit', 'keydown']) document.addEventListener(name, block, true);
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    for (const name of ['click', 'submit', 'keydown']) document.removeEventListener(name, block, true);
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="active" class="mutation-pending" role="dialog" aria-modal="true" aria-label="Enregistrement en cours">
            <div ref="panel" class="mutation-pending__panel" tabindex="-1" role="status" aria-live="polite" aria-busy="true">
                <i class="pi pi-spin pi-spinner" aria-hidden="true" />
                <strong>Enregistrement en cours…</strong>
                <p>{{ slow ? 'Le serveur met plus de temps à répondre. Veuillez patienter sans relancer la validation.' : 'Veuillez patienter pendant la validation de votre action.' }}</p>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.mutation-pending {
    position: fixed;
    inset: 0;
    z-index: 2147483647;
    display: grid;
    place-items: center;
    background: rgb(15 23 42 / 30%);
    cursor: wait;
    padding: 1.5rem;
}
.mutation-pending__panel {
    width: min(100%, 28rem);
    padding: 1.75rem;
    border-radius: 1rem;
    background: var(--p-content-background, white);
    color: var(--p-text-color, #0f172a);
    box-shadow: 0 16px 50px rgb(0 0 0 / 20%);
    text-align: center;
}
.mutation-pending__panel i {
    display: block;
    font-size: 1.75rem;
    margin-bottom: 1rem;
}
.mutation-pending__panel p {
    margin: 0.75rem 0 0;
    line-height: 1.5;
}
</style>
