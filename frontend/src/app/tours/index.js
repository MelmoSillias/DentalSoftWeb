export const GUIDED_TOUR_START_EVENT = 'orodent:guided-tour:start';

export { buildTourStepsForRoute, getRegistryForRoute, getSupportedTourRoutes, getTaskForRoute, getTaskMenuItemsForRoute, getTasksForRoute, resolveRouteMockScenario } from '@/app/tours/registry/index.js';

import { getSupportedTourRoutes } from '@/app/tours/registry/index.js';

const supportedRoutes = new Set(getSupportedTourRoutes());

export function isGuidedTourRoute(routeName) {
    return Boolean(routeName && supportedRoutes.has(routeName));
}

export function requestGuidedTourStart(routeName, { taskId = 'overview', variantId = null } = {}) {
    if (typeof window === 'undefined' || !routeName) {
        return;
    }

    window.dispatchEvent(
        new CustomEvent(GUIDED_TOUR_START_EVENT, {
            detail: {
                routeName,
                taskId,
                variantId
            }
        })
    );
}
