<script setup>
import { reactive, computed, watch, toRaw } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
    visible: { type: Boolean, default: false }
});

const emit = defineEmits(['create', 'hide', 'update:visible']);

const form = reactive({
    beginAt: null,
    endAt: null,
    title: '',
    description: ''
});

const errors = reactive({
    beginAt: false,
    endAt: false,
    title: false
});

watch(
    () => props.visible,
    (v) => {
        if (!v) {
            Object.assign(form, {
                beginAt: null,
                endAt: null,
                title: '',
                description: ''
            });
            Object.keys(errors).forEach((k) => (errors[k] = false));
        }
    }
);

const isValid = computed(() => {
    errors.beginAt = !form.beginAt;
    errors.endAt = !form.endAt;
    errors.title = !form.title.trim();
    return !Object.values(errors).some(Boolean);
});

function onSubmit() {
    if (!isValid.value) return;

    emit(
        'create',
        toRaw({
            beginAt: form.beginAt,
            endAt: form.endAt,
            title: form.title,
            description: form.description
        })
    );
}

const onHide = () => {
    emit('hide');
    emit('update:visible', false);
};
</script>
<template>
    <AppDialog
        :visible="visible"
        title="Ajouter un événement"
        icon="pi pi-calendar-plus"
        icon-tone="info"
        size="lg"
        cancel-label="Fermer"
        confirm-label="Enregistrer"
        @update:visible="(val) => !val && onHide()"
        @cancel="onHide"
        @confirm="onSubmit"
        @hide="onHide"
    >
        <form @submit.prevent="onSubmit" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Date de début <span class="text-red-500">*</span> </label>
                    <DatePicker
                        v-model="form.beginAt"
                        showTime
                        hourFormat="24"
                        class="w-full"
                        :inputClass="[
                            'w-full rounded-lg px-3 py-2 border focus:ring-2 focus:outline-none',
                            errors.beginAt ? 'border-red-500 focus:ring-red-400' : 'border-surface-300 focus:ring-primary-500 dark:border-surface-600',
                            'dark:bg-surface-800 dark:text-white'
                        ]"
                    />
                    <p v-if="errors.beginAt" class="text-xs text-red-500">Date de début obligatoire</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Date de fin <span class="text-red-500">*</span> </label>
                    <DatePicker
                        v-model="form.endAt"
                        showTime
                        hourFormat="24"
                        class="w-full"
                        :inputClass="[
                            'w-full rounded-lg px-3 py-2 border focus:ring-2 focus:outline-none',
                            errors.endAt ? 'border-red-500 focus:ring-red-400' : 'border-surface-300 focus:ring-primary-500 dark:border-surface-600',
                            'dark:bg-surface-800 dark:text-white'
                        ]"
                    />
                    <p v-if="errors.endAt" class="text-xs text-red-500">Date de fin obligatoire</p>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Titre <span class="text-red-500">*</span> </label>
                <input
                    v-model="form.title"
                    type="text"
                    class="w-full rounded-lg px-3 py-2 border focus:outline-none focus:ring-2 dark:bg-surface-800 dark:text-white"
                    :class="errors.title ? 'border-red-500 focus:ring-red-400' : 'border-surface-300 focus:ring-primary-500 dark:border-surface-600'"
                />
                <p v-if="errors.title" class="text-xs text-red-500">Le titre est obligatoire</p>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Description </label>
                <textarea v-model="form.description" rows="4" class="w-full rounded-lg px-3 py-2 border focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:bg-surface-800 dark:text-white dark:border-surface-600" />
            </div>
        </form>
    </AppDialog>
</template>
