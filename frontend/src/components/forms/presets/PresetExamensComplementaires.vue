<script setup>
import PresetTemplateBlock from '@/components/forms/presets/PresetTemplateBlock.vue';
</script>

<template>
    <PresetTemplateBlock
        title="Examens complementaires"
        icon="pi pi-plus-circle"
        description="Section reutilisable pour bacterio, serologie, histologie."
        :bullets="['Bacteriologie', 'Serologie', 'Histologie']"
    />
</template>
