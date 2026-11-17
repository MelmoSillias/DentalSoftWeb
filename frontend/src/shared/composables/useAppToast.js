import { useToast } from 'primevue/usetoast';

const DEFAULT_LIFE_BY_SEVERITY = {
    success: 3000,
    info: 3000,
    warn: 3000,
    secondary: 3000,
    contrast: 3000,
    error: 6000
};

/**
 * Toast helper with readable defaults: error stays 6s, others 3s.
 */
export function useAppToast() {
    const toast = useToast();

    function add(message = {}) {
        const severity = message.severity || 'info';
        const next = { ...message, severity };

        if (!next.sticky && next.life == null) {
            next.life = DEFAULT_LIFE_BY_SEVERITY[severity] ?? 3000;
        }

        return toast.add(next);
    }

    return {
        add,
        remove: (...args) => toast.remove(...args),
        removeGroup: (...args) => toast.removeGroup(...args),
        removeAllGroups: (...args) => toast.removeAllGroups(...args)
    };
}
