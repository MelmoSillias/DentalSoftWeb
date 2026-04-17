<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import { duplicateMedicalForm, fetchMedicalForm, updateMedicalForm } from '@/services/medicalFormsService';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const token = localStorage.getItem('token');

const loading = ref(true);
const saving = ref(false);
const duplicating = ref(false);
const form = ref(null);

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = computed(() => [
    { label: 'Paramètres', to: router.resolve({ name: 'settings-apparence' }).href },
    { label: 'Formulaires médicaux', to: router.resolve({ name: 'settings-apparence' }).href },
    { label: form.value?.nom || 'Éditeur' }
]);

const fieldTypeOptions = [
    { label: 'Texte', value: 'text' },
    { label: 'Zone de texte', value: 'textarea' },
    { label: 'Nombre', value: 'number' },
    { label: 'Date', value: 'date' },
    { label: 'Booléen', value: 'boolean' },
    { label: 'Liste', value: 'select' },
    { label: 'Liste multiple', value: 'multiselect' },
    { label: 'Objet JSON', value: 'object' },
    { label: 'Collection d’objets', value: 'collection_object' },
    { label: 'Fichier', value: 'file' },
    { label: 'Collection de fichiers', value: 'file_collection' }
];

const extractApiError = (error, fallback) => error?.response?.data?.error || error?.response?.data?.message || error?.message || fallback;

const clone = (value) => JSON.parse(JSON.stringify(value));

const normalizeFormForSave = () => {
    const source = clone(form.value);
    return {
        nom: source.nom,
        code: source.code,
        description: source.description,
        actif: source.actif,
        onglets: (source.onglets || []).map((onglet, ongletIndex) => ({
            code: onglet.code,
            nom: onglet.nom,
            ordre: ongletIndex + 1,
            actif: onglet.actif !== false,
            sections: (onglet.sections || []).map((section, sectionIndex) => ({
                code: section.code,
                nom: section.nom,
                ordre: sectionIndex + 1,
                actif: section.actif !== false,
                champs: (section.champs || []).map((champ, champIndex) => ({
                    code: champ.code,
                    nom: champ.nom,
                    type: champ.type,
                    ordre: champIndex + 1,
                    actif: champ.actif !== false,
                    config: parseConfig(champ.configText)
                }))
            }))
        }))
    };
};

const parseConfig = (configText) => {
    const raw = String(configText || '').trim();
    if (!raw) return {};
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
};

const enrichForm = (payload) => ({
    ...payload,
    onglets: (payload.onglets || []).map((onglet) => ({
        ...onglet,
        sections: (onglet.sections || []).map((section) => ({
            ...section,
            champs: (section.champs || []).map((champ) => ({
                ...champ,
                configText: JSON.stringify(champ.config || {}, null, 2)
            }))
        }))
    }))
});

const loadForm = async () => {
    loading.value = true;
    try {
        form.value = enrichForm(await fetchMedicalForm(route.params.id, token));
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Formulaire', detail: extractApiError(error, 'Chargement impossible'), life: 3500 });
        router.push({ name: 'settings-apparence' });
    } finally {
        loading.value = false;
    }
};

const saveForm = async () => {
    saving.value = true;
    try {
        form.value = enrichForm(await updateMedicalForm(route.params.id, normalizeFormForSave(), token));
        toast.add({ severity: 'success', summary: 'Formulaire', detail: 'Formulaire enregistré', life: 2500 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Sauvegarde impossible'), life: 3500 });
    } finally {
        saving.value = false;
    }
};

const duplicateFormAction = async () => {
    duplicating.value = true;
    try {
        const duplicated = await duplicateMedicalForm(route.params.id, { nom: `${form.value.nom} copie` }, token);
        toast.add({ severity: 'success', summary: 'Formulaire', detail: 'Copie créée', life: 2500 });
        router.push({ name: 'settings-medical-form-editor', params: { id: duplicated.id } });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: extractApiError(error, 'Duplication impossible'), life: 3500 });
    } finally {
        duplicating.value = false;
    }
};

const moveItem = (list, index, direction) => {
    const nextIndex = index + direction;
    if (!Array.isArray(list) || nextIndex < 0 || nextIndex >= list.length) {
        return;
    }

    const [item] = list.splice(index, 1);
    list.splice(nextIndex, 0, item);
};

const addTab = () => {
    form.value.onglets.push({
        nom: `Onglet ${form.value.onglets.length + 1}`,
        code: '',
        ordre: form.value.onglets.length + 1,
        actif: true,
        sections: [
            {
                nom: 'Section 1',
                code: '',
                ordre: 1,
                actif: true,
                champs: [
                    {
                        nom: 'Champ 1',
                        code: '',
                        type: 'text',
                        ordre: 1,
                        actif: true,
                        config: {},
                        configText: '{}'
                    }
                ]
            }
        ]
    });
};

const addSection = (onglet) => {
    onglet.sections.push({
        nom: `Section ${onglet.sections.length + 1}`,
        code: '',
        ordre: onglet.sections.length + 1,
        actif: true,
        champs: [
            {
                nom: 'Champ 1',
                code: '',
                type: 'text',
                ordre: 1,
                actif: true,
                config: {},
                configText: '{}'
            }
        ]
    });
};

const addField = (section) => {
    section.champs.push({
        nom: `Champ ${section.champs.length + 1}`,
        code: '',
        type: 'text',
        ordre: section.champs.length + 1,
        actif: true,
        config: {},
        configText: '{}'
    });
};

onMounted(loadForm);
</script>

<template>
    <div class="space-y-6 pb-6">
        <div class="rounded-2xl border border-surface-200 bg-surface-0 p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900/50">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ form?.nom || 'Formulaire médical' }}</h1>
                            <Tag v-if="form?.isNatif" value="Natif" severity="warn" />
                            <Tag v-if="form && !form.actif" value="Inactif" severity="contrast" />
                        </div>
                        <p class="mt-2 max-w-3xl text-sm text-surface-500 dark:text-surface-400">
                            Gérez la structure du formulaire, ses onglets, sections et champs. Les fiches existantes conservent leur snapshot.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push({ name: 'settings-apparence' })" />
                    <Button label="Dupliquer" icon="pi pi-copy" severity="secondary" :loading="duplicating" @click="duplicateFormAction" />
                    <Button label="Enregistrer" icon="pi pi-save" :loading="saving" @click="saveForm" />
                </div>
            </div>
        </div>

        <div v-if="loading" class="space-y-4">
            <Skeleton height="14rem" borderRadius="1rem" />
            <Skeleton height="20rem" borderRadius="1rem" />
        </div>

        <div v-else-if="form" class="space-y-6">
            <Card>
                <template #content>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="field-group">
                            <label class="field-label">Nom</label>
                            <InputText v-model="form.nom" class="w-full" />
                        </div>
                        <div class="field-group">
                            <label class="field-label">Code technique</label>
                            <InputText v-model="form.code" class="w-full" />
                        </div>
                        <div class="field-group lg:col-span-2">
                            <label class="field-label">Description</label>
                            <Textarea v-model="form.description" rows="3" class="w-full" autoResize />
                        </div>
                        <div class="field-group">
                            <label class="field-label">Version</label>
                            <InputText :modelValue="String(form.version || 1)" class="w-full" disabled />
                        </div>
                        <div class="field-group inline-toggle">
                            <div>
                                <p class="field-label mb-1">Actif</p>
                                <p class="text-sm text-surface-500 dark:text-surface-400">Les nouvelles fiches pourront utiliser ce formulaire s’il reste actif.</p>
                            </div>
                            <ToggleSwitch v-model="form.actif" />
                        </div>
                    </div>
                </template>
            </Card>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">Structure</h2>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Organisez les onglets, sections et champs du formulaire.</p>
                    </div>
                    <Button label="Ajouter un onglet" icon="pi pi-plus" outlined @click="addTab" />
                </div>

                <div class="space-y-4">
                    <Card v-for="(onglet, ongletIndex) in form.onglets" :key="`onglet-${ongletIndex}`" class="editor-card">
                        <template #content>
                            <div class="space-y-5">
                                <div class="editor-head">
                                    <div class="editor-title-wrap">
                                        <h3 class="editor-title">Onglet {{ ongletIndex + 1 }}</h3>
                                        <Tag :value="`${onglet.sections.length} section(s)`" severity="info" />
                                    </div>
                                    <div class="editor-actions">
                                        <Button icon="pi pi-arrow-up" text rounded :disabled="ongletIndex === 0" @click="moveItem(form.onglets, ongletIndex, -1)" />
                                        <Button icon="pi pi-arrow-down" text rounded :disabled="ongletIndex === form.onglets.length - 1" @click="moveItem(form.onglets, ongletIndex, 1)" />
                                        <Button icon="pi pi-trash" text rounded severity="danger" @click="form.onglets.splice(ongletIndex, 1)" />
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]">
                                    <div class="field-group">
                                        <label class="field-label">Nom de l’onglet</label>
                                        <InputText v-model="onglet.nom" class="w-full" />
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Code</label>
                                        <InputText v-model="onglet.code" class="w-full" />
                                    </div>
                                    <div class="field-group inline-toggle compact">
                                        <span class="field-label">Actif</span>
                                        <ToggleSwitch v-model="onglet.actif" />
                                    </div>
                                </div>

                                <div class="space-y-4 nested-level">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="nested-title">Sections</h4>
                                        <Button label="Ajouter une section" icon="pi pi-plus" text @click="addSection(onglet)" />
                                    </div>

                                    <Card v-for="(section, sectionIndex) in onglet.sections" :key="`section-${ongletIndex}-${sectionIndex}`" class="nested-card">
                                        <template #content>
                                            <div class="space-y-4">
                                                <div class="editor-head">
                                                    <div class="editor-title-wrap">
                                                        <h5 class="editor-subtitle">Section {{ sectionIndex + 1 }}</h5>
                                                        <Tag :value="`${section.champs.length} champ(s)`" severity="contrast" />
                                                    </div>
                                                    <div class="editor-actions">
                                                        <Button icon="pi pi-arrow-up" text rounded :disabled="sectionIndex === 0" @click="moveItem(onglet.sections, sectionIndex, -1)" />
                                                        <Button icon="pi pi-arrow-down" text rounded :disabled="sectionIndex === onglet.sections.length - 1" @click="moveItem(onglet.sections, sectionIndex, 1)" />
                                                        <Button icon="pi pi-trash" text rounded severity="danger" @click="onglet.sections.splice(sectionIndex, 1)" />
                                                    </div>
                                                </div>

                                                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]">
                                                    <div class="field-group">
                                                        <label class="field-label">Nom de la section</label>
                                                        <InputText v-model="section.nom" class="w-full" />
                                                    </div>
                                                    <div class="field-group">
                                                        <label class="field-label">Code</label>
                                                        <InputText v-model="section.code" class="w-full" />
                                                    </div>
                                                    <div class="field-group inline-toggle compact">
                                                        <span class="field-label">Actif</span>
                                                        <ToggleSwitch v-model="section.actif" />
                                                    </div>
                                                </div>

                                                <div class="space-y-4 nested-level deeper">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <h6 class="nested-title">Champs</h6>
                                                        <Button label="Ajouter un champ" icon="pi pi-plus" text @click="addField(section)" />
                                                    </div>

                                                    <div v-for="(champ, champIndex) in section.champs" :key="`champ-${ongletIndex}-${sectionIndex}-${champIndex}`" class="field-card">
                                                        <div class="editor-head">
                                                            <h6 class="editor-subtitle">Champ {{ champIndex + 1 }}</h6>
                                                            <div class="editor-actions">
                                                                <Button icon="pi pi-arrow-up" text rounded :disabled="champIndex === 0" @click="moveItem(section.champs, champIndex, -1)" />
                                                                <Button icon="pi pi-arrow-down" text rounded :disabled="champIndex === section.champs.length - 1" @click="moveItem(section.champs, champIndex, 1)" />
                                                                <Button icon="pi pi-trash" text rounded severity="danger" @click="section.champs.splice(champIndex, 1)" />
                                                            </div>
                                                        </div>

                                                        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                                                            <div class="field-group xl:col-span-2">
                                                                <label class="field-label">Nom du champ</label>
                                                                <InputText v-model="champ.nom" class="w-full" />
                                                            </div>
                                                            <div class="field-group">
                                                                <label class="field-label">Code</label>
                                                                <InputText v-model="champ.code" class="w-full" />
                                                            </div>
                                                            <div class="field-group inline-toggle compact">
                                                                <span class="field-label">Actif</span>
                                                                <ToggleSwitch v-model="champ.actif" />
                                                            </div>
                                                            <div class="field-group xl:col-span-2">
                                                                <label class="field-label">Type</label>
                                                                <Select v-model="champ.type" :options="fieldTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                                                            </div>
                                                            <div class="field-group xl:col-span-2">
                                                                <label class="field-label">Configuration JSON</label>
                                                                <Textarea v-model="champ.configText" rows="5" class="w-full font-mono text-sm" autoResize />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </Card>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.field-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-color);
}

.inline-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.inline-toggle.compact {
    align-self: end;
    min-height: 42px;
}

.editor-card,
.nested-card,
.field-card {
    border-radius: 1rem;
}

.editor-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.editor-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.editor-title,
.editor-subtitle,
.nested-title {
    margin: 0;
    font-weight: 700;
    color: var(--text-color);
}

.nested-level {
    border-left: 2px solid color-mix(in srgb, var(--primary-color), transparent 80%);
    padding-left: 1rem;
}

.nested-level.deeper {
    border-left-color: color-mix(in srgb, var(--surface-500), transparent 60%);
}

.editor-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.field-card {
    border: 1px solid var(--surface-border);
    border-radius: 1rem;
    padding: 1rem;
    background: color-mix(in srgb, var(--surface-0), var(--surface-ground) 20%);
}

@media (max-width: 768px) {
    .editor-head {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>