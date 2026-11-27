<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import PageShell from '@/app/components/layout/PageShell.vue';
import PageHeader from '@/app/components/layout/PageHeader.vue';
import PageSection from '@/app/components/layout/PageSection.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Skeleton from 'primevue/skeleton';
import VitrineEmptyState from '@/domains/communication/components/vitrine/VitrineEmptyState.vue';
import { getHttpErrorMessage } from '@/shared/api/http.js';
import { getMailbox, saveMailbox, getMessages, syncMailbox, setMessageStatus, replyToMessage } from '@/domains/communication/services/vitrineService.js';
import { useMercureNotifications } from '@/shared/realtime/useMercureNotifications.js';
import { useAuthStore } from '@/domains/auth/stores/auth.js';

const route = useRoute();
const auth = useAuthStore();
const { onNotificationReceived } = useMercureNotifications();
const canConfigure = computed(() => auth.user?.roles?.includes('ROLE_ADMIN'));
const items = ref([]);
const selectedId = ref(null);
const selected = computed(() => items.value.find((item) => item.id === selectedId.value));
const mailList = ref(null);
const readingPane = ref(null);
const readingTitle = ref(null);
const mobileBack = ref(null);
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
    { label: 'Tous', value: 'all' },
    { label: 'Non lus', value: 'new' },
    { label: 'Lus', value: 'read' },
    { label: 'Traités', value: 'handled' }
];
const labels = { new: 'Non lu', read: 'Lu', handled: 'Traité' };
const filtered = computed(() => items.value.filter((m) => (status.value === 'all' || m.status === status.value) && `${m.sender} ${m.subject} ${m.body}`.toLowerCase().includes(search.value.trim().toLowerCase())));
const newCount = computed(() => items.value.filter((m) => m.status === 'new').length);
const date = (value) => new Date(value).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
const shortDate = (value) => new Date(value).toLocaleDateString('fr-FR', { dateStyle: 'short' });

function preview(body) {
    const text = body?.trim().replace(/\s+/g, ' ') || 'Aucun aperçu disponible';
    return text.length > 140 ? `${text.slice(0, 140).trimEnd()}…` : text;
}
function replace(item) {
    items.value = items.value.map((m) => (m.id === item.id ? item : m));
}
let refreshRequest;
let refreshPending = false;

async function load() {
    items.value = (await getMessages()).items;
}

function refreshInbox() {
    refreshPending = true;
    if (busy.value || refreshRequest) return;
    refreshPending = false;
    refreshRequest = load()
        .catch(() => {})
        .finally(() => {
            refreshRequest = undefined;
            if (refreshPending) refreshInbox();
        });
}

async function run(action) {
    busy.value = true;
    error.value = '';
    info.value = '';
    try {
        await refreshRequest;
        await action();
    } catch (e) {
        error.value = getHttpErrorMessage(e, 'Impossible de terminer cette opération.');
    } finally {
        busy.value = false;
        if (refreshPending) refreshInbox();
    }
}
async function openMessage(id) {
    if (selectedId.value === id) return;
    selectedId.value = id;
    reply.value = '';
    phone.value = '';
    await nextTick();
    if (readingPane.value) readingPane.value.scrollTop = 0;
    if (mobileBack.value?.offsetWidth) readingTitle.value?.focus();
}
async function selectMessage(item) {
    if (busy.value) return;
    await openMessage(item.id);
    if (item.status === 'new') await run(async () => replace(await setMessageStatus(item.id, 'read')));
}
async function backToList() {
    const row = mailList.value?.querySelector('[aria-pressed="true"]');
    selectedId.value = null;
    await nextTick();
    const target = row?.isConnected ? row : mailList.value?.querySelector('#mail-search');
    target?.focus();
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
    const payload = { body: reply.value, channel: channel.value, phone: phone.value };
    await run(async () => {
        replace(await replyToMessage(id, payload));
        if (selectedId.value === id) reply.value = '';
        info.value = payload.channel === 'sms' ? 'SMS ajouté à la file d’envoi.' : 'Réponse confiée au serveur email.';
    });
}
const unsubscribe = onNotificationReceived((notification) => {
    if (notification.link?.startsWith('/vitrine/messagerie?message=')) refreshInbox();
});

onMounted(async () => {
    await run(async () => {
        const config = await getMailbox();
        connected.value = config.configured;
        settings.value = { ...settings.value, ...config, password: '' };
        await load();
    });
    loading.value = false;
});
onUnmounted(() => {
    unsubscribe();
    refreshPending = false;
    settings.value.password = '';
});
watch(settingsOpen, (open) => {
    if (!open) settings.value.password = '';
});
const linkedMessageId = computed(() => Number(route.query.message) || null);
const hasLinkedMessage = computed(() => items.value.some((item) => item.id === linkedMessageId.value));
watch(linkedMessageId, (id) => {
    if (id !== null && !hasLinkedMessage.value) refreshInbox();
});
watch([linkedMessageId, hasLinkedMessage], ([id, available]) => {
    if (available) openMessage(id);
});
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
        <PageSection class="mailbox-frame" title="Boîte de réception">
            <template #headerActions><Tag :value="loading ? 'Connexion…' : connected ? 'Email connecté' : 'Email non connecté'" :severity="connected ? 'success' : 'secondary'" :icon="connected ? 'pi pi-check-circle' : 'pi pi-envelope'" /></template>
            <div class="mailbox" :class="{ 'mailbox--selected': selected }">
                <aside ref="mailList" class="mail-list" aria-label="Liste des messages" :aria-busy="loading">
                    <div class="mail-filters">
                        <div class="mail-filter-controls">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText id="mail-search" v-model="search" placeholder="Rechercher…" aria-label="Rechercher par objet, expéditeur ou contenu" size="small" fluid />
                            </IconField>
                            <Select v-model="status" :options="statuses" option-label="label" option-value="value" aria-label="Filtrer par statut" size="small" />
                        </div>
                        <p v-if="!loading" class="mail-count" role="status">
                            <span>{{ filtered.length }} message{{ filtered.length === 1 ? '' : 's' }}</span>
                            <span v-if="newCount" class="mail-unread-count">{{ newCount }} non lu{{ newCount === 1 ? '' : 's' }} au total</span>
                        </p>
                    </div>
                    <div class="mail-results">
                        <div v-if="loading" class="mail-loading"><Skeleton v-for="n in 4" :key="n" height="3.5rem" /></div>
                        <VitrineEmptyState
                            v-else-if="!filtered.length"
                            icon="pi pi-inbox"
                            :title="items.length ? 'Aucun résultat' : 'Aucun message reçu'"
                            :description="items.length ? 'Modifiez votre recherche ou le statut sélectionné.' : 'Les demandes de rendez-vous apparaîtront ici.'"
                        />
                        <ul v-else class="mail-rows">
                            <li v-for="item in filtered" :key="item.id">
                                <button class="mail-row" :class="{ unread: item.status === 'new' }" :aria-pressed="selectedId === item.id" @click="selectMessage(item)">
                                    <span class="mail-row-line">
                                        <span class="mail-subject">{{ item.subject || '(Sans objet)' }}</span>
                                        <i v-if="item.status === 'handled'" class="pi pi-check mail-handled" aria-hidden="true" :title="labels[item.status]" />
                                        <span class="sr-only">{{ labels[item.status] }}</span>
                                        <time class="mail-date" :datetime="item.receivedAt" :title="date(item.receivedAt)">{{ shortDate(item.receivedAt) }}</time>
                                    </span>
                                    <span class="mail-row-line mail-snippet">
                                        <span v-if="item.sender" class="mail-sender">{{ item.sender }}</span>
                                        <span v-if="item.sender" aria-hidden="true">·</span>
                                        <span class="mail-preview">{{ preview(item.body) }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>
                <section v-if="selected" ref="readingPane" class="conversation" aria-labelledby="mail-reading-title">
                    <div ref="mobileBack" class="conversation-back">
                        <Button label="Retour aux messages" icon="pi pi-arrow-left" severity="secondary" text size="small" @click="backToList" />
                    </div>
                    <header class="conversation-header">
                        <h2 id="mail-reading-title" ref="readingTitle" tabindex="-1">{{ selected.subject || '(Sans objet)' }}</h2>
                        <div class="conversation-meta">
                            <span class="sender-avatar" aria-hidden="true"><i class="pi pi-user" /></span>
                            <div class="conversation-sender">
                                <strong>{{ selected.sender }}</strong>
                                <time :datetime="selected.receivedAt">{{ date(selected.receivedAt) }}</time>
                            </div>
                            <Tag v-if="selected.status === 'handled'" value="Traité" severity="success" icon="pi pi-check" />
                            <Button v-else label="Marquer comme traité" icon="pi pi-check" severity="secondary" outlined size="small" :disabled="busy" @click="run(async () => replace(await setMessageStatus(selected.id, 'handled')))" />
                        </div>
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
.mailbox-frame {
    container: mailbox / inline-size;
}
.mailbox {
    display: grid;
    grid-template-columns: clamp(17rem, 35%, 25rem) minmax(0, 1fr);
    grid-template-rows: minmax(0, 1fr);
    height: clamp(26rem, 70dvh, 56rem);
}
.mail-list {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border-inline-end: 1px solid var(--surface-border);
}
.mail-filters {
    flex-shrink: 0;
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-ground);
}
.mail-filter-controls {
    display: flex;
    gap: 0.5rem;
}
.mail-filter-controls :deep(.p-iconfield) {
    flex: 1;
    min-width: 0;
}
.mail-filter-controls :deep(.p-select) {
    flex-shrink: 0;
    min-width: 6.25rem;
}
.mail-count {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.25rem 0.625rem;
    margin: 0.5rem 0 0;
    color: var(--text-color-secondary);
    font-size: 0.75rem;
}
.mail-unread-count {
    font-weight: 600;
}
.mail-results,
.conversation {
    min-height: 0;
    overflow: auto;
}
.mail-loading {
    display: grid;
    gap: 0.375rem;
    padding: 0.625rem 0.75rem;
}
.mail-rows {
    margin: 0;
    padding: 0;
    list-style: none;
}
.mail-row {
    display: grid;
    gap: 0.125rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    text-align: start;
    border: 0;
    border-inline-start: 3px solid transparent;
    border-bottom: 1px solid var(--surface-border);
    background: transparent;
    color: var(--text-color);
    font: inherit;
    line-height: 1.35;
    cursor: pointer;
}
.mail-row:hover {
    background: var(--surface-hover);
}
.mail-row[aria-pressed='true'] {
    background: color-mix(in srgb, var(--p-primary-color) 13%, var(--surface-card));
}
.mail-row.unread {
    border-inline-start-color: var(--p-primary-color);
}
.mail-row.unread .mail-subject {
    font-weight: 700;
}
.mail-row:focus-visible,
.quick-links a:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: -2px;
}
.mail-row-line {
    display: flex;
    align-items: baseline;
    min-width: 0;
    gap: 0.375rem;
}
.mail-subject,
.mail-sender,
.mail-preview {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mail-subject {
    flex: 1;
    font-weight: 600;
}
.mail-date,
.mail-handled {
    flex-shrink: 0;
    color: var(--text-color-secondary);
    font-size: 0.75rem;
    white-space: nowrap;
}
.mail-snippet {
    font-size: 0.8125rem;
    color: var(--text-color-secondary);
}
.mail-sender {
    flex: 0 0 auto;
    max-width: 38%;
    font-weight: 500;
}
.conversation {
    min-width: 0;
    padding: 1.125rem 1.5rem;
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
    display: grid;
    gap: 0.875rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--surface-border);
}
.conversation h2,
.composer h3 {
    margin: 0;
    font-weight: 600;
}
.conversation h2 {
    font-size: 1.25rem;
}
.conversation-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.625rem;
}
.sender-avatar {
    display: grid;
    flex: 0 0 2.25rem;
    height: 2.25rem;
    place-items: center;
    border-radius: 50%;
    background: color-mix(in srgb, var(--p-primary-color) 10%, var(--surface-card));
    color: var(--p-primary-color);
}
.conversation-sender {
    flex: 1 1 10rem;
    min-width: 0;
}
.conversation-sender time {
    display: block;
    color: var(--text-color-secondary);
    font-size: 0.8125rem;
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
.connection-form label,
.composer label {
    color: var(--text-color-secondary);
    font-weight: 500;
}
.connection-form p {
    margin: 0 0 0.5rem;
    color: var(--text-color-secondary);
}
@container mailbox (max-width: 42rem) {
    .mailbox {
        display: block;
        height: auto;
    }
    .mail-list {
        border-inline-end: 0;
    }
    .mail-results {
        max-height: 65dvh;
    }
    .conversation--empty,
    .mailbox--selected .mail-list {
        display: none;
    }
    .conversation-back {
        display: block;
        margin-bottom: 0.625rem;
    }
    .conversation {
        padding: 0.875rem;
        overflow: visible;
    }
}
</style>
