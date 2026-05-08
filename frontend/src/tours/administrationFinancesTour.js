export function resolveAdministrationFinancesTourGroup(activeTab) {
    return 'administration-finances';
}

async function wait(ms = 120) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

export function createAdministrationFinancesTour({ switchTab, openTransactionDialog, openModeDialog, closeAllDialogs }) {
    return [
        {
            group: 'administration-finances',
            order: 10,
            target: '[data-tour="admin-finances.header"]',
            title: 'Tableau de bord financier',
            content: 'Cette page centralise la supervision financiere du cabinet. Elle sert a suivre les transactions, piloter les comptes et administrer les modes de paiement sans quitter un seul ecran.'
        },
        {
            group: 'administration-finances',
            order: 20,
            target: '[data-tour="admin-finances.kpi"]',
            title: 'Lire les KPI',
            content: 'Les cartes du haut synthétisent les points de controle essentiels: capital disponible, transactions deja validees, flux encore en attente et nombre de modes actifs ou assurances configurees.'
        },
        {
            group: 'administration-finances',
            order: 30,
            target: '[data-tour="admin-finances.tabs"]',
            title: 'Organisation par onglets',
            content: 'L onglet Transactions centralise le suivi quotidien. L onglet Mode de paiement regroupe la gestion des comptes. L onglet Graphiques sert a l analyse globale sur la periode.'
        },
        {
            group: 'administration-finances',
            order: 40,
            target: '[data-tour="admin-finances.transactions"]',
            title: 'Filtrer l historique',
            content: 'Le premier bloc permet d isoler les transactions a verifier en combinant periode, statut de validation et recherche libre. C est la zone de travail la plus utile pour les controles administratifs quotidiens.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('transactions');
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 50,
            target: '[data-tour="admin-finances.validation"]',
            title: 'Valider ou rejeter',
            content: 'Quand une transaction est en attente, ces actions permettent soit de la valider pour l intégrer au flux confirme, soit de la rejeter si elle doit etre corrigee ou justifiee.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('transactions');
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 60,
            target: '[data-tour="admin-finances.methods"]',
            title: 'Gerer les modes de paiement',
            content: 'Ce tableau regroupe les comptes de paiement par famille. Les modes classiques sont dissocies des assurances afin de distinguer les reglements directs des prises en charge assureur.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('payment-methods');
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 70,
            target: '[data-tour="admin-finances.method-actions"]',
            title: 'Actions sur un mode',
            content: 'Chaque mode peut etre modifie, active ou desactive. Cette zone sert a faire evoluer le referentiel financier sans toucher aux comptes proteges.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('payment-methods');
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 80,
            target: '[data-tour="admin-finances.dialog.transaction"]',
            title: 'Nouvelle transaction',
            content: 'Le formulaire de transaction enregistre une entree ou une sortie avec le compte, le montant, la date et le motif. Il évite de quitter la page pendant le controle.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('transactions');
                await openTransactionDialog();
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 90,
            target: '[data-tour="admin-finances.dialog.mode"]',
            title: 'Configurer un mode',
            content: 'Le formulaire de mode de paiement permet d ajouter ou modifier un compte, y compris une assurance avec son taux de couverture par défaut.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('payment-methods');
                await openModeDialog();
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 100,
            target: '[data-tour="admin-finances.tabs"]',
            title: 'Passer aux graphiques',
            content: 'Le tour bascule maintenant vers les graphiques pour montrer la partie analyse et pilotage financier.',
            beforeEnter: async () => {
                await closeAllDialogs();
                await switchTab('charts');
                await wait();
            }
        },
        {
            group: 'administration-finances',
            order: 110,
            target: '[data-tour="admin-finances.monthly-flow"]',
            title: 'Flux mensuel global',
            content: 'Ce graphique compare les entrees, les depenses et le resultat net sur l annee choisie. Il sert a detecter rapidement les mois d acceleration ou de tension.'
        },
        {
            group: 'administration-finances',
            order: 120,
            target: '[data-tour="admin-finances.distribution"]',
            title: 'Répartition des encaissements',
            content: 'Ce donut montre la part de chaque mode de paiement dans les encaissements. Il aide a mesurer la dependance a certains canaux de reglement.'
        },
        {
            group: 'administration-finances',
            order: 130,
            target: '[data-tour="admin-finances.accounts"]',
            title: 'Solde par compte',
            content: 'Ce graphique compare les entrees, les sorties et le solde courant pour chaque compte. Il permet de reperer les comptes sous tension ou surutilisés.'
        },
        {
            group: 'administration-finances',
            order: 140,
            target: '[data-tour="admin-finances.capital-share"]',
            title: 'Capital par compte',
            content: 'La répartition du capital disponible met en évidence la concentration de la trésorerie entre les différents comptes actifs.'
        },
        {
            group: 'administration-finances',
            order: 150,
            target: '[data-tour="admin-finances.status"]',
            title: 'Statuts de validation',
            content: 'Ce graphique offre une lecture immédiate du volume de flux en attente, validés ou rejetés. C est un bon indicateur de qualité de traitement administratif.'
        },
        {
            group: 'administration-finances',
            order: 160,
            target: '[data-tour="admin-finances.evolution"]',
            title: 'Évolution du capital',
            content: 'Le graphique final suit la progression cumulée du capital sur l année. Il donne une lecture macro de la trajectoire financière globale.'
        }
    ];
}
