<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { getHttpErrorMessage } from '@/service/http';
import { getMailbox, saveMailbox, getMessages, syncMailbox, setMessageStatus, replyToMessage } from '@/services/vitrineService';
import { on as onRealtime } from '@/composables/realtime/mercureEventRouter';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();
const canConfigure = computed(() => auth.user?.roles?.includes('ROLE_ADMIN'));
const items = ref([]);
const selectedId = ref(null);
const selected = computed(() => items.value.find((item) => item.id === selectedId.value));
const search = ref('');
const status = ref('all');
const busy = ref(false);
const error = ref('');
const info = ref('');
const connected = ref(false);
const settingsOpen = ref(false);
const settings = ref({ email: '', host: '', username: '', password: '' });
const reply = ref('');
const phone = ref('');
const channel = ref('email');
const statuses = [
    { label: 'Tous les messages', value: 'all' },
    { label: 'Nouveaux', value: 'new' },
    { label: 'Lus', value: 'read' },
    { label: 'Traités', value: 'handled' }
];
const labels = { new: 'Nouveau', read: 'Lu', handled: 'Traité' };
const filtered = computed(() => items.value.filter((m) => (status.value === 'all' || m.status === status.value) && `${m.sender} ${m.subject} ${m.body}`.toLowerCase().includes(search.value.toLowerCase())));
const newCount = computed(() => items.value.filter((m) => m.status === 'new').length);
const handledCount = computed(() => items.value.filter((m) => m.status === 'handled').length);
const date = (value) => new Date(value).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
function replace(item) {
    items.value = items.value.map((m) => (m.id === item.id ? item : m));
}
async function run(action) {
    busy.value = true;
    error.value = '';
    info.value = '';
    try {
        await action();
    } catch (e) {
        error.value = getHttpErrorMessage(e, 'Impossible de terminer cette opération.');
    } finally {
        busy.value = false;
    }
}
async function load() {
    items.value = (await getMessages()).items;
    if (route.query.message && items.value.some((m) => m.id === Number(route.query.message))) selectedId.value = Number(route.query.message);
}
async function selectMessage(item) {
    if (busy.value) return;
    selectedId.value = item.id;
    reply.value = '';
    phone.value = '';
    if (item.status === 'new') await run(async () => replace(await setMessageStatus(item.id, 'read')));
}
async function connect() {
    await run(async () => {
        await saveMailbox(settings.value);
        settings.value.password = '';
        connected.value = true;
        settingsOpen.value = false;
        info.value = 'Email de contact connecté.';
    });
}
async function sync() {
    await run(async () => {
        const result = await syncMailbox();
        await load();
        info.value = `${result.imported} nouveau(x) message(s) importé(s).`;
    });
}
async function send() {
    const id = selectedId.value;
    await run(async () => {
        replace(await replyToMessage(id, { body: reply.value, channel: channel.value, phone: phone.value }));
        reply.value = '';
        info.value = channel.value === 'sms' ? 'SMS ajouté à la file d’envoi.' : 'Réponse confiée au serveur email.';
    });
}
let unsubscribe;
onMounted(async () => {
    await run(async () => {
        const config = await getMailbox();
        connected.value = config.configured;
        settings.value = { ...settings.value, ...config, password: '' };
        await load();
    });
    unsubscribe = onRealtime('notification', () => load().catch(() => {}));
});
onUnmounted(() => {
    unsubscribe?.();
    settings.value.password = '';
});
watch(settingsOpen, (open) => {
    if (!open) settings.value.password = '';
});
watch(
    () => route.query.message,
    () => {
        if (route.query.message) selectedId.value = Number(route.query.message);
    }
);
</script>

<template>
    <PageShell>
        <template #header>
            <PageHeader title="Messagerie" subtitle="Les demandes de rendez-vous du site, réunies au même endroit." icon="pi pi-inbox" :breadcrumb-items="[{ label: 'Vitrine' }, { label: 'Messagerie' }]">
                <template #actions>
                    <Button v-if="canConfigure" label="Connecter l’email" icon="pi pi-cog" severity="secondary" outlined :disabled="busy" @click="settingsOpen = true" />
                    <Button label="Synchroniser" icon="pi pi-sync" :loading="busy" :disabled="!connected" @click="sync" />
                </template>
            </PageHeader>
        </template>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <Message v-if="info" severity="success" :closable="false">{{ info }}</Message>
        <Message v-if="!connected" severity="info" :closable="false">Connectez l’email de contact pour importer les objets « Nouvelle demande de rendez-vous ».</Message>
        <div class="page-kpi-grid">
            <div class="page-kpi-card">
                <div>
                    <p class="page-kpi-label">Demandes reçues</p>
                    <p class="page-kpi-value">{{ items.length }}</p>
                </div>
                <i class="pi pi-inbox page-kpi-icon" />
            </div>
            <div class="page-kpi-card">
                <div>
                    <p class="page-kpi-label">Nouveaux messages</p>
                    <p class="page-kpi-value">{{ newCount }}</p>
                </div>
                <i class="pi pi-envelope page-kpi-icon" />
            </div>
            <div class="page-kpi-card">
                <div>
                    <p class="page-kpi-label">Demandes traitées</p>
                    <p class="page-kpi-value">{{ handledCount }}</p>
                </div>
                <i class="pi pi-check-circle page-kpi-icon" />
            </div>
        </div>
        <div class="mailbox">
            <aside class="mail-list">
                <div class="filters">
                    <label for="mail-search">Rechercher un message</label><InputText id="mail-search" v-model="search" placeholder="Nom, email, contenu…" fluid /><Select
                        v-model="status"
                        :options="statuses"
                        option-label="label"
                        option-value="value"
                        aria-label="Statut des messages"
                        fluid
                    />
                </div>
                <p v-if="!filtered.length" class="empty">{{ busy ? 'Chargement…' : 'Aucun message pour ces filtres.' }}</p>
                <button v-for="item in filtered" :key="item.id" class="mail-row" :class="{ active: selectedId === item.id, unread: item.status === 'new' }" :aria-pressed="selectedId === item.id" @click="selectMessage(item)">
                    <div class="row-heading">
                        <strong>{{ item.sender }}</strong
                        ><Tag :value="labels[item.status]" :severity="item.status === 'new' ? 'info' : 'secondary'" />
                    </div>
                    <span>{{ item.subject }}</span
                    ><small>{{ date(item.receivedAt) }}</small>
                    <p>{{ item.body.slice(0, 110) }}</p>
                </button>
            </aside>
            <section v-if="selected" class="conversation" aria-label="Message et réponses">
                <header class="conversation-header">
                    <div>
                        <h2>{{ selected.subject }}</h2>
                        <p>{{ selected.sender }} · {{ date(selected.receivedAt) }}</p>
                    </div>
                    <Button label="Marquer traité" icon="pi pi-check" severity="secondary" outlined :disabled="busy || selected.status === 'handled'" @click="run(async () => replace(await setMessageStatus(selected.id, 'handled')))" />
                </header>
                <div class="message-body">{{ selected.body || 'Ce message ne contient pas de version texte.' }}</div>
                <div class="quick-links"><RouterLink :to="{ name: 'agenda-rendezvous' }">Consulter l’agenda</RouterLink><RouterLink to="/vitrine/portail-patient">Rechercher un patient</RouterLink></div>
                <article v-for="(entry, index) in selected.replies" :key="index" class="reply-bubble">
                    <div>
                        <strong>{{ entry.channel === 'sms' ? 'SMS' : 'Email' }} → {{ entry.recipient }}</strong
                        ><small>{{ date(entry.date) }} · {{ entry.delivery?.status === 'queued' ? 'En file d’envoi' : 'Accepté par le transport' }}</small>
                    </div>
                    <p>{{ entry.body }}</p>
                </article>
                <form class="composer" @submit.prevent="send">
                    <h3>Répondre au patient</h3>
                    <Select
                        v-model="channel"
                        :options="[
                            { label: 'Par email', value: 'email' },
                            { label: 'Par SMS', value: 'sms' }
                        ]"
                        option-label="label"
                        option-value="value"
                        aria-label="Canal de réponse"
                        :disabled="busy"
                    />
                    <label v-if="channel === 'sms'" for="reply-phone">Téléphone du destinataire</label><InputText v-if="channel === 'sms'" id="reply-phone" v-model="phone" placeholder="+223…" required :disabled="busy" />
                    <label for="reply-body">Votre réponse</label><Textarea id="reply-body" v-model="reply" rows="5" :maxlength="channel === 'sms' ? 160 : 10000" required :disabled="busy" fluid />
                    <small v-if="channel === 'sms'">{{ reply.length }}/160 caractères</small>
                    <Button type="submit" label="Envoyer la réponse" icon="pi pi-send" :loading="busy" :disabled="!reply.trim() || (channel === 'sms' && !phone)" />
                </form>
            </section>
            <section v-else class="empty conversation">
                <i class="pi pi-comments text-4xl" />
                <h2>Votre accueil en ligne</h2>
                <p>Sélectionnez une demande pour consulter son contenu et répondre par email ou SMS.</p>
            </section>
        </div>
        <Dialog v-model:visible="settingsOpen" modal header="Connecter l’email de contact" :style="{ width: '32rem' }" :closable="!busy">
            <form class="connection-form" @submit.prevent="connect">
                <p>Connexion sécurisée IMAP (SSL, port 993). Utilisez un mot de passe d’application si votre fournisseur le demande.</p>
                <label for="contact-email">Email de contact</label><InputText id="contact-email" v-model="settings.email" type="email" required fluid /> <label for="imap-host">Serveur IMAP</label
                ><InputText id="imap-host" v-model="settings.host" placeholder="imap.exemple.fr" required fluid /> <label for="imap-user">Identifiant IMAP</label
                ><InputText id="imap-user" v-model="settings.username" placeholder="Par défaut : email de contact" fluid /> <label for="imap-password">Mot de passe d’application</label
                ><InputText id="imap-password" v-model="settings.password" type="password" autocomplete="new-password" :required="!connected" :placeholder="connected ? 'Laisser vide pour conserver' : ''" fluid />
                <Button label="Tester et connecter" type="submit" :loading="busy" />
            </form>
        </Dialog>
    </PageShell>
</template>

<style scoped>
.mailbox {
    display: grid;
    grid-template-columns: minmax(270px, 35%) 1fr;
    border: 1px solid var(--surface-border);
    border-radius: 1rem;
    overflow: hidden;
    background: var(--surface-card);
    min-height: 36rem;
}
.mail-list {
    border-right: 1px solid var(--surface-border);
    max-height: 75vh;
    overflow: auto;
}
.filters,
.connection-form,
.composer {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    padding: 1.25rem;
}
.mail-row {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
    padding: 1.2rem;
    text-align: left;
    border: 0;
    border-bottom: 1px solid var(--surface-border);
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.mail-row.active {
    background: var(--p-primary-50);
    color: var(--p-primary-900);
}
.mail-row.unread {
    border-left: 3px solid var(--p-primary-color);
}
.row-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    max-width: 100%;
    overflow-wrap: anywhere;
}
.mail-row p {
    margin: 0;
    opacity: 0.7;
}
.mail-row small {
    opacity: 0.65;
}
.conversation {
    padding: 1.5rem;
    min-width: 0;
}
.conversation-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.conversation h2 {
    font-size: 1.25rem;
    margin: 0;
}
.message-body {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    padding: 1.5rem 0;
    line-height: 1.7;
}
.quick-links {
    display: flex;
    gap: 1.5rem;
    padding-bottom: 1rem;
}
.quick-links a {
    color: var(--p-primary-color);
}
.reply-bubble {
    background: var(--surface-ground);
    border-radius: 1rem;
    padding: 1rem;
    margin: 1rem 0;
    overflow-wrap: anywhere;
}
.reply-bubble small {
    display: block;
    opacity: 0.7;
    margin-top: 0.3rem;
}
.reply-bubble p {
    white-space: pre-wrap;
}
.composer {
    border-top: 1px solid var(--surface-border);
    padding: 1rem 0;
}
.empty {
    text-align: center;
    padding: 3rem 1.5rem;
    align-content: center;
    color: var(--text-color-secondary);
}
@media (max-width: 800px) {
    .mailbox {
        grid-template-columns: 1fr;
    }
    .mail-list {
        max-height: 22rem;
        border-right: 0;
        border-bottom: 1px solid var(--surface-border);
    }
}
</style>
