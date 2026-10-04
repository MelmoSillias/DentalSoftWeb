<script setup>
import Button from 'primevue/button';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => []
    },
    modelValue: {
        type: String,
        default: ''
    },
    mode: {
        type: String,
        default: 'tabs' // 'tabs' or 'sidebar'
    },
    initKey: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['update:modelValue']);

const active = computed({
    get: () => props.modelValue || props.sections?.[0]?.id || '',
    set: (val) => emit('update:modelValue', val)
});

const select = (id) => emit('update:modelValue', id);

const openSections = ref(new Set());
const initialized = ref(false);
const sectionRefs = ref({});
let observer = null;

const setSectionRef = (id, el) => {
    if (el) sectionRefs.value[id] = el;
};

const setInitialOpen = () => {
    const next = new Set((props.sections || []).filter((s) => !s?.filled).map((s) => s.id));
    openSections.value = next;
    initialized.value = true;
};

const isOpen = (id) => openSections.value.has(id);

const toggleSection = (id) => {
    if (openSections.value.has(id)) openSections.value.delete(id);
    else openSections.value.add(id);
    openSections.value = new Set(openSections.value);
};

const getStatusIcon = (status) => {
    switch (status) {
        case 'saving':
            return 'pi pi-spin pi-spinner';
        case 'dirty':
            return 'pi pi-exclamation-circle';
        case 'saved':
            return 'pi pi-check-circle';
        default:
            return 'pi pi-info-circle';
    }
};

const scrollToSection = (id) => {
    const el = sectionRefs.value[id];
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    emit('update:modelValue', id);
};

const setupObserver = () => {
    if (observer) observer.disconnect();
    const targets = Object.values(sectionRefs.value);
    if (!targets.length) return;
    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (visible?.target?.dataset?.sectionId) emit('update:modelValue', visible.target.dataset.sectionId);
        },
        { rootMargin: '-20% 0px -60% 0px', threshold: [0.1, 0.5, 0.9] }
    );
    targets.forEach((target) => observer.observe(target));
};

watch(
    () => props.initKey,
    () => {
        initialized.value = false;
        setInitialOpen();
    }
);

watch(
    () => props.sections,
    () => {
        if (!initialized.value && props.sections?.length) setInitialOpen();
    },
    { deep: true, immediate: true }
);

watch(
    () => [props.mode, props.initKey, props.sections?.length],
    async () => {
        if (props.mode !== 'sidebar') {
            if (observer) observer.disconnect();
            return;
        }
        await nextTick();
        setupObserver();
    },
    { immediate: true }
);

function completedSections() {
    return props.sections.filter((s) => s.status === 'saved').length;
}

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});
</script>

<!-- SectionSwitcher.vue -->
<template>
    <div class="medical-form-ui w-full">
        <!-- ===== MODE TABS ===== -->
        <template v-if="mode === 'tabs'">
            <div class="medical-form-nav">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    type="button"
                    class="medical-form-nav__item"
                    :class="{ 'is-active': active === section.id, 'opacity-50': section.disabled }"
                    :disabled="section.disabled"
                    @click="select(section.id)"
                >
                    <i v-if="section.icon" :class="section.icon"></i>
                    <span>{{ section.label }}</span>
                </button>
            </div>

            <div class="w-full p-3 md:p-4">
                <slot :name="active"></slot>
            </div>
        </template>

        <!-- ===== MODE SIDEBAR ===== -->
        <template v-else>
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_16rem] gap-3 w-full p-3 md:p-4">
                <!-- ===== CONTENT ===== -->
                <div class="w-full space-y-3">
                    <template v-for="section in sections" :key="section.id">
                        <section
                            :id="section.id"
                            :data-section-id="section.id"
                            :ref="(el) => setSectionRef(section.id, el)"
                            class="medical-form-block"
                        >
                            <!-- Header -->
                            <div class="medical-form-block__header">
                                <button
                                    type="button"
                                    class="flex items-center gap-2 text-left"
                                    @click="toggleSection(section.id)"
                                >
                                    <i v-if="section.icon" class="pi text-primary" :class="section.icon" />
                                    <span class="medical-form-block__title">{{ section.label }}</span>
                                    <i class="pi text-sm" :class="isOpen(section.id) ? 'pi-chevron-down' : 'pi-chevron-right'" />
                                </button>

                                <div class="flex items-center gap-3">
                                    <div
                                        v-if="section.statusLabel"
                                        class="flex items-center justify-center w-8 h-8 rounded-full border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-700 text-surface-500 dark:text-surface-400 transition-colors"
                                        :class="{
                                            'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400': section.status === 'saved',
                                            'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800 text-amber-600 dark:text-amber-400': section.status === 'dirty',
                                            'bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800 text-teal-600 dark:text-teal-400': section.status === 'saving'
                                        }"
                                        :title="section.statusLabel"
                                        role="img"
                                    >
                                        <i class="pi animate-spin" :class="getStatusIcon(section.status)" />
                                    </div>

                                    <Button
                                        v-if="section.onSave"
                                        label="Enregistrer"
                                        icon="pi pi-save"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        :loading="section.saving"
                                        :disabled="section.saveDisabled || section.saving"
                                        @click.stop="section.onSave"
                                        class="hidden sm:inline-flex"
                                    />
                                </div>
                            </div>

                            <!-- Body -->
                            <div v-show="isOpen(section.id)" class="medical-form-block__body" :class="{ 'opacity-50': section.disabled }">
                                <slot :name="section.id"></slot>
                            </div>
                        </section>
                    </template>
                </div>

                <!-- ===== SIDEBAR NAVIGATION ===== -->
                <aside class="hidden lg:block sticky top-4 h-fit max-h-[calc(100vh-6rem)] overflow-y-auto">
                    <div class="medical-form-block">
                        <div class="medical-form-block__header">
                            <h5 class="medical-form-block__title"><i class="pi pi-list mr-2"></i>Navigation</h5>
                        </div>

                        <nav class="medical-form-block__body flex flex-col gap-0.5 !py-2">
                            <button
                                v-for="section in sections"
                                :key="section.id"
                                type="button"
                                class="medical-form-nav__item w-full justify-start rounded-md border-b-0 px-2"
                                :class="{ 'is-active': active === section.id }"
                                @click="scrollToSection(section.id)"
                            >
                                <span class="flex-1 text-left">{{ section.label }}</span>
                                <span
                                    v-if="section.status && section.status !== 'readonly'"
                                    class="medical-form-nav__dot"
                                    :class="{
                                        'bg-amber-500': section.status === 'dirty',
                                        'bg-primary': section.status === 'saving'
                                    }"
                                />
                            </button>
                        </nav>

                        <div class="medical-form-block__body border-t border-surface-200 dark:border-surface-700">
                            <div class="text-xs font-medium text-surface-500 dark:text-surface-400 mb-2">Progression</div>
                            <div class="w-full h-1.5 bg-surface-100 dark:bg-surface-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-primary rounded-full transition-all duration-500"
                                    :style="{ width: `${sections.length ? (completedSections() / sections.length) * 100 : 0}%` }"
                                />
                            </div>
                            <p class="mt-2 text-xs text-surface-500 dark:text-surface-400">
                                {{ completedSections() }} / {{ sections.length }}
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </template>
    </div>
</template>
