<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import VitrineFilterBar from '@/components/vitrine/VitrineFilterBar.vue';
import VitrineEmptyState from '@/components/vitrine/VitrineEmptyState.vue';
import { getPortals, changePortalUsername, resetPortalPassword } from '@/services/vitrineService';
import { createPatientPortalUser, togglePatientPortalUser } from '@/services/patients';
import { getHttpErrorMessage } from '@/service/http';

const token = localStorage.getItem('token');
const items = ref([]);
const search = ref('');
const scope = ref('opened');
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const info = ref('');
const editing = ref(null);
const username = ref('');
const resetTarget = ref(null);
const credentials = ref(null);
let timer;
let controller;
let disposed = false;
async function load() {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    loading.value = true;
    error.value = '';
    try {
        const data = await getPortals({ q: search.value, scope: scope.value }, request.signal);
        if (!request.signal.aborted) items.value = data.items;
    } catch (e) {
        if (!request.signal.aborted) error.value = getHttpErrorMessage(e, 'Impossible de charger les portails.');
    } finally {
        if (controller === request) loading.value = false;
    }
}
async function run(action) {
    busy.value = true;
    error.value = '';
    info.value = '';
    try {
        await action();
        await load();
    } catch (e) {
        error.value = getHttpErrorMessage(e, 'Impossible de modifier le portail.');
    } finally {
        busy.value = false;
    }
}
function edit(item) {
    editing.value = item;
    username.value = item.username;
}
async function saveUsername() {
    await run(async () => {
        await changePortalUsername(editing.value.id, username.value);
        editing.value = null;
        info.value = 'Identifiant modifié.';
    });
}
async function reset() {
    await run(async () => {
        credentials.value = await resetPortalPassword(resetTarget.value.id);
        resetTarget.value = null;
    });
}
async function openPortal(item) {
    await run(async () => {
        if (!item.hasPortal) {
            const result = await createPatientPortalUser(item.id, token);
            credentials.value = { username: result.account.username, password: result.account.defaultPassword };
        } else await togglePatientPortalUser(item.id, true, token);
        info.value = 'Portail patient ouvert.';
    });
}
watch([search, scope], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (!disposed) load();
    }, 300);
});
onMounted(load);
onUnmounted(() => {
    disposed = true;
    clearTimeout(timer);
    controller?.abort();
    credentials.value = null;
});
</script>

<template>
    <PageShell>
        <template #header>
            <PageHeader title="Portail patient" subtitle="Gérez les accès des patients à leur espace personnel." icon="pi pi-id-card" :breadcrumb-items="[{ label: 'Vitrine' }, { label: 'Portail patient' }]">
                <template #actions><Button label="Actualiser" icon="pi pi-refresh" severity="secondary" outlined size="small" :loading="loading" :disabled="busy" @click="load" /></template>
            </PageHeader>
        </template>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <Message v-if="info" severity="success" :closable="false">{{ info }}</Message>
        <PageSection title="Accès des patients" subtitle="Créez un portail, modifiez un identifiant ou gérez un accès." padded>
            <template #headerActions><Tag :value="`${items.length} résultat${items.length > 1 ? 's' : ''}`" severity="secondary" /></template>
            <VitrineFilterBar>
                <div class="vitrine-filter vitrine-filter--search">
                    <label for="portal-search">Rechercher un patient</label><IconField><InputIcon class="pi pi-search" /><InputText id="portal-search" v-model="search" placeholder="Nom, prénom, carnet ou identifiant…" fluid /></IconField>
                </div>
                <div class="vitrine-filter">
                    <label for="portal-scope">Patients à afficher</label>
                    <Select
                        input-id="portal-scope"
                        v-model="scope"
                        :options="[
                            { label: 'Portails créés', value: 'opened' },
                            { label: 'Tous les patients', value: 'all' }
                        ]"
                        option-label="label"
                        option-value="value"
                        aria-label="Patients à afficher"
                    />
                </div>
            </VitrineFilterBar>
            <p class="portal-hint"><i class="pi pi-info-circle" aria-hidden="true" /> Choisissez « Tous les patients » pour créer un accès. Jusqu’à 100 patients par recherche.</p>
            <div class="page-table-scroll">
                <DataTable
                    :value="items"
                    :loading="loading"
                    paginator
                    :rows="15"
                    :rows-per-page-options="[15, 30, 50]"
                    data-key="id"
                    :table-style="{ minWidth: '54rem' }"
                    paginator-template="PrevPageLink PageLinks NextPageLink RowsPerPageDropdown"
                    :always-show-paginator="false"
                >
                    <template #empty><VitrineEmptyState title="Aucun patient trouvé" description="Essayez un autre nom ou élargissez la recherche à tous les patients." /></template>
                    <Column field="name" header="Patient" sortable
                        ><template #body="{ data }"
                            ><span class="patient-name">{{ data.name }}</span></template
                        ></Column
                    ><Column field="numCarnet" header="Carnet" />
                    <Column field="username" header="Identifiant"
                        ><template #body="{ data }">{{ data.username || 'Pas encore de compte' }}</template></Column
                    >
                    <Column header="Accès"
                        ><template #body="{ data }"><Tag :value="!data.hasPortal ? 'À créer' : data.active ? 'Ouvert' : 'Désactivé'" :severity="data.active ? 'success' : 'secondary'" /></template
                    ></Column>
                    <Column header="Actions"
                        ><template #body="{ data }"
                            ><div class="portal-actions">
                                <Button v-if="data.hasPortal" label="Identifiant" :aria-label="`Modifier l’identifiant de ${data.name}`" icon="pi pi-pencil" size="small" severity="secondary" outlined :disabled="busy" @click="edit(data)" />
                                <Button v-if="data.hasPortal" label="Réinitialiser" icon="pi pi-key" size="small" severity="warn" outlined :disabled="busy" @click="resetTarget = data" />
                                <Button v-if="!data.hasPortal || !data.active" label="Ouvrir le portail" icon="pi pi-lock-open" size="small" :disabled="busy" @click="openPortal(data)" />
                                <Button
                                    v-else
                                    label="Désactiver"
                                    icon="pi pi-lock"
                                    size="small"
                                    severity="secondary"
                                    text
                                    :disabled="busy"
                                    @click="
                                        run(async () => {
                                            await togglePatientPortalUser(data.id, false, token);
                                            info = 'Accès désactivé.';
                                        })
                                    "
                                /></div></template
                    ></Column>
                </DataTable>
            </div>
        </PageSection>
        <Dialog :visible="!!editing" modal header="Modifier l’identifiant" :closable="!busy" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" @update:visible="editing = null">
            <form class="portal-form" @submit.prevent="saveUsername">
                <label for="portal-username">Nouvel identifiant de {{ editing?.name }}</label
                ><InputText id="portal-username" v-model="username" minlength="3" maxlength="180" required fluid :disabled="busy" /><Button label="Enregistrer" type="submit" :loading="busy" />
            </form>
        </Dialog>
        <Dialog :visible="!!resetTarget" modal header="Réinitialiser le mot de passe" :closable="!busy" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" @update:visible="resetTarget = null">
            <p>Générer un nouveau mot de passe pour {{ resetTarget?.name }} ? L’ancien mot de passe sera remplacé.</p>
            <Button label="Réinitialiser" icon="pi pi-key" :loading="busy" @click="reset" />
        </Dialog>
        <Dialog :visible="!!credentials" modal header="Identifiants du portail" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" @update:visible="credentials = null">
            <p>Transmettez ces identifiants au patient. Le mot de passe est affiché uniquement ici.</p>
            <p>
                Identifiant : <strong>{{ credentials?.username }}</strong>
            </p>
            <p>
                Mot de passe : <code class="credential">{{ credentials?.password }}</code>
            </p>
            <Button label="Fermer" @click="credentials = null" />
        </Dialog>
    </PageShell>
</template>

<style scoped>
.portal-actions {
    display: flex;
    gap: 0.375rem;
    align-items: center;
    flex-wrap: nowrap;
}
.patient-name {
    font-weight: 600;
}
.portal-hint {
    display: flex;
    gap: 0.5rem;
    align-items: baseline;
    margin: 0 0 1rem;
    color: var(--text-color-secondary);
}
.portal-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.credential {
    user-select: all;
    font-size: 1.1rem;
    overflow-wrap: anywhere;
}
</style>
