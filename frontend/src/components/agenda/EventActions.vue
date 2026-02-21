<template>
  <div>
    <Dialog header="Actions" :visible="visible" :modal="false" :closable="true" @hide="$emit('hide')">
      <div class="p-d-flex p-flex-column">
        <Button label="Valider" icon="pi pi-check" class="p-button-success p-mb-2" @click="onValidate" />
        <Button label="Supprimer" icon="pi pi-trash" class="p-button-danger" @click="onDelete" />
      </div>
    </Dialog>

    <Dialog header="Confirmer la validation" :visible="showValidateConfirm" :modal="true" @hide="showValidateConfirm=false">
      <p>Voulez-vous valider cet événement ? Il sera marqué comme "Confirmé".</p>
      <div class="p-d-flex p-jc-end p-mt-3">
        <Button label="Annuler" class="p-button-secondary p-mr-2" @click="showValidateConfirm=false" />
        <Button label="Valider" class="p-button-success" @click="confirmValidate" />
      </div>
    </Dialog>

    <Dialog header="Confirmer la suppression" :visible="showDeleteConfirm" :modal="true" @hide="showDeleteConfirm=false">
      <p>Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.</p>
      <div class="p-d-flex p-jc-end p-mt-3">
        <Button label="Annuler" class="p-button-secondary p-mr-2" @click="showDeleteConfirm=false" />
        <Button label="Supprimer" class="p-button-danger" @click="confirmDelete" />
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({ visible: Boolean, eventId: [String, Number] })
const emit = defineEmits(['delete', 'validate', 'hide'])

const showDeleteConfirm = ref(false)
const showValidateConfirm = ref(false)

function onDelete() { showDeleteConfirm.value = true }
function onValidate() { showValidateConfirm.value = true }

function confirmDelete() {
  showDeleteConfirm.value = false
  emit('delete', props.eventId)
}

function confirmValidate() {
  showValidateConfirm.value = false
  emit('validate', props.eventId)
}
</script>

<style scoped>
.p-dialog .p-dialog-title { font-weight: 700 }
</style>
