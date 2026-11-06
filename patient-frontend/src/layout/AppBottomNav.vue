<script setup>
const items = [
    { label: 'Accueil', icon: 'pi pi-home', to: '/dashboard' },
    { label: 'Consultations', icon: 'pi pi-file-edit', to: '/consultations' },
    { label: 'Rendez-vous', icon: 'pi pi-calendar', to: '/rendez-vous' },
    { label: 'Paiements', icon: 'pi pi-wallet', to: '/paiements' },
    { label: 'Documents', icon: 'pi pi-folder-open', to: '/documents' },
];
</script>
<template>
    <nav class="bottom-nav" aria-label="Navigation mobile">
        <RouterLink
            v-for="item in items"
            :key="item.to"
            :to="item.to"
            class="nav-btn"
            active-class="active"
            ><span class="nav-pill"
                ><i :class="item.icon" aria-hidden="true" /></span
            ><span class="nav-label">{{ item.label }}</span></RouterLink
        >
    </nav>
</template>
