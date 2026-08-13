<script setup>
import { reactive, computed, watch, toRaw } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'

const props = defineProps({
  visible: { type: Boolean, default: false }
})

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [
	{ label: 'Agenda' },
	{ label: 'Evenements', class: 'font-semibold' }
];

const emit = defineEmits(['create', 'hide'])

const form = reactive({
  beginAt: null,
  endAt: null,
  title: '',
  description: ''
})

const errors = reactive({
  beginAt: false,
  endAt: false,
  title: false
})

watch(() => props.visible, (v) => {
  if (!v) {
    Object.assign(form, {
      beginAt: null,
      endAt: null,
      title: '',
      description: ''
    })
    Object.keys(errors).forEach(k => errors[k] = false)
  }
})

const isValid = computed(() => {
  errors.beginAt = !form.beginAt
  errors.endAt = !form.endAt
  errors.title = !form.title.trim()
  return !Object.values(errors).some(Boolean)
})

function onSubmit() {
  if (!isValid.value) return

  emit('create', toRaw({
    beginAt: form.beginAt,
    endAt: form.endAt,
    title: form.title,
    description: form.description
  }))
}
</script>
<template>
  <Dialog
    :visible="visible"
    modal
    closable
    header="Ajouter un Événement"
    class="w-full max-w-2xl"
    contentClass="animate-scalein"
    @hide="$emit('hide')"
  >
    <form @submit.prevent="onSubmit" class="space-y-6">

      <!-- Dates -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Début -->
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Date de début <span class="text-red-500">*</span>
          </label>
          <DatePicker
            v-model="form.beginAt"
            showTime
            hourFormat="24"
            class="w-full"
            :inputClass="[
              'w-full rounded-lg px-3 py-2 border focus:ring-2 focus:outline-none',
              errors.beginAt
                ? 'border-red-500 focus:ring-red-400'
                : 'border-gray-300 focus:ring-blue-500 dark:border-gray-600',
              'dark:bg-gray-800 dark:text-white'
            ]"
          />
          <p v-if="errors.beginAt" class="text-xs text-red-500">
            Date de début obligatoire
          </p>
        </div>

        <!-- Fin -->
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Date de fin <span class="text-red-500">*</span>
          </label>
          <DatePicker
            v-model="form.endAt"
            showTime
            hourFormat="24"
            class="w-full"
            :inputClass="[
              'w-full rounded-lg px-3 py-2 border focus:ring-2 focus:outline-none',
              errors.endAt
                ? 'border-red-500 focus:ring-red-400'
                : 'border-gray-300 focus:ring-blue-500 dark:border-gray-600',
              'dark:bg-gray-800 dark:text-white'
            ]"
          />
          <p v-if="errors.endAt" class="text-xs text-red-500">
            Date de fin obligatoire
          </p>
        </div>
      </div>

      <!-- Titre -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Titre <span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.title"
          type="text"
          class="w-full rounded-lg px-3 py-2 border
                 focus:outline-none focus:ring-2
                 dark:bg-gray-800 dark:text-white
                 "
          :class="errors.title
            ? 'border-red-500 focus:ring-red-400'
            : 'border-gray-300 focus:ring-blue-500 dark:border-gray-600'"
        />
        <p v-if="errors.title" class="text-xs text-red-500">
          Le titre est obligatoire
        </p>
      </div>

      <!-- Description -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Description
        </label>
        <textarea
          v-model="form.description"
          rows="4"
          class="w-full rounded-lg px-3 py-2 border
                 focus:outline-none focus:ring-2 focus:ring-blue-500
                 resize-none
                 dark:bg-gray-800 dark:text-white dark:border-gray-600"
        />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700">
        <Button
          label="Fermer"
          class="p-button-text"
          type="button"
          @click="$emit('hide')"
        />
        <Button
          label="Enregistrer"
          icon="pi pi-check"
          type="submit"
        />
      </div>

    </form>
  </Dialog>
</template>

