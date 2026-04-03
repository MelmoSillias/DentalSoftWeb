import { nextTick } from 'vue';
import { getTourGuideClient } from './tourGuideClient';

function wait(ms = 120) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

async function refreshTourLayout() {
    const tg = getTourGuideClient();

    if (!tg?.isVisible) return;
    await tg.updatePositions().catch(() => undefined);
}

async function flushUi() {
    await nextTick();
    await wait();
    await refreshTourLayout();
}

export function createAdministrationNotificationsTour() {
    return [
        {
            group: 'administration-notifications',
            order: 10,
            target: '[data-tour="admin-notifications.header"]',
            title: 'Centre de notification',
            content: 'Cette page sert a composer une notification interne et a choisir precisement ses destinataires.'
        },
        {
            group: 'administration-notifications',
            order: 20,
            target: '[data-tour="admin-notifications.action-bar"]',
            title: 'Etat de l envoi',
            content: 'La barre d action rappelle le nombre de destinataires retenus et centralise le bouton d envoi.'
        },
        {
            group: 'administration-notifications',
            order: 30,
            target: '[data-tour="admin-notifications.users"]',
            title: 'Selection individuelle',
            content: 'La liste de gauche permet de rechercher un utilisateur puis de le choisir en selection simple ou multiple.'
        },
        {
            group: 'administration-notifications',
            order: 40,
            target: '[data-tour="admin-notifications.types"]',
            title: 'Selection rapide par type',
            content: 'Les boutons de type servent a preselectionner un groupe de profils comme reception, medecins ou administration.'
        },
        {
            group: 'administration-notifications',
            order: 50,
            target: '[data-tour="admin-notifications.message"]',
            title: 'Composer le message',
            content: 'Ce bloc regroupe la priorite, le message, le lien eventuel et l apercu du rendu final.'
        },
        {
            group: 'administration-notifications',
            order: 60,
            target: '[data-tour="admin-notifications.preview"]',
            title: 'Verifier l apercu',
            content: 'L apercu permet de controler le ton, la priorite et le lien avant toute confirmation d envoi.'
        },
        {
            group: 'administration-notifications',
            order: 70,
            target: '[data-tour="admin-notifications.recipients"]',
            title: 'Controler les destinataires',
            content: 'La colonne de droite recapitule les destinataires selectionnes et permet de nettoyer rapidement la liste.'
        },
        {
            group: 'administration-notifications',
            order: 80,
            target: '[data-tour="admin-notifications.send"]',
            title: 'Declencher l envoi',
            content: 'Une fois le message et les destinataires verifies, le bouton Envoyer ouvre la confirmation d envoi.'
        }
    ];
}