<script setup>
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { ref } from 'vue';

const emit = defineEmits(['confirm']);
const confirm = useConfirm();
const target = ref(null);

function show(event) {
  target.value = event.currentTarget;
  confirm.require({
    target: target.value,
    message: 'Confirmer la suppression de cette salle ?',
    icon: 'pi pi-exclamation-triangle',
    accept: () => emit('confirm'),
    reject: () => {}
  });
}

defineExpose({ show });
</script>
<template>
  <ConfirmPopup />
</template>
