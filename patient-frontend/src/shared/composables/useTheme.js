import { computed, ref } from 'vue';

const STORAGE_KEY = 'patient_portal_theme';
const isDark = ref(false);
let initialized = false;
let themeTransition;
let fallbackTimer;

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
        if (themeTransition) return;

        const update = () => {
            isDark.value = !isDark.value;
            applyTheme();
        };
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            update();
            return;
        }

        if (document.startViewTransition) {
            themeTransition = document.startViewTransition(update);
            themeTransition.finished.catch(() => {}).finally(() => {
                themeTransition = undefined;
            });
        } else {
            clearTimeout(fallbackTimer);
            document.documentElement.classList.add('theme-is-switching');
            update();
            fallbackTimer = setTimeout(() => {
                document.documentElement.classList.remove('theme-is-switching');
            }, 360);
        }
    }

    return {
        isDark: computed(() => isDark.value),
        initTheme,
        toggleTheme
    };
}
