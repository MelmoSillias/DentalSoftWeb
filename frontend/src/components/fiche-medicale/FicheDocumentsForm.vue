<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import Galleria from 'primevue/galleria';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { filePrefix } from '@/config';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ documents: [] })
    },
    saving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const typeOptions = [
    { label: 'Radio', value: 'Radio' },
    { label: 'Scanner', value: 'Scanner' },
    { label: 'Resultats analyse', value: 'Resultats analyse' },
    { label: 'Compte rendu', value: 'Compte rendu' },
    { label: 'Ordonnance', value: 'Ordonnance' },
    { label: 'Devis', value: 'Devis' },
    { label: 'Consentement', value: 'Consentement' },
    { label: 'Photo clinique', value: 'Photo clinique' },
    { label: 'Autre', value: 'Autre' }
];

const showDialog = ref(false);
const dialogMode = ref('add');
const editingIndex = ref(null);
const previewVisible = ref(false);
const previewIndex = ref(0);
const previewItems = ref([]);
const draftDocument = reactive({
    type: 'Document',
    libelle: '',
    urls: [],
    files: [],
    groupKey: null
});
const draftUrlInput = ref('');
const fileUrlCache = new Map();

const dialogTitle = computed(() => (dialogMode.value === 'edit' ? 'Modifier un document' : 'Ajouter un document'));

const resetDraft = () => {
    draftDocument.type = 'Document';
    draftDocument.libelle = '';
    draftDocument.urls = [];
    draftDocument.files = [];
    draftDocument.groupKey = null;
    draftUrlInput.value = '';
};

const createGroupKey = () => {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return `doc_${Date.now()}_${Math.random().toString(16).slice(2)}`;
};

const getFileUrl = (file) => {
    if (!file) return '';
    if (fileUrlCache.has(file)) return fileUrlCache.get(file);
    const url = URL.createObjectURL(file);
    fileUrlCache.set(file, url);
    return url;
};

const resolveUrl = (url) => {
    if (!url || typeof url !== 'string') return '';
    if (/^https?:\/\//i.test(url) || url.startsWith('blob:') || url.startsWith('data:')) return url;
    const prefix = filePrefix.replace(/\/$/, '');
    return `${prefix}/${url.replace(/^\//, '')}`;
};

const getExtension = (value) => {
    if (!value) return '';
    const cleaned = value.split('?')[0].split('#')[0];
    const parts = cleaned.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
};

const isImageExtension = (extension) => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(extension);

const isImageDocument = (doc) => {
    const files = Array.isArray(doc?.files) ? doc.files : doc?.file ? [doc.file] : [];
    if (files[0]?.type) return files[0].type.startsWith('image/');
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    const extension = getExtension(files[0]?.name || urls[0] || '');
    return isImageExtension(extension);
};

const getDocumentIcon = (extension) => {
    switch (extension) {
        case 'pdf':
            return 'pi-file-pdf';
        case 'doc':
        case 'docx':
            return 'pi-file-word';
        case 'xls':
        case 'xlsx':
            return 'pi-file-excel';
        case 'ppt':
        case 'pptx':
            return 'pi-file-powerpoint';
        default:
            return 'pi-file';
    }
};

const getDocTitle = (doc, idx) => {
    const title = doc?.libelle?.trim();
    if (title) return title;
    const files = Array.isArray(doc?.files) ? doc.files : doc?.file ? [doc.file] : [];
    if (files[0]?.name) return files[0].name;
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    if (urls[0]) return urls[0].split('/').pop();
    return `Document ${idx + 1}`;
};

const getDocDescription = (doc) => {
    const type = doc?.type || 'Document';
    const files = Array.isArray(doc?.files) ? doc.files : doc?.file ? [doc.file] : [];
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    const count = files.length + urls.length;
    if (count) return `${type} - ${count} fichier(s)`;
    return `${type} - Aucun fichier lie`;
};

const getDocPreview = (doc) => {
    const files = Array.isArray(doc?.files) ? doc.files : doc?.file ? [doc.file] : [];
    if (files[0]) return getFileUrl(files[0]);
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    if (urls[0]) return resolveUrl(urls[0]);
    return '';
};

const addDocument = (doc = null) => {
    const docs = form.value.documents || [];
    form.value = {
        ...form.value,
        documents: [
            ...docs,
            doc ?? { type: 'Document', libelle: '', urls: [], files: [], groupKey: createGroupKey() }
        ]
    };
};

const updateDocument = (idx, patch) => {
    const docs = (form.value.documents || []).map((doc, i) => (i === idx ? { ...doc, ...patch } : doc));
    form.value = { ...form.value, documents: docs };
};

const removeDocument = (idx) => {
    const docs = (form.value.documents || []).filter((_, i) => i !== idx);
    form.value = { ...form.value, documents: docs };
};

const openAddDialog = () => {
    dialogMode.value = 'add';
    editingIndex.value = null;
    resetDraft();
    draftDocument.groupKey = createGroupKey();
    showDialog.value = true;
};

const openEditDialog = (doc, idx) => {
    dialogMode.value = 'edit';
    editingIndex.value = idx;
    draftDocument.type = doc?.type || 'Document';
    draftDocument.libelle = doc?.libelle || '';
    draftDocument.urls = Array.isArray(doc?.urls) ? [...doc.urls] : doc?.url ? [doc.url] : [];
    draftDocument.files = Array.isArray(doc?.files) ? [...doc.files] : doc?.file ? [doc.file] : [];
    draftDocument.groupKey = doc?.groupKey || createGroupKey();
    showDialog.value = true;
};

const saveDraft = () => {
    if (draftUrlInput.value) {
        draftDocument.urls = [...draftDocument.urls, draftUrlInput.value].filter(Boolean);
        draftUrlInput.value = '';
    }

    const payload = {
        type: draftDocument.type,
        libelle: draftDocument.libelle,
        urls: [...draftDocument.urls],
        files: [...draftDocument.files],
        groupKey: draftDocument.groupKey || createGroupKey()
    };

    if (dialogMode.value === 'edit' && editingIndex.value !== null) {
        updateDocument(editingIndex.value, payload);
    } else {
        addDocument(payload);
    }

    showDialog.value = false;
    resetDraft();
};

const onDraftFilesSelect = (event) => {
    const files = event.files || [];
    draftDocument.files = [...draftDocument.files, ...files];
    if (!draftDocument.libelle && files.length) {
        draftDocument.libelle = files[0].name;
    }
};

const onDraftFilesClear = () => {
    draftDocument.files = [];
};

const removeDraftUrl = (idx) => {
    draftDocument.urls = draftDocument.urls.filter((_, i) => i !== idx);
};

const removeDraftFile = (idx) => {
    draftDocument.files = draftDocument.files.filter((_, i) => i !== idx);
};

const addDraftUrl = () => {
    if (!draftUrlInput.value) return;
    draftDocument.urls = [...draftDocument.urls, draftUrlInput.value].filter(Boolean);
    draftUrlInput.value = '';
};

const buildEntries = (doc, docIndex) => {
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    const files = Array.isArray(doc?.files) ? doc.files : doc?.file ? [doc.file] : [];
    const entries = [];

    urls.forEach((url, fileIndex) => {
        const resolved = resolveUrl(url);
        const extension = getExtension(url);
        const isImage = isImageExtension(extension);
        const name = url?.split('/').pop() || 'fichier';
        entries.push({
            entryKey: `${docIndex}-url-${fileIndex}`,
            isImage,
            previewSrc: resolved,
            extension,
            icon: getDocumentIcon(extension),
            downloadUrl: resolved,
            fileName: name
        });
    });

    files.forEach((file, fileIndex) => {
        const extension = getExtension(file?.name || '');
        const isImage = file?.type ? file.type.startsWith('image/') : isImageExtension(extension);
        const preview = getFileUrl(file);
        entries.push({
            entryKey: `${docIndex}-file-${fileIndex}`,
            isImage,
            previewSrc: preview,
            extension,
            icon: getDocumentIcon(extension),
            downloadUrl: preview,
            fileName: file?.name || 'fichier'
        });
    });

    return entries;
};

const documentsView = computed(() =>
    (form.value.documents || []).map((doc, index) => ({
        doc,
        title: getDocTitle(doc, index),
        description: getDocDescription(doc),
        entries: buildEntries(doc, index)
    }))
);

const galleryItems = computed(() =>
    documentsView.value.flatMap((item, docIndex) =>
        item.entries.map((entry) => ({
            ...entry,
            docIndex,
            title: item.title,
            description: item.description
        }))
    )
);

const galleryIndexMap = computed(() => {
    const map = new Map();
    galleryItems.value.forEach((item, idx) => {
        map.set(item.entryKey, idx);
    });
    return map;
});

const galleriaPt = {
    mask: { class: 'bg-surface-950/90 backdrop-blur-sm' },
    content: { class: 'flex items-center justify-center' },
    itemsContainer: { class: 'flex-1' },
    item: { class: 'flex items-center justify-center' },
    closeButton: { class: 'text-white hover:text-primary-200' },
    closeIcon: { class: 'text-white' },
    prevButton: { class: 'absolute left-4 top-1/2 -translate-y-1/2 z-10 text-white/90 hover:text-white' },
    nextButton: { class: 'absolute right-4 top-1/2 -translate-y-1/2 z-10 text-white/90 hover:text-white' },
    prevIcon: { class: 'text-white/90' },
    nextIcon: { class: 'text-white/90' }
};

const responsiveOptions = [
    { breakpoint: '1200px', numVisible: 7 },
    { breakpoint: '992px', numVisible: 5 },
    { breakpoint: '768px', numVisible: 3 },
    { breakpoint: '576px', numVisible: 1 }
];

const openPreviewByKey = (entryKey) => {
    const idx = galleryIndexMap.value.get(entryKey);
    if (idx === undefined) return;
    const selected = galleryItems.value[idx];
    if (!selected) return;
    const sameDocItems = galleryItems.value.filter((item) => item.docIndex === selected.docIndex);
    previewItems.value = sameDocItems.map((item) => ({ ...item }));
    const nextIndex = sameDocItems.findIndex((item) => item.entryKey === entryKey);
    previewIndex.value = nextIndex >= 0 ? nextIndex : 0;
    previewVisible.value = true;
};

const goPrevPreview = () => {
    if (!previewItems.value.length) return;
    previewIndex.value = (previewIndex.value - 1 + previewItems.value.length) % previewItems.value.length;
};

const goNextPreview = () => {
    if (!previewItems.value.length) return;
    previewIndex.value = (previewIndex.value + 1) % previewItems.value.length;
};

onBeforeUnmount(() => {
    for (const url of fileUrlCache.values()) {
        URL.revokeObjectURL(url);
    }
    fileUrlCache.clear();
});
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-images text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Images & Documents</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Pieces jointes du dossier</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button icon="pi pi-plus" label="Ajouter" size="small" class="rounded-xl" @click="openAddDialog" />
                <Button
                    label="Sauvegarder"
                    icon="pi pi-save"
                    :loading="saving"
                    @click="emit('save')"
                    class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
                />
            </div>
        </div>

        <div class="space-y-4">
            <div v-if="!galleryItems.length" class="text-sm text-surface-500 dark:text-surface-400">
                Aucun document ajoute.
            </div>

            <div v-for="(item, idx) in documentsView" :key="idx" class="rounded-2xl border border-surface-200/70 dark:border-surface-700/70 bg-surface-50 dark:bg-surface-800/30 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h4 class="text-base font-semibold text-surface-900 dark:text-surface-100">{{ item.title }}</h4>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 break-words">{{ item.description }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(item.doc, idx)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="removeDocument(idx)" />
                    </div>
                </div>

                <div v-if="item.entries.length" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button
                        v-for="entry in item.entries"
                        :key="entry.entryKey"
                        type="button"
                        class="group relative flex h-20 sm:h-24 md:h-28 lg:h-32 items-center justify-center overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900/40 transition-shadow hover:shadow-md"
                        @click="openPreviewByKey(entry.entryKey)"
                    >
                        <img
                            v-if="entry.isImage && entry.previewSrc"
                            :src="entry.previewSrc"
                            :alt="item.title"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex flex-col items-center justify-center gap-2 text-surface-500 dark:text-surface-400">
                            <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                                <i :class="['pi', entry.icon, 'text-lg']"></i>
                            </div>
                            <span class="text-[10px] sm:text-[11px] uppercase tracking-wide">
                                {{ entry.extension || 'file' }}
                            </span>
                        </div>
                        <div class="absolute inset-0 bg-primary-500/0 transition-colors group-hover:bg-primary-500/10"></div>
                    </button>
                </div>
                <div v-else class="mt-4 text-xs text-surface-500 dark:text-surface-400">
                    Aucun fichier attache.
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showDialog" modal :header="dialogTitle" class="w-full max-w-2xl">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                    <Select
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :modelValue="draftDocument.type"
                        @update:modelValue="(v) => (draftDocument.type = v)"
                        class="w-full"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Titre</label>
                    <InputText v-model="draftDocument.libelle" class="w-full" />
                </div>
                <div class="space-y-2 lg:col-span-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Fichiers</label>
                    <FileUpload
                        mode="basic"
                        chooseLabel="Selectionner"
                        :customUpload="true"
                        :multiple="true"
                        class="mt-2"
                        @select="onDraftFilesSelect"
                        @clear="onDraftFilesClear"
                    />
                    <div v-if="draftDocument.files.length" class="mt-3 space-y-2">
                        <div
                            v-for="(file, fileIdx) in draftDocument.files"
                            :key="fileIdx"
                            class="flex items-center justify-between rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-xs text-surface-600 dark:text-surface-300"
                        >
                            <span class="truncate">{{ file.name }}</span>
                            <Button icon="pi pi-times" text rounded size="small" @click="removeDraftFile(fileIdx)" />
                        </div>
                    </div>
                </div>
                <div class="space-y-2 lg:col-span-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Lien externe</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <InputText v-model="draftUrlInput" class="flex-1 min-w-[200px]" />
                        <Button label="Ajouter" icon="pi pi-plus" size="small" @click="addDraftUrl" />
                    </div>
                    <div v-if="draftDocument.urls.length" class="mt-3 space-y-2">
                        <div
                            v-for="(url, urlIdx) in draftDocument.urls"
                            :key="urlIdx"
                            class="flex items-center justify-between rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-xs text-surface-600 dark:text-surface-300"
                        >
                            <span class="truncate">{{ url }}</span>
                            <Button icon="pi pi-times" text rounded size="small" @click="removeDraftUrl(urlIdx)" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <Button label="Annuler" text @click="showDialog = false" />
                    <Button label="Enregistrer" icon="pi pi-check" @click="saveDraft" />
                </div>
            </template>
        </Dialog>

        <Galleria
            v-model:visible="previewVisible"
            v-model:activeIndex="previewIndex"
            :value="previewItems"
            :responsiveOptions="responsiveOptions"
            :numVisible="7"
            :fullScreen="true"
            :showThumbnails="false"
            :showItemNavigators="false"
            :circular="true"
            :pt="galleriaPt"
            containerClass="w-screen h-screen"
        >
            <template #item="{ item }">
                <div class="relative flex h-full w-full flex-col items-center justify-center gap-6 bg-transparent px-6 py-8">
                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 ml-3 inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white/20"
                        @click.stop="goPrevPreview"
                    >
                        <i class="pi pi-chevron-left"></i>
                    </button>
                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 mr-3 inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white/20"
                        @click.stop="goNextPreview"
                    >
                        <i class="pi pi-chevron-right"></i>
                    </button>
                    <div class="flex flex-1 items-center justify-center">
                        <img
                            v-if="item.isImage && item.previewSrc"
                            :src="item.previewSrc"
                            :alt="item.title"
                            class="max-h-[70vh] w-auto max-w-[90vw] rounded-2xl shadow-xl"
                        />
                        <div v-else class="flex flex-col items-center justify-center text-center text-white/90">
                            <div class="h-28 w-28 rounded-3xl bg-white/10 flex items-center justify-center">
                                <i :class="['pi', item.icon, 'text-4xl text-white']"></i>
                            </div>
                        </div>
                    </div>
                    <div class="text-center text-white/90">
                        <div class="text-lg font-semibold">{{ item.fileName || item.title }}</div>
                        <div class="text-sm text-white/70 mt-1 max-w-xl break-words">{{ item.description }}</div>
                        <a
                            v-if="item.downloadUrl"
                            :href="item.downloadUrl"
                            download
                            class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm text-white hover:bg-white/10"
                        >
                            <i class="pi pi-download"></i>
                            Telecharger
                        </a>
                    </div>
                </div>
            </template>
        </Galleria>
    </div>
</template>
