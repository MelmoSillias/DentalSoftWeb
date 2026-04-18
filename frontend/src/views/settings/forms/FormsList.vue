<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useFormBuilderStore } from '@/stores/formBuilder';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const store = useFormBuilderStore();
const search = ref('');

const formsFiltered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return store.forms;

    return store.forms.filter((item) => {
        const haystack = [item.code, item.label, item.status, item.description].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });
});

const toBuilder = (id = null) => {
    if (!id) {
        router.push({ name: 'settings-forms-builder-new' });
        return;
    }

    router.push({ name: 'settings-forms-builder', params: { formId: id } });
};

const refresh = async () => {
    try {
        await store.loadForms(true);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Formulaires', detail: error?.message || 'Chargement impossible', life: 3200 });
    }
};

const setDefault = async (form) => {
    try {
        await store.setDefaultForm(form.code);
        toast.add({ severity: 'success', summary: 'Formulaire par defaut', detail: `${form.label} applique`, life: 2200 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise a jour impossible', life: 3200 });
    }
};

const duplicate = async (form) => {
    try {
        await store.duplicateForm(form.id, `${form.label} - copie`);
        toast.add({ severity: 'success', summary: 'Duplication', detail: 'Version brouillon creee', life: 2500 });
        await store.loadForms(true);
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Duplication impossible', life: 3200 });
    }
};

const publish = async (form) => {
    try {
        await store.publishForm(form.id);
        toast.add({ severity: 'success', summary: 'Publication', detail: 'Formulaire publie', life: 2200 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Publication impossible', life: 3200 });
    }
};

const removeForm = async (form) => {
    confirm.require({
        header: 'Suppression formulaire',
        message: `Supprimer ${form.label} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                store.activeFormId = form.id;
                await store.removeActiveForm();
                toast.add({ severity: 'success', summary: 'Suppression', detail: 'Formulaire supprime', life: 2200 });
                await store.loadForms(true);
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Suppression impossible', life: 3200 });
            }
        }
    });
};

onMounted(async () => {
    await refresh();
});
</script>

<template>
    <div class="forms-page">
        <ConfirmDialog />

        <div class="forms-hero">
            <div>
                <p class="forms-kicker">SETTINGS / FORM BUILDER</p>
                <h1>Formulaires dynamiques</h1>
                <p>Gerez les definitions JSON, les versions publiees et le formulaire medical par defaut.</p>
            </div>
            <div class="forms-hero-actions">
                <Button label="Rafraichir" icon="pi pi-refresh" severity="secondary" outlined :loading="store.loading" @click="refresh" />
                <Button label="Nouveau formulaire" icon="pi pi-plus" @click="toBuilder()" />
            </div>
        </div>

        <div class="forms-toolbar">
            <IconField>
                <InputIcon class="pi pi-search"></InputIcon>
                <InputText v-model="search" placeholder="Rechercher un formulaire" class="w-full" />
            </IconField>
            <Tag :value="`Defaut: ${store.defaultFormCode || 'non defini'}`" severity="info" />
        </div>

        <div class="forms-table-wrap">
            <div v-if="store.loading" class="forms-loading">
                <Skeleton height="2rem" borderRadius="8px" v-for="n in 5" :key="n" />
            </div>

            <table v-else class="forms-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Code</th>
                        <th>Version</th>
                        <th>Statut</th>
                        <th>Par defaut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="form in formsFiltered" :key="form.id">
                        <td>
                            <div class="table-main">{{ form.label }}</div>
                            <div class="table-sub">{{ form.description || 'Sans description' }}</div>
                        </td>
                        <td>{{ form.code }}</td>
                        <td>{{ form.version || '-' }}</td>
                        <td>
                            <Tag
                                :severity="form.status === 'published' ? 'success' : form.status === 'draft' ? 'warning' : 'secondary'"
                                :value="form.status || 'draft'"
                            />
                        </td>
                        <td>
                            <Tag v-if="store.defaultFormCode === form.code" value="Defaut" severity="contrast" />
                            <span v-else>-</span>
                        </td>
                        <td>
                            <div class="actions">
                                <Button icon="pi pi-pencil" text rounded aria-label="Edit" @click="toBuilder(form.id)" />
                                <Button icon="pi pi-copy" text rounded aria-label="Duplicate" @click="duplicate(form)" />
                                <Button icon="pi pi-upload" text rounded aria-label="Publish" @click="publish(form)" />
                                <Button icon="pi pi-star" text rounded aria-label="Default" @click="setDefault(form)" />
                                <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Delete" @click="removeForm(form)" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!formsFiltered.length">
                        <td colspan="6" class="empty">Aucun formulaire disponible.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.forms-page {
    display: grid;
    gap: 1rem;
    padding-bottom: 1rem;
}

.forms-hero {
    border-radius: 20px;
    padding: 1.15rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
    justify-content: space-between;
    align-items: flex-start;
}

.forms-kicker {
    margin: 0 0 0.35rem;
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    font-weight: 700;
    color: var(--primary-color);
}

.forms-hero h1 {
    margin: 0;
    font-size: 1.6rem;
}

.forms-hero p {
    margin: 0.5rem 0 0;
    max-width: 650px;
    color: var(--text-color-secondary);
}

.forms-hero-actions {
    display: flex;
    gap: 0.55rem;
}

.forms-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: space-between;
    align-items: center;
}

.forms-table-wrap {
    border: 1px solid var(--surface-border);
    border-radius: 16px;
    overflow: auto;
    background: var(--surface-card);
}

.forms-loading {
    display: grid;
    gap: 0.55rem;
    padding: 0.8rem;
}

.forms-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
}

.forms-table th,
.forms-table td {
    padding: 0.7rem 0.8rem;
    border-bottom: 1px solid var(--surface-border);
    text-align: left;
    vertical-align: middle;
}

.forms-table th {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.table-main {
    font-weight: 600;
}

.table-sub {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.actions {
    display: flex;
    gap: 0.25rem;
}

.empty {
    text-align: center;
    color: var(--text-color-secondary);
    padding: 1rem;
}

@media (max-width: 920px) {
    .forms-hero-actions {
        width: 100%;
    }
}
</style>
