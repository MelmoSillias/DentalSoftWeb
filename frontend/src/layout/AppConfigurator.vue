<script setup>
import { computed } from 'vue';
import { useAppearanceSettings } from '@/composables/useAppearanceSettings';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';

const { layoutConfig, isDarkTheme, preset, presetOptions, menuMode, menuModeOptions, themeOptions, fontFamilyOptions, fontSizeOptions, primaryColors, surfaces, themeMode, fontFamily, fontSize, updateColors, onPresetChange, onMenuModeChange } =
    useAppearanceSettings();

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false
    },
    showMenuMode: {
        type: Boolean,
        default: true
    },
    showPresets: {
        type: Boolean,
        default: true
    },
    showSurface: {
        type: Boolean,
        default: true
    },
    showPrimary: {
        type: Boolean,
        default: true
    },
    showThemeMode: {
        type: Boolean,
        default: true
    },
    showTypography: {
        type: Boolean,
        default: true
    }
});

const panelClass = computed(() => {
    if (props.embedded) {
        return 'config-panel embedded';
    }

    return 'config-panel hidden absolute top-[3.25rem] right-0 w-64 p-4 bg-surface-0 dark:bg-surface-900 border border-surface rounded-border origin-top shadow-[0px_3px_5px_rgba(0,0,0,0.02),0px_0px_2px_rgba(0,0,0,0.05),0px_1px_4px_rgba(0,0,0,0.08)]';
});
</script>

<template>
    <div :class="panelClass">
        <div class="flex flex-col gap-4">
            <div v-if="showThemeMode" :id="embedded ? 'appearance-theme' : null">
                <span class="text-sm text-muted-color font-semibold">Theme</span>
                <div class="pt-2">
                    <SelectButton v-model="themeMode" :options="themeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" class="w-full" />
                </div>
            </div>
            <div v-if="showPrimary" :id="embedded ? 'appearance-primary' : null">
                <span class="text-sm text-muted-color font-semibold">Primary</span>
                <div class="pt-2 flex gap-2 flex-wrap justify-between">
                    <button
                        v-for="primaryColor of primaryColors"
                        :key="primaryColor.name"
                        type="button"
                        :title="primaryColor.name"
                        @click="updateColors('primary', primaryColor)"
                        :class="['border-none w-5 h-5 rounded-full p-0 cursor-pointer outline-none outline-offset-1', { 'outline-primary': layoutConfig.primary === primaryColor.name }]"
                        :style="{ backgroundColor: `${primaryColor.name === 'noir' ? 'var(--text-color)' : primaryColor.palette['500']}` }"
                    ></button>
                </div>
            </div>
            <div v-if="showSurface" :id="embedded ? 'appearance-surface' : null">
                <span class="text-sm text-muted-color font-semibold">Surface</span>
                <div class="pt-2 flex gap-2 flex-wrap justify-between">
                    <button
                        v-for="surface of surfaces"
                        :key="surface.name"
                        type="button"
                        :title="surface.name"
                        @click="updateColors('surface', surface)"
                        :class="[
                            'border-none w-5 h-5 rounded-full p-0 cursor-pointer outline-none outline-offset-1',
                            { 'outline-primary': layoutConfig.surface ? layoutConfig.surface === surface.name : isDarkTheme ? surface.name === 'zinc' : surface.name === 'slate' }
                        ]"
                        :style="{ backgroundColor: `${surface.palette['500']}` }"
                    ></button>
                </div>
            </div>
            <div v-if="showPresets" class="flex flex-col gap-2" :id="embedded ? 'appearance-presets' : null">
                <span class="text-sm text-muted-color font-semibold">Presets</span>
                <SelectButton v-model="preset" @change="onPresetChange" :options="presetOptions" :allowEmpty="false" />
            </div>
            <div v-if="showTypography" class="flex flex-col gap-2" :id="embedded ? 'appearance-font-family' : null">
                <span class="text-sm text-muted-color font-semibold">Police</span>
                <Select v-model="fontFamily" :options="fontFamilyOptions" class="w-full" />
            </div>
            <div v-if="showTypography" class="flex flex-col gap-2" :id="embedded ? 'appearance-font-size' : null">
                <span class="text-sm text-muted-color font-semibold">Taille texte</span>
                <SelectButton v-model="fontSize" :options="fontSizeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
            </div>
            <div v-if="showMenuMode" class="flex flex-col gap-2">
                <span class="text-sm text-muted-color font-semibold">Menu Mode</span>
                <SelectButton v-model="menuMode" @change="onMenuModeChange" :options="menuModeOptions" :allowEmpty="false" optionLabel="label" optionValue="value" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.config-panel.embedded {
    position: static;
    width: 100%;
    padding: 0;
    border: none;
    box-shadow: none;
    background: transparent;
}
</style>
