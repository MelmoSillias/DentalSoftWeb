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
    <div class="w-full" :class="mode === 'tabs' ? 'block' : 'block'">
        <!-- ===== MODE TABS ===== -->
        <template v-if="mode === 'tabs'">
            <div class="flex flex-wrap gap-2 px-6 pt-6">
                <Button
                    v-for="section in sections"
                    :key="section.id"
                    :label="section.label"
                    :severity="active === section.id ? 'secondary' : 'help'"
                    :outlined="active === section.id"
                    :disabled="section.disabled"
                    @click="select(section.id)"
                    class="rounded-xl px-4 py-2.5 font-medium transition-all hover:shadow-md"
                />
            </div>

            <div class="p-6 w-full">
                <slot :name="active"></slot>
            </div>
        </template>

        <!-- ===== MODE SIDEBAR ===== -->
        <template v-else>
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 w-full p-6">
                <!-- ===== CONTENT ===== -->
                <div class="w-full space-y-4">
                    <template v-for="(section, index) in sections" :key="section.id">
                        <section
                            :id="section.id"
                            :data-section-id="section.id"
                            :ref="(el) => setSectionRef(section.id, el)"
                            class="w-full rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/60 p-5 shadow-sm hover:shadow-md transition-shadow duration-300"
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between gap-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                                <button
                                    type="button"
                                    class="flex items-center gap-3 text-surface-900 dark:text-surface-100 font-semibold text-lg hover:text-primary-600 dark:hover:text-primary-400 transition-colors group"
                                    @click="toggleSection(section.id)"
                                >
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-500/10 dark:bg-primary-500/20 group-hover:bg-primary-500/20 transition-colors">
                                        <i class="pi text-primary-500" :class="section.icon || 'pi-file'" />
                                    </div>
                                    <span>{{ section.label }}</span>
                                    <i class="pi transition-transform duration-300 text-surface-400" :class="isOpen(section.id) ? 'pi-chevron-down' : 'pi-chevron-right'" />
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
                                        outlined
                                        :loading="section.saving"
                                        :disabled="section.saveDisabled || section.saving"
                                        @click.stop="section.onSave"
                                        class="hidden sm:inline-flex rounded-lg px-4 py-2 hover:shadow-sm transition-all"
                                    />
                                </div>
                            </div>

                            <!-- Body -->
                            <div v-show="isOpen(section.id)" class="mt-4 animate-fade-in" :class="{ 'opacity-50': section.disabled }">
                                <slot :name="section.id"></slot>
                            </div>
                        </section>
                    </template>
                </div>

                <!-- ===== SIDEBAR NAVIGATION ===== -->
                <aside class="hidden lg:block sticky top-16 h-fit max-h-[calc(100vh-6rem)] overflow-y-auto">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/60 p-4 shadow-sm">
                        <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                            <h5 class="font-semibold text-surface-900 dark:text-surface-100"><i class="pi pi-list text-primary-500"></i> Navigation rapide</h5>
                        </div>

                        <nav class="flex flex-col gap-1">
                            <a
                                v-for="section in sections"
                                :key="section.id"
                                href="#"
                                @click.prevent="scrollToSection(section.id)"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100 hover:bg-surface-50 dark:hover:bg-surface-700/50 transition-all group"
                                :class="{
                                    'font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 border-l-4 border-primary-500': active === section.id
                                }"
                            >
                                <div class="flex items-center justify-center w-6 h-6 rounded-md bg-surface-100 dark:bg-surface-700 group-hover:bg-surface-200 dark:group-hover:bg-surface-600 transition-colors">
                                    <i class="pi text-xs" :class="section.icon || 'pi-circle'" />
                                </div>
                                <span class="flex-1">{{ section.label }}</span>
                                <div
                                    v-if="section.status"
                                    class="w-2 h-2 rounded-full"
                                    :class="{
                                        'bg-emerald-500': section.status === 'saved',
                                        'bg-amber-500 animate-pulse': section.status === 'dirty',
                                        'bg-teal-500 animate-spin': section.status === 'saving'
                                    }"
                                />
                            </a>
                        </nav>

                        <!-- Progress Indicator -->
                        <div class="mt-6 pt-4 border-t border-surface-100 dark:border-surface-700">
                            <div class="text-xs font-medium text-surface-500 dark:text-surface-400 mb-2">Progression</div>
                            <div class="w-full h-2 bg-surface-100 dark:bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-primary-500 to-primary-600 rounded-full transition-all duration-500" :style="{ width: `${(completedSections / sections.length) * 100}%` }" />
                            </div>
                            <div class="flex justify-between text-xs text-surface-600 dark:text-surface-400 mt-2">
                                <span>{{ completedSections() }} section(s) complétée(s)</span>
                                <span>{{ sections.length }} total</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </template>
    </div>
</template>
