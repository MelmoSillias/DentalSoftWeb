import { computed, ref } from 'vue';

const STORAGE_KEY = 'patient_portal_theme';
const isDark = ref(false);
let initialized = false;

export function useTheme() {
    function initTheme() {
        if (initialized) {
            return;
        }

        const stored = localStorage.getItem(STORAGE_KEY);
        isDark.value = stored === 'dark';
        applyTheme();
        initialized = true;
    }

    function applyTheme() {
        document.documentElement.classList.toggle('app-dark', isDark.value);
        localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
    }

    function toggleTheme() {
        isDark.value = !isDark.value;
        applyTheme();
    }

    return {
        isDark: computed(() => isDark.value),
        initTheme,
        toggleTheme
    };
}
