import agendaEvenements from '@/assets/hub-icons/agenda-evenements.png';
import agendaRendezvous from '@/assets/hub-icons/agenda-rendezvous.png';
import apiSms from '@/assets/hub-icons/api-sms.png';
import avisRetours from '@/assets/hub-icons/avis-retours.png';
import caisse from '@/assets/hub-icons/caisse.png';
import consommables from '@/assets/hub-icons/consommables.png';
import consultationsCards from '@/assets/hub-icons/consultations-cards.png';
import consultationsTable from '@/assets/hub-icons/consultations-table.png';
import dashboard from '@/assets/hub-icons/dashboard.png';
import finances from '@/assets/hub-icons/finances.png';
import focusMode from '@/assets/hub-icons/focus-mode.png';
import generalOptions from '@/assets/hub-icons/general-options.png';
import gestionRh from '@/assets/hub-icons/gestion-rh.png';
import notifications from '@/assets/hub-icons/notifications.png';
import patientsDossier from '@/assets/hub-icons/patients-dossier.png';
import patientsListe from '@/assets/hub-icons/patients-liste.png';
import rapports from '@/assets/hub-icons/rapports.png';
import salles from '@/assets/hub-icons/salles.png';
import utilisateurs from '@/assets/hub-icons/utilisateurs.png';

export const HUB_ICONS = {
    dashboard,
    'focus-mode': focusMode,
    'agenda-rendezvous': agendaRendezvous,
    'agenda-evenements': agendaEvenements,
    'patients-liste': patientsListe,
    'patients-dossier': patientsDossier,
    'consultations-cards': consultationsCards,
    'consultations-table': consultationsTable,
    caisse,
    rapports,
    consommables,
    salles,
    'gestion-rh': gestionRh,
    finances,
    utilisateurs,
    notifications,
    'avis-retours': avisRetours,
    'general-options': generalOptions,
    'api-sms': apiSms
};

export function getHubIcon(iconKey) {
    return iconKey ? HUB_ICONS[iconKey] ?? null : null;
}
