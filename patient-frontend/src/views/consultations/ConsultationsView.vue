<script setup>
import AppLayout from '../../layout/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <PvCard>
            <template #title>Consultations</template>
            <template #content>
                <p class="muted">Liste des consultations à brancher sur l'API patient.</p>
            </template>
        </PvCard>
    </AppLayout>
</template>
