<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import BrandMark from '../components/BrandMark.vue';
defineProps({ dark: Boolean });
defineEmits(['toggle-theme']);
const route = useRoute();
const auth = useAuthStore();
const initials = computed(() =>
    (auth.user?.name || 'P').slice(0, 2).toUpperCase(),
);
const today = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(new Date());
</script>
<template>
    <header class="top-header">
        <RouterLink to="/dashboard" class="mobile-brand" aria-label="Accueil"
            ><BrandMark
        /></RouterLink>
        <div class="header-context">
            <span class="eyebrow">VOTRE ESPACE PERSONNEL</span
            ><span class="header-page">{{ route.meta.title }}</span>
        </div>
        <div class="header-actions">
            <span class="header-date"
                ><i class="pi pi-calendar" aria-hidden="true" />{{
                    today
                }}</span
            ><button
                class="icon-button"
                type="button"
                :aria-label="
                    dark ? 'Activer le thème clair' : 'Activer le thème sombre'
                "
                @click="$emit('toggle-theme')"
            >
                <i
                    :class="dark ? 'pi pi-sun' : 'pi pi-moon'"
                    aria-hidden="true"
                /></button
            ><RouterLink
                to="/profil"
                class="avatar-trigger"
                aria-label="Mon profil"
                >{{ initials }}</RouterLink
            >
        </div>
    </header>
</template>
