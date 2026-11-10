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
import PageSection from '@/components/layout/PageSection.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Skeleton from 'primevue/skeleton';
import VitrineEmptyState from '@/components/vitrine/VitrineEmptyState.vue';
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
const loading = ref(true);
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
    loading.value = false;
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
                    <Button v-if="canConfigure" :label="connected ? 'Paramètres email' : 'Connecter l’email'" icon="pi pi-cog" severity="secondary" outlined size="small" :disabled="busy" @click="settingsOpen = true" />
                    <Button label="Synchroniser" icon="pi pi-sync" size="small" :loading="busy" :disabled="!connected" @click="sync" />
                </template>
            </PageHeader>
        </template>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <Message v-if="info" severity="success" :closable="false">{{ info }}</Message>
        <Message v-if="!loading && !connected" severity="info" :closable="false">{{
            canConfigure ? 'Connectez l’email de contact pour recevoir les demandes de rendez-vous du site.' : 'Un administrateur doit connecter l’email de contact pour recevoir les demandes du site.'
        }}</Message>
        <template #toolbar>
            <div class="page-kpi-grid mailbox-stats">
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
        </template>
        <PageSection title="Boîte de réception" subtitle="Consultez les demandes et répondez aux patients.">
            <template #headerActions><Tag :value="loading ? 'Connexion…' : connected ? 'Email connecté' : 'Email non connecté'" :severity="connected ? 'success' : 'secondary'" :icon="connected ? 'pi pi-check-circle' : 'pi pi-envelope'" /></template>
            <div class="mailbox" :class="{ 'mailbox--selected': selected }">
                <aside class="mail-list" aria-label="Liste des messages" :aria-busy="loading">
                    <div class="mail-filters">
                        <label for="mail-search">Rechercher un message</label><IconField><InputIcon class="pi pi-search" /><InputText id="mail-search" v-model="search" placeholder="Nom, email, contenu…" fluid /></IconField>
                        <label for="mail-status">Statut</label><Select input-id="mail-status" v-model="status" :options="statuses" option-label="label" option-value="value" aria-label="Statut des messages" fluid />
                    </div>
                    <div v-if="loading" class="mail-loading"><Skeleton v-for="n in 3" :key="n" height="6rem" /></div>
                    <VitrineEmptyState
                        v-else-if="!filtered.length"
                        icon="pi pi-inbox"
                        :title="items.length ? 'Aucun résultat' : 'Aucun message reçu'"
                        :description="items.length ? 'Modifiez votre recherche ou le statut sélectionné.' : 'Les demandes de rendez-vous apparaîtront ici.'"
                    />
                    <p v-else class="mail-count" role="status">{{ filtered.length }} message{{ filtered.length > 1 ? 's' : '' }}</p>
                    <button v-for="item in filtered" :key="item.id" class="mail-row" :class="{ active: selectedId === item.id, unread: item.status === 'new' }" :aria-pressed="selectedId === item.id" @click="selectMessage(item)">
                        <div class="row-heading">
                            <strong>{{ item.sender }}</strong
                            ><Tag :value="labels[item.status]" :severity="item.status === 'new' ? 'info' : 'secondary'" />
                        </div>
                        <span class="mail-subject">{{ item.subject }}</span
                        ><small>{{ date(item.receivedAt) }}</small>
                        <p>{{ item.body.slice(0, 110) }}</p>
                    </button>
                </aside>
                <section v-if="selected" class="conversation" aria-label="Message et réponses">
                    <Button class="conversation-back" label="Tous les messages" icon="pi pi-arrow-left" severity="secondary" text size="small" @click="selectedId = null" />
                    <header class="conversation-header">
                        <div>
                            <h2>{{ selected.subject }}</h2>
                            <p>{{ selected.sender }} · {{ date(selected.receivedAt) }}</p>
                        </div>
                        <Button label="Marquer traité" icon="pi pi-check" severity="secondary" outlined :disabled="busy || selected.status === 'handled'" @click="run(async () => replace(await setMessageStatus(selected.id, 'handled')))" />
                    </header>
                    <div class="message-body">{{ selected.body || 'Ce message ne contient pas de version texte.' }}</div>
                    <div class="quick-links">
                        <RouterLink :to="{ name: 'agenda-rendezvous' }"><i class="pi pi-calendar" aria-hidden="true" /> Consulter l’agenda</RouterLink
                        ><RouterLink to="/vitrine/portail-patient"><i class="pi pi-user" aria-hidden="true" /> Rechercher un patient</RouterLink>
                    </div>
                    <article v-for="(entry, index) in selected.replies" :key="index" class="reply-bubble">
                        <div>
                            <strong>{{ entry.channel === 'sms' ? 'SMS' : 'Email' }} → {{ entry.recipient }}</strong
                            ><small>{{ date(entry.date) }} · {{ entry.delivery?.status === 'queued' ? 'En file d’envoi' : 'Accepté par le transport' }}</small>
                        </div>
                        <p>{{ entry.body }}</p>
                    </article>
                    <form class="composer" @submit.prevent="send">
                        <h3>Répondre au patient</h3>
                        <label for="reply-channel">Canal de réponse</label
                        ><Select
                            input-id="reply-channel"
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
                <section v-else class="conversation conversation--empty">
                    <VitrineEmptyState icon="pi pi-comments" title="Sélectionnez une demande" description="Consultez son contenu et retrouvez les échanges avec le patient, par email ou SMS." />
                </section>
            </div>
        </PageSection>
        <Dialog v-model:visible="settingsOpen" modal header="Paramètres de l’email de contact" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :closable="!busy">
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
.mailbox-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.mailbox-stats .page-kpi-icon {
    color: var(--p-primary-color);
}
.mailbox {
    display: grid;
    grid-template-columns: minmax(16rem, 32%) minmax(0, 1fr);
    min-height: 34rem;
}
.mail-list {
    min-width: 0;
    border-right: 1px solid var(--surface-border);
    max-height: 72vh;
    overflow: auto;
}
.mail-filters {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.875rem;
    border-bottom: 1px solid var(--surface-border);
}
.mail-filters label,
.connection-form label,
.composer label {
    color: var(--text-color-secondary);
    font-weight: 500;
}
.mail-filters :deep(.p-iconfield) {
    width: 100%;
}
.mail-filters :deep(.p-select) {
    margin-top: 0;
}
.mail-count {
    margin: 0;
    padding: 0.625rem 0.875rem;
    color: var(--text-color-secondary);
    background: var(--surface-ground);
}
.mail-loading {
    display: grid;
    gap: 0.75rem;
    padding: 0.875rem;
}
.mail-row {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    width: 100%;
    padding: 0.875rem;
    text-align: left;
    border: 0;
    border-left: 3px solid transparent;
    border-bottom: 1px solid var(--surface-border);
    background: transparent;
    color: var(--text-color);
    font: inherit;
    cursor: pointer;
    overflow-wrap: anywhere;
}
.mail-row:hover {
    background: var(--surface-hover);
}
.mail-row.active {
    background: color-mix(in srgb, var(--p-primary-color) 9%, var(--surface-card));
}
.mail-row.unread {
    border-left-color: var(--p-primary-color);
}
.mail-row:focus-visible,
.quick-links a:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: -2px;
}
.row-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}
.row-heading strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.row-heading :deep(.p-tag) {
    flex-shrink: 0;
}
.mail-subject {
    font-weight: 500;
}
.mail-row p {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin: 0;
    color: var(--text-color-secondary);
}
.mail-row small {
    color: var(--text-color-secondary);
    font-size: var(--page-kpi-meta-size);
}
.conversation {
    padding: 1rem 1.25rem;
    min-width: 0;
    overflow-wrap: anywhere;
}
.conversation--empty {
    display: grid;
    place-items: center;
}
.conversation-back {
    display: none;
}
.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--surface-border);
}
.conversation h2,
.composer h3 {
    margin: 0;
    font-weight: 600;
}
.conversation-header p {
    margin: 0.375rem 0 0;
    color: var(--text-color-secondary);
}
.message-body {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    padding: 1.25rem 0;
    line-height: 1.65;
}
.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.25rem;
    padding-bottom: 1rem;
}
.quick-links a {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    color: var(--p-primary-color);
}
.quick-links a:hover {
    text-decoration: underline;
}
.reply-bubble {
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: var(--page-section-radius);
    padding: 0.875rem;
    margin: 0.75rem 0;
    overflow-wrap: anywhere;
}
.reply-bubble small {
    display: block;
    color: var(--text-color-secondary);
    margin-top: 0.3rem;
}
.reply-bubble p {
    white-space: pre-wrap;
    margin-bottom: 0;
}
.composer,
.connection-form {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
.composer {
    border-top: 1px solid var(--surface-border);
    padding-top: 1rem;
    margin-top: 0.5rem;
}
.composer > .p-button {
    align-self: flex-end;
}
.connection-form p {
    margin: 0 0 0.5rem;
    color: var(--text-color-secondary);
}
@media (max-width: 800px) {
    .mailbox {
        grid-template-columns: minmax(0, 1fr);
        min-height: 24rem;
    }
    .mail-list {
        max-height: 65vh;
        border-right: 0;
    }
    .conversation--empty {
        display: none;
    }
    .mailbox--selected .mail-list {
        display: none;
    }
    .conversation-back {
        display: inline-flex;
        margin-bottom: 0.75rem;
    }
    .conversation {
        padding: 0.875rem;
    }
}
@media (max-width: 479px) {
    .mailbox-stats {
        grid-template-columns: 1fr;
    }
}
</style>
