<script setup>
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import AppConfigurator from '@/layout/AppConfigurator.vue';
</script>

<template>
    <PageShell class="appearance-settings-page">
        <template #header>
            <PageHeader title="Apparence" subtitle="Personnalisez l’affichage de votre compte sur cet appareil." icon="pi pi-palette" />
        </template>
        <section class="rounded-2xl border border-surface bg-surface-0 p-5 dark:bg-surface-900 md:p-7">
            <AppConfigurator embedded />
        </section>
    </PageShell>
</template>
