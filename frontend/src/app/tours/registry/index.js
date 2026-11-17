import { patientsListeRegistry } from '@/app/tours/patientsListTour.js';
import { patientsDossierRegistry } from '@/app/tours/patientsDossierTour.js';
import { consultationsCardsRegistry } from '@/app/tours/consultationsCardsTour.js';
import { consultationsTableRegistry } from '@/app/tours/consultationsTableTour.js';
import { consultationsFormRegistry } from '@/app/tours/consultationsFormTour.js';
import { caisseRegistry } from '@/app/tours/caisseTour.js';
import { dashboardRegistry } from '@/app/tours/dashboardTour.js';
import { agendaRendezvousRegistry } from '@/app/tours/agendaRendezvousTour.js';
import { agendaEvenementsRegistry } from '@/app/tours/agendaEvenementsTour.js';
import { rapportsRegistry } from '@/app/tours/rapportsTour.js';
import { administrationConsumablesRegistry } from '@/app/tours/administrationConsumablesTour.js';
import { administrationSallesRegistry } from '@/app/tours/administrationSallesTour.js';
import { administrationNotificationsRegistry } from '@/app/tours/administrationNotificationsTour.js';
import { administrationFinancesRegistry } from '@/app/tours/administrationFinancesTour.js';
import { administrationUtilisateursRegistry } from '@/app/tours/administrationUsersTour.js';
import { administrationGestionrhRegistry } from '@/app/tours/administrationGestionRHTour.js';
import { administrationEmployeeDetailsRegistry } from '@/app/tours/administrationEmployeeDetailsTour.js';
import { settingsApparenceRegistry } from '@/app/tours/settingsApparenceTour.js';
import { smsSettingsRegistry } from '@/app/tours/smsSettingsTour.js';
import { flattenTaskMenuItems, isTaskAllowedForRole, resolveTaskMockScenario } from '@/app/tours/shared/taskUtils.js';

const REGISTRIES = new Map([
    [patientsListeRegistry.routeName, patientsListeRegistry],
    [patientsDossierRegistry.routeName, patientsDossierRegistry],
    [consultationsCardsRegistry.routeName, consultationsCardsRegistry],
    [consultationsTableRegistry.routeName, consultationsTableRegistry],
    [consultationsFormRegistry.routeName, consultationsFormRegistry],
    [caisseRegistry.routeName, caisseRegistry],
    [dashboardRegistry.routeName, dashboardRegistry],
    [agendaRendezvousRegistry.routeName, agendaRendezvousRegistry],
    [agendaEvenementsRegistry.routeName, agendaEvenementsRegistry],
    [rapportsRegistry.routeName, rapportsRegistry],
    [administrationConsumablesRegistry.routeName, administrationConsumablesRegistry],
    [administrationSallesRegistry.routeName, administrationSallesRegistry],
    [administrationNotificationsRegistry.routeName, administrationNotificationsRegistry],
    [administrationFinancesRegistry.routeName, administrationFinancesRegistry],
    [administrationUtilisateursRegistry.routeName, administrationUtilisateursRegistry],
    [administrationGestionrhRegistry.routeName, administrationGestionrhRegistry],
    [administrationEmployeeDetailsRegistry.routeName, administrationEmployeeDetailsRegistry],
    [settingsApparenceRegistry.routeName, settingsApparenceRegistry],
    [smsSettingsRegistry.routeName, smsSettingsRegistry]
]);

export function getRegistryForRoute(routeName) {
    return REGISTRIES.get(routeName) || null;
}

export function getTasksForRoute(routeName, { roles = [] } = {}) {
    const registry = getRegistryForRoute(routeName);
    if (!registry) {
        return [];
    }

    return (registry.tasks || []).filter((task) => isTaskAllowedForRole(task, roles));
}

export function getTaskForRoute(routeName, taskId) {
    const registry = getRegistryForRoute(routeName);
    return registry?.tasks?.find((task) => task.id === taskId) || null;
}

export function getTaskMenuItemsForRoute(routeName, { roles = [] } = {}) {
    return flattenTaskMenuItems(getTasksForRoute(routeName, { roles }));
}

export function buildTourStepsForRoute(routeName, taskId, variantId, ctx) {
    const registry = getRegistryForRoute(routeName);
    if (!registry) {
        return [];
    }

    return registry.buildSteps(taskId || 'overview', variantId, ctx) || [];
}

export function resolveRouteMockScenario(routeName, taskId, variantId, fallbackScenario = 'static') {
    const task = getTaskForRoute(routeName, taskId);
    return resolveTaskMockScenario(task, variantId, fallbackScenario);
}

export function getSupportedTourRoutes() {
    return Array.from(REGISTRIES.keys());
}
