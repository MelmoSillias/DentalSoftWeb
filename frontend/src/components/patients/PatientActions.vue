<script setup></script>

<template>
    <div class="patient-actions">
        <Button icon="pi pi-pencil" class="p-button-rounded p-button-info p-mr-2" @click="$emit('edit')" :aria-label="'Éditer le patient'" />
        <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="$emit('delete')" :aria-label="'Supprimer le patient'" />
    </div>
</template>

<style setup></style>
