<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTheme } from '../composables/useTheme';
import AppHeader from './AppHeader.vue';
import AppBottomNav from './AppBottomNav.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { isDark, initTheme, toggleTheme } = useTheme();

onMounted(() => {
    initTheme();
});

function logout() {
    authStore.logout();
    router.push('/login');
}
</script>

<template>
    <div class="app-shell bg-blue-500 min-h-screen flex flex-row">
        <AppHeader :dark="isDark" @toggle-theme="toggleTheme" />

        <main class="app-content">
            <slot />
            <Divider />
            <Button
                v-if="route.path !== '/login'"
                severity="secondary"
                label="Se déconnecter"
                icon="pi pi-sign-out"
                fluid
                outlined
                @click="logout"
            />
        </main>

        <AppBottomNav v-if="route.path !== '/login'" />
    </div>
</template>
