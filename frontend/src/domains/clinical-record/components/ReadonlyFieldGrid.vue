<script setup>
defineProps({
    fields: {
        type: Array,
        default: () => []
    },
    columns: {
        type: Number,
        default: 2
    }
});

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
    return String(value);
};
</script>

<template>
    <div
        class="medical-form-fields"
        :class="{
            'medical-form-fields--1': columns === 1,
            'medical-form-fields--2': columns === 2,
            'medical-form-fields--3': columns === 3
        }"
    >
        <div v-for="field in fields" :key="field.label" class="medical-form-row">
            <div class="medical-form-row__label">{{ field.label }}</div>
            <div class="medical-form-row__value">{{ formatValue(field.value) }}</div>
        </div>
    </div>
</template>

<style scoped>
.medical-form-fields {
    display: grid;
    gap: 0;
}

.medical-form-fields--1 {
    grid-template-columns: 1fr;
}

@media (min-width: 768px) {
    .medical-form-fields--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.25rem;
    }

    .medical-form-fields--3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.25rem;
    }
}

@media (min-width: 1024px) {
    .medical-form-fields--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>
