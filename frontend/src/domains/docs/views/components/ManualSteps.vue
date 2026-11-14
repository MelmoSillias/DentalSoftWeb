<script setup>
defineProps({
    start: { type: Number, default: 1 }
});
</script>

<template>
    <ol class="m-steps" :start="start" :style="{ counterReset: `m-step ${start - 1}` }">
        <slot />
    </ol>
</template>
