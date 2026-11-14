<script setup>
defineProps({
    critical: { type: Boolean, default: false },
    result: { type: String, default: '' }
});
</script>

<template>
    <li class="m-step" :class="{ 'm-step--critical': critical }">
        <p v-if="critical" class="m-step__flag">Action critique</p>
        <div class="m-step__body"><slot /></div>
        <p v-if="result" class="m-step__result"><span>Résultat :</span> {{ result }}</p>
        <slot name="figure" />
    </li>
</template>
