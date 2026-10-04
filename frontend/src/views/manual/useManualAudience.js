import { computed, inject, provide, ref } from 'vue';

const AUDIENCE_KEY = Symbol('manualAudience');

/** Reader profiles of the manual. `accueil` covers ROLE_RECEPTION, ROLE_RECEPTIONNISTE and ROLE_SECRETAIRE. */
export const AUDIENCES = [
    { value: 'all', label: 'Tout le manuel' },
    { value: 'admin', label: 'Administrateur' },
    { value: 'accueil', label: 'Accueil' },
    { value: 'medecin', label: 'Médecin' }
];

export const ROLE_LABELS = {
    admin: 'Admin',
    accueil: 'Accueil',
    medecin: 'Médecin'
};

/**
 * Picks the reader profile matching the logged-in account.
 * @param {string[]} roles
 */
export function audienceFromRoles(roles = []) {
    if (roles.includes('ROLE_ADMIN')) return 'all';
    if (roles.includes('ROLE_MEDECIN')) return 'medecin';
    if (roles.some((role) => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'].includes(role))) return 'accueil';
    return 'all';
}

function matches(audience, roles) {
    return audience === 'all' || !roles?.length || roles.includes(audience);
}

export function provideManualAudience(initial = 'all') {
    const audience = ref(initial);
    const isVisible = (roles) => matches(audience.value, roles);
    const label = computed(() => AUDIENCES.find((item) => item.value === audience.value)?.label ?? '');
    const state = { audience, isVisible, label };
    provide(AUDIENCE_KEY, state);
    return state;
}

export function useManualAudience() {
    return inject(AUDIENCE_KEY, {
        audience: ref('all'),
        isVisible: () => true,
        label: computed(() => '')
    });
}
