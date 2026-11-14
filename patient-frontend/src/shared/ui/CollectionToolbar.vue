<script setup>
defineProps({
    search: String,
    filter: String,
    filters: { type: Array, default: () => [] },
    count: Number,
    placeholder: { type: String, default: 'Rechercher...' },
});
defineEmits(['update:search', 'update:filter']);
</script>
<template>
    <div class="collection-toolbar">
        <div
            v-if="filters.length"
            class="filter-tabs"
            role="group"
            aria-label="Filtrer les résultats"
        >
            <button
                v-for="item in filters"
                :key="item.value"
                type="button"
                :class="{ selected: filter === item.value }"
                :aria-pressed="filter === item.value"
                @click="$emit('update:filter', item.value)"
            >
                {{ item.label }}
            </button>
        </div>
        <label class="collection-search"
            ><i class="pi pi-search" aria-hidden="true" /><input
                :value="search"
                type="search"
                :placeholder="placeholder"
                :aria-label="placeholder"
                @input="$emit('update:search', $event.target.value)"
        /></label>
        <span v-if="count !== undefined" class="collection-count"
            >{{ count }} résultat{{ count > 1 ? 's' : '' }}</span
        >
    </div>
</template>
