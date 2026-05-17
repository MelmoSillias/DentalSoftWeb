<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const items = [
    { label: 'Dashboard', icon: 'pi pi-home', to: '/dashboard' },
    { label: 'Consultations', icon: 'pi pi-file', to: '/consultations' },
    { label: 'Rendez-vous', icon: 'pi pi-calendar', to: '/rendez-vous' },
    { label: 'Paiements', icon: 'pi pi-wallet', to: '/paiements' },
    { label: 'Documents', icon: 'pi pi-folder', to: '/documents' }
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
            <span class="nav-pill">
                <i :class="item.icon" />
            </span>
            <span class="nav-label">{{ item.label }}</span>
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
    height: var(--pp-nav-h);
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    padding: 0.3rem 0.25rem;
    gap: 0.1rem;
    box-shadow: 0 -2px 16px rgba(0,0,0,0.06);
}

.nav-btn {
    border: 0;
    background: transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    padding: 0.25rem;
    cursor: pointer;
    border-radius: 12px;
    transition: background 0.15s ease;
}

.nav-btn:hover {
    background: rgba(0,0,0,0.04);
}

.nav-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.4rem;
    height: 1.6rem;
    border-radius: 999px;
    transition: background 0.2s ease, transform 0.15s ease;
}

.nav-btn i {
    font-size: 1rem;
    color: var(--p-text-muted-color);
    transition: color 0.2s ease;
}

.nav-label {
    font-size: 0.66rem;
    color: var(--p-text-muted-color);
    transition: color 0.2s ease;
    font-weight: 500;
    line-height: 1;
}

/* Active state */
.nav-btn.active .nav-pill {
    background: #eff6ff;
    transform: translateY(-1px);
}

.nav-btn.active .nav-pill i {
    color: #1d4ed8;
    font-size: 1.05rem;
}

.nav-btn.active .nav-label {
    color: #1d4ed8;
    font-weight: 700;
}
</style>
