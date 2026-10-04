<script setup>
import { ROLE_LABELS } from '../useManualAudience';

defineProps({
    roles: { type: Array, default: () => [] }
});
</script>

<template>
    <span v-if="roles.length" class="m-roles" aria-label="Profils concernés">
        <span v-for="role in roles" :key="role" class="m-role" :class="`m-role--${role}`">{{ ROLE_LABELS[role] ?? role }}</span>
    </span>
</template>
