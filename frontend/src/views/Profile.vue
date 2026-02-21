<script setup>
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useProfile } from '@/composables/useProfile';
import ProfileStatsSection from '@/components/profile/ProfileStatsSection.vue';
import ProfileInfoSection from '@/components/profile/ProfileInfoSection.vue';
import ProfileActivitySection from '@/components/profile/ProfileActivitySection.vue';
import ProfileNotificationsSection from '@/components/profile/ProfileNotificationsSection.vue';
import ProfileQuickLinksSection from '@/components/profile/ProfileQuickLinksSection.vue';

const toast = useToast();
const notificationsFilter = ref('all');

const {
	user,
	employee,
	notifications,
	activity,
	stats,
	unreadCount,
	loading,
	fetchProfile,
	updateProfile,
	changePassword,
	fetchNotifications,
	markNotificationsRead,
	markAllNotificationsRead
} = useProfile();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Profil' }];

const quickLinks = computed(() => {
	const roles = user.value?.roles || [];
	if (roles.includes('ROLE_ADMIN')) {
		return [
			{ label: 'Dashboard', icon: 'pi pi-chart-bar', to: '/dashboard' },
			{ label: 'Patients', icon: 'pi pi-users', to: '/patients/liste' },
			{ label: 'Agenda', icon: 'pi pi-calendar', to: '/agenda/rendez-vous' },
			{ label: 'Finances', icon: 'pi pi-wallet', to: '/administration/finances' }
		];
	}
	if (roles.includes('ROLE_MEDECIN')) {
		return [
			{ label: 'Agenda', icon: 'pi pi-calendar', to: '/agenda/rendez-vous' },
			{ label: 'Consultations', icon: 'pi pi-briefcase', to: '/consultations/cards' },
			{ label: 'Patients', icon: 'pi pi-users', to: '/patients/liste' }
		];
	}
	if (roles.includes('ROLE_RECEPTION')) {
		return [
			{ label: 'Agenda', icon: 'pi pi-calendar', to: '/agenda/rendez-vous' },
			{ label: 'Patients', icon: 'pi pi-users', to: '/patients/liste' },
			{ label: 'Caisse', icon: 'pi pi-inbox', to: '/caisse' }
		];
	}
	return [];
});

const handleSaveInfo = async (payload) => {
	try {
		await updateProfile(payload);
		toast.add({ severity: 'success', summary: 'Profil mis à jour', life: 2500 });
	} catch (err) {
		toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour le profil.' });
	}
};

const handleChangePassword = async (payload) => {
	try {
		await changePassword({ oldPassword: payload.oldPassword, newPassword: payload.newPassword });
		toast.add({ severity: 'success', summary: 'Mot de passe mis à jour', life: 2500 });
	} catch (err) {
		toast.add({ severity: 'error', summary: 'Erreur', detail: 'Mot de passe incorrect ou invalide.' });
	}
};

const handleFilterChange = async (filter) => {
	notificationsFilter.value = filter;
	try {
		await fetchNotifications(filter);
	} catch (err) {
		toast.add({ severity: 'warn', summary: 'Notifications', detail: 'Impossible de charger les notifications.' });
	}
};

const handleMarkRead = async (ids) => {
	try {
		await markNotificationsRead(ids);
		toast.add({ severity: 'success', summary: 'Notification mise à jour', life: 2000 });
	} catch (err) {
		toast.add({ severity: 'error', summary: 'Erreur', detail: 'Marquage impossible.' });
	}
};

const handleMarkAll = async () => {
	try {
		await markAllNotificationsRead();
		toast.add({ severity: 'success', summary: 'Toutes marquées', life: 2000 });
	} catch (err) {
		toast.add({ severity: 'error', summary: 'Erreur', detail: 'Marquage impossible.' });
	}
};

onMounted(async () => {
	await fetchProfile();
});
</script>

<template>
	<section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
		<Toast />

		<div class="mb-6 md:mb-8">
			<div class="mb-6">
				<div class="inline-flex items-center gap-3 mb-4 p-3 rounded-2xl bg-surface-0/80 dark:bg-surface-800/80 backdrop-blur-sm border border-surface-200/50 dark:border-surface-700/50">
					<div class="p-2.5 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600">
						<i class="pi pi-user text-white text-xl"></i>
					</div>
					<div>
						<h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-50">Profil</h1>
						<p class="text-sm text-surface-600 dark:text-surface-300">Votre espace personnel et vos notifications.</p>
					</div>
				</div>
				<Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
			</div>
		</div>

		<ProfileStatsSection :stats="stats" :unread-count="unreadCount" class="mb-6" />

		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			<div class="lg:col-span-1 space-y-6">
				<ProfileInfoSection :user="user" :employee="employee" :loading="loading" @save-info="handleSaveInfo" @change-password="handleChangePassword" />
				<ProfileQuickLinksSection :links="quickLinks" />
			</div>
			<div class="lg:col-span-2 space-y-6">
				<ProfileActivitySection :activity="activity" />
				<ProfileNotificationsSection
					:notifications="notifications"
					:unread-count="unreadCount"
					:loading="loading"
					:filter="notificationsFilter"
					@filter-change="handleFilterChange"
					@mark-read="handleMarkRead"
					@mark-all="handleMarkAll"
				/>
			</div>
		</div>
	</section>
</template>
