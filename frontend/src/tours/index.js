export const GUIDED_TOUR_START_EVENT = 'orodent:guided-tour:start';

const supportedRoutes = new Set([
    'dashboard',
    'patients-liste',
    'consultations-cards',
    'consultations-table',
    'consultations-form',
    'patients-dossier',
    'agenda-rendezvous',
    'agenda-evenements',
    'caisse',
    'rapports',
    'administration-consommables',
    'administration-salles',
    'administration-finances',
    'administration-utilisateurs',
    'administration-gestionrh',
    'administration-employee-details',
    'settings-apparence'
]);

export function isGuidedTourRoute(routeName) {
    return Boolean(routeName && supportedRoutes.has(routeName));
}

export function requestGuidedTourStart(routeName) {
    if (typeof window === 'undefined' || !routeName) {
        return;
    }

    window.dispatchEvent(
        new CustomEvent(GUIDED_TOUR_START_EVENT, {
            detail: { routeName }
        })
    );
}
