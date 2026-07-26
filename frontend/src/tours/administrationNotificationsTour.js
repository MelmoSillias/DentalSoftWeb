import { normalizeTourSteps } from './shared/tourHelpers';
import { createTourRegistry } from './shared/createTourRegistry';

const GROUP = 'administration-notifications';

const TASKS = [
    { id: 'overview', label: 'Presentation de la page', icon: 'pi pi-compass', mockScenario: 'static' },
    { id: 'create-notification', label: 'Creer une notification', icon: 'pi pi-bell', mockScenario: 'static' },
    { id: 'schedule-reminder', label: 'Programmer un rappel', icon: 'pi pi-clock', mockScenario: 'static' }
];

function buildOverviewSteps(ctx) {
    return normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.header"]',
            title: 'Centre de notification',
            content: 'Cette page sert a composer une notification interne et a choisir precisement ses destinataires.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.action-bar"]',
            title: 'Etat de l envoi',
            content: 'La barre d action rappelle le nombre de destinataires retenus et centralise le bouton d envoi.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.users"]',
            title: 'Selection individuelle',
            content: 'La liste de gauche permet de rechercher un utilisateur puis de le choisir en selection simple ou multiple.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.types"]',
            title: 'Selection rapide par type',
            content: 'Les boutons de type servent a preselectionner un groupe de profils comme reception, medecins ou administration.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.message"]',
            title: 'Composer le message',
            content: 'Ce bloc regroupe la priorite, le message, le lien eventuel et l apercu du rendu final.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.recipients"]',
            title: 'Controler les destinataires',
            content: 'La colonne de droite recapitule les destinataires selectionnes et permet de nettoyer rapidement la liste.'
        }
    ]);
}

export const administrationNotificationsRegistry = createTourRegistry(GROUP, TASKS, {
    overview: buildOverviewSteps,
    'create-notification': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.users"]',
            title: 'Choisir les destinataires',
            content: 'Recherchez et selectionnez les utilisateurs qui recevront la notification.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.message"]',
            title: 'Composer le message',
            content: 'Definissez la priorite, le texte et le lien eventuel du message a diffuser.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.preview"]',
            title: 'Verifier l apercu',
            content: 'L apercu permet de controler le ton, la priorite et le lien avant confirmation.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.send"]',
            title: 'Declencher l envoi',
            content: 'Une fois le message et les destinataires verifies, le bouton Envoyer ouvre la confirmation d envoi.'
        }
    ]),
    'schedule-reminder': (ctx) => normalizeTourSteps([
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.types"]',
            title: 'Cibler un groupe',
            content: 'Preselectionnez un type de profil pour adresser un rappel a un service precis.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.message"]',
            title: 'Rediger le rappel',
            content: 'Utilisez une priorite elevee et un message concis pour un rappel efficace.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.recipients"]',
            title: 'Valider les destinataires',
            content: 'Verifiez la liste finale avant d envoyer le rappel aux personnes concernees.'
        },
        {
            group: GROUP,
            target: '[data-tour="admin-notifications.send"]',
            title: 'Envoyer le rappel',
            content: 'Confirmez l envoi pour diffuser le rappel aux destinataires selectionnes.'
        }
    ])
});

export function buildAdministrationNotificationsTourSteps(taskId, variantId, ctx) {
    return administrationNotificationsRegistry.buildSteps(taskId, variantId, ctx);
}

export function createAdministrationNotificationsTour(ctx = {}) {
    return buildAdministrationNotificationsTourSteps('overview', null, ctx);
}
