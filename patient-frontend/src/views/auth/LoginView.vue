<script setup>
import { useRoute, useRouter } from 'vue-router';
import LoginForm from './components/LoginForm.vue';

const route = useRoute();
const router = useRouter();

function handleSuccess() {
    const redirect = route.query.redirect || '/dashboard';
    router.push(String(redirect));
}
</script>

<template>
    <main class="login-page">
        <section class="hero">
            <h1>Bienvenue dans votre espace patient</h1>
            <p class="muted">Consultez vos rendez-vous, consultations et paiements en toute simplicité.</p>
        </section>
        <LoginForm @success="handleSuccess" />
    </main>
</template>

<style scoped>
.login-page {
    min-height: 100dvh;
    display: grid;
    align-content: center;
    gap: 1rem;
    padding: 1rem;
    width: min(100%, 560px);
    margin: 0 auto;
}

.hero h1 {
    margin: 0;
    font-size: 1.45rem;
}

.hero p {
    margin: 0.4rem 0 0;
}
</style>
