<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const items = [
    { label: 'Dashboard', icon: 'pi pi-home', to: '/dashboard' },
    { label: 'Consultations', icon: 'pi pi-file', to: '/consultations' },
    { label: 'Rendez-vous', icon: 'pi pi-calendar', to: '/rendez-vous' },
    { label: 'Profil', icon: 'pi pi-user', to: '/profil' }
];

const currentPath = computed(() => route.path);

function go(path) {
    router.push(path);
}
</script>

<template>
    <nav class="bottom-nav" aria-label="Navigation principale">
        <button
            v-for="item in items"
            :key="item.to"
            class="nav-btn"
            :class="{ active: currentPath === item.to }"
            type="button"
            @click="go(item.to)"
        >
            <i :class="item.icon" />
            <span>{{ item.label }}</span>
        </button>
    </nav>
</template>

<style scoped>
.bottom-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 30;
    height: 4.5rem;
    border-top: 1px solid var(--p-surface-200);
    background: var(--p-surface-0);
    display: grid;
    grid-template-columns: repeat(4, 1fr);
}

.nav-btn {
    border: 0;
    background: transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    color: var(--p-text-muted-color);
    font-size: 0.72rem;
}

.nav-btn i {
    font-size: 1rem;
}

.nav-btn.active {
    color: var(--p-primary-color);
    font-weight: 600;
}
</style>
