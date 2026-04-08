<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import AppLayout from '../../layout/AppLayout.vue';

const authStore = useAuthStore();
const user = computed(() => authStore.user || { name: 'Patient', email: '-' });
</script>

<template>
    <AppLayout>
        <PvCard>
            <template #title>Mon profil</template>
            <template #content>
                <div class="profile-head">
                    <PvAvatar icon="pi pi-user" shape="circle" size="xlarge" />
                    <div>
                        <p><strong>{{ user.name }}</strong></p>
                        <p class="muted">{{ user.email }}</p>
                    </div>
                </div>
            </template>
        </PvCard>
    </AppLayout>
</template>

<style scoped>
.profile-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.profile-head p {
    margin: 0;
}
</style>
