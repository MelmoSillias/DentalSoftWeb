export const GUIDED_TOUR_START_EVENT = 'orodent:guided-tour:start';

const supportedRoutes = new Set(['patients-liste', 'consultations-cards']);

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
