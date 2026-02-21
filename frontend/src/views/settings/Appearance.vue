<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import ColorPicker from 'primevue/colorpicker';
import Select from 'primevue/select';
import Slider from 'primevue/slider';
import Button from 'primevue/button';

const toast = useToast();

// const primaryColor = ref('#0ea5a4');
// const theme = ref('system'); // 'light' | 'dark' | 'system'
const fontSize = ref(12); // px

// const themeOptions = [
//     { label: 'Système (par défaut)', value: 'system' },
//     { label: 'Clair', value: 'light' },
//     { label: 'Sombre', value: 'dark' }
// ];

function loadSettings() {
    const saved = localStorage.getItem('appearanceSettings');
    if (saved) {
        try {
            const s = JSON.parse(saved);
            // if (s.primaryColor) primaryColor.value = s.primaryColor;
            // if (s.theme) theme.value = s.theme;
            if (s.fontSize) fontSize.value = s.fontSize;
        } catch (e) {
            console.warn('Impossible de parser les réglages d\'apparence', e);
        }
    }
}

function applySettings() {
    // Couleur principale via variable CSS
    document.documentElement.style.setProperty('--primary-color', primaryColor.value);

    // Thème
    if (theme.value === 'system') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', theme.value);
    }

    // Taille de police globale (px)
    document.documentElement.style.fontSize = fontSize.value + 'px';
}

function saveSettings() {
    const s = {
        primaryColor: primaryColor.value,
        theme: theme.value,
        fontSize: fontSize.value
    };
    localStorage.setItem('appearanceSettings', JSON.stringify(s));
    applySettings();
    toast.add({ severity: 'success', summary: 'Paramètres enregistrés', detail: 'Apparence mise à jour', life: 2500 });
}

function resetSettings() {
    // primaryColor.value = '#0ea5a4';
    // theme.value = 'system';
    fontSize.value = 12;
    saveSettings();
}

onMounted(() => {
    loadSettings();
    applySettings();
});

</script>

<template>
    <div class="p-4">
        <h2 class="text-xl font-semibold mb-4">Apparence & Thème</h2>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Couleur principale</label>
                <ColorPicker v-model="primaryColor" inline format="hex" />
                <p class="text-xs text-gray-500 mt-2">Choisissez la couleur principale de l'application.</p>
            </div>

            <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Thème</label>
                <Select v-model="theme" :options="themeOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner" />
                <p class="text-xs text-gray-500 mt-2">Forcer le thème clair ou sombre, ou suivre le système.</p>
            </div>

            <div class="card p-4 md:col-span-2">
                <label class="block text-sm font-medium mb-2">Taille du texte</label>
                <div class="flex items-center gap-3">
                    <Slider v-model="fontSize" :min=12 :max=20 step="1" />
                    <div class="w-16 text-right">{{ fontSize }}px</div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Ajuste la taille de base du texte de l'interface.</p>
            </div>

            <div class="md:col-span-2 p-4">
                <label class="block text-sm font-medium mb-2">Aperçu</label>
                <div class="p-4 rounded" :style="{ background: 'var(--surface-card, #fff)', border: '1px solid #e5e7eb' }">
                    <h3 :style="{ color: primaryColor }">Titre d'aperçu</h3>
                    <p :style="{ fontSize: fontSize + 'px' }">Ceci est un aperçu du texte avec la taille sélectionnée.</p>
                    <Button class="mt-3" :style="{ backgroundColor: primaryColor, borderColor: primaryColor, color: '#fff' }" label="Bouton d'exemple" />
                </div>
            </div>

            <div class="md:col-span-2 flex gap-3 mt-2">
                <Button label="Enregistrer" class="p-button-primary" @click="saveSettings" />
                <Button label="Réinitialiser" class="p-button-secondary" @click="resetSettings" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.card { background: var(--surface-card, #fff); border-radius: 6px; }
</style>
