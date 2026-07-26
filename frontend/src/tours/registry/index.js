import { patientsListeRegistry } from '../patientsListTour';
import { patientsDossierRegistry } from '../patientsDossierTour';
import { consultationsCardsRegistry } from '../consultationsCardsTour';
import { consultationsTableRegistry } from '../consultationsTableTour';
import { consultationsFormRegistry } from '../consultationsFormTour';
import { caisseRegistry } from '../caisseTour';
import { dashboardRegistry } from '../dashboardTour';
import { agendaRendezvousRegistry } from '../agendaRendezvousTour';
import { agendaEvenementsRegistry } from '../agendaEvenementsTour';
import { rapportsRegistry } from '../rapportsTour';
import { administrationConsumablesRegistry } from '../administrationConsumablesTour';
import { administrationSallesRegistry } from '../administrationSallesTour';
import { administrationNotificationsRegistry } from '../administrationNotificationsTour';
import { administrationFinancesRegistry } from '../administrationFinancesTour';
import { administrationUtilisateursRegistry } from '../administrationUsersTour';
import { administrationGestionrhRegistry } from '../administrationGestionRHTour';
import { administrationEmployeeDetailsRegistry } from '../administrationEmployeeDetailsTour';
import { settingsApparenceRegistry } from '../settingsApparenceTour';
import { smsSettingsRegistry } from '../smsSettingsTour';
import { flattenTaskMenuItems, isTaskAllowedForRole, resolveTaskMockScenario } from '../shared/taskUtils';

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
