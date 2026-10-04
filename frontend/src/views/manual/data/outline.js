import { pageDocs } from './pagesCatalog';
import { scenarios } from './scenarios';

const ALL = ['admin', 'accueil', 'medecin'];

/**
 * Plan du manuel : source unique des titres, numéros, profils concernés,
 * du sommaire latéral et du sommaire imprimé.
 */
const chapters = [
    {
        id: 'avant-propos',
        kicker: 'Avant de commencer',
        title: 'Lire et utiliser ce manuel',
        numbered: false,
        roles: [],
        sections: [
            { id: 'conventions', title: 'Conventions de lecture' },
            { id: 'profils', title: 'Profils et droits' },
            { id: 'navigation', title: 'Se repérer dans l’application' }
        ]
    },
    {
        id: 'mise-en-route',
        title: 'Mise en route',
        goal: 'Configurer le cabinet le jour de l’installation, pour que le premier patient puisse être reçu, soigné, facturé et encaissé sans blocage.',
        roles: ['admin'],
        sections: [
            { id: 'mr-checklist', title: 'Checklist de première configuration' },
            { id: 'mr-postes', title: 'Se connecter et autoriser les postes' },
            { id: 'mr-cabinet', title: 'Régler l’identité, les horaires et les règles' },
            { id: 'mr-tarifs', title: 'Tarifs de consultation et catalogue des soins' },
            { id: 'mr-paiements', title: 'Créer les modes de paiement' },
            { id: 'mr-salles', title: 'Créer les salles de soins' },
            { id: 'mr-employes', title: 'Créer les employés et les médecins' },
            { id: 'mr-comptes', title: 'Créer les comptes de connexion' },
            { id: 'mr-assurances', title: 'Activer les assurances' },
            { id: 'mr-fermetures', title: 'Bloquer les jours de fermeture' },
            { id: 'mr-test', title: 'Valider l’installation par un test à blanc' }
        ]
    },
    {
        id: 'workflow',
        title: 'Utilisation de base',
        goal: 'Accompagner une visite ordinaire de bout en bout : accueil du patient, consultation, encaissement, reçu et rendez-vous de suivi.',
        roles: ALL,
        sections: [
            { id: 'wf-vue', title: 'Le parcours d’une visite', roles: ALL },
            { id: 'wf-patient', title: 'Créer ou retrouver le patient', roles: ALL },
            { id: 'wf-consultation', title: 'Ouvrir la consultation', roles: ['admin', 'accueil'] },
            { id: 'wf-cloture', title: 'Soigner et clôturer', roles: ALL },
            { id: 'wf-paiement', title: 'Encaisser', roles: ['admin', 'accueil'] },
            { id: 'wf-recu', title: 'Imprimer le reçu', roles: ['admin', 'accueil'] },
            { id: 'wf-rdv', title: 'Prendre le rendez-vous de suivi', roles: ALL }
        ]
    },
    {
        id: 'focus',
        title: 'Mode Focus',
        goal: 'Tenir la journée sur un seul écran : arrivées, file d’attente, fiche médicale, règlements et rendez-vous, sans naviguer entre les menus.',
        roles: ALL,
        sections: [
            { id: 'fo-principe', title: 'Le principe', roles: ALL },
            { id: 'fo-usages', title: 'Quand l’utiliser et ce qu’on y gagne', roles: ALL },
            { id: 'fo-comparaison', title: 'Mode Focus ou menu classique', roles: ALL },
            { id: 'fo-reception', title: 'Travailler en mode Reception', roles: ['admin', 'accueil'] },
            { id: 'fo-dentiste', title: 'Travailler en mode Dentiste', roles: ['admin', 'medecin'] },
            { id: 'fo-rdv', title: 'Le mode Rendez-vous', roles: ALL },
            { id: 'fo-actions', title: 'Récapitulatif des actions possibles', roles: ALL }
        ]
    },
    {
        id: 'pages',
        title: 'Documentation des pages',
        goal: 'Servir de référence écran par écran : finalité, actions, événements déclenchés, conséquences et exceptions.',
        roles: ALL,
        sections: pageDocs.map((page) => ({ id: page.id, title: page.title, roles: page.audience }))
    },
    {
        id: 'scenarios',
        title: 'Scénarios d’utilisation',
        goal: 'Rejouer des situations réelles du cabinet, de la plus simple à la plus délicate, avec les décisions à prendre et leurs variantes.',
        roles: ALL,
        sections: scenarios.map((story) => ({ id: story.id, title: story.title, roles: story.audience }))
    },
    {
        id: 'annexes',
        kicker: 'Annexes',
        title: 'Références rapides',
        numbered: false,
        letters: true,
        roles: [],
        sections: [
            { id: 'ax-droits', title: 'Matrice des droits par profil' },
            { id: 'ax-depannage', title: 'Dépannage' },
            { id: 'ax-glossaire', title: 'Glossaire' }
        ]
    }
];

let chapterNumber = 0;
export const outline = chapters.map((chapter) => {
    const numbered = chapter.numbered !== false;
    if (numbered) chapterNumber += 1;
    const prefix = numbered ? String(chapterNumber) : '';
    return {
        ...chapter,
        number: prefix,
        kicker: chapter.kicker ?? `Chapitre ${prefix}`,
        sections: chapter.sections.map((section, index) => ({
            roles: [],
            ...section,
            chapterId: chapter.id,
            number: chapter.letters ? String.fromCharCode(65 + index) : numbered ? `${prefix}.${index + 1}` : ''
        }))
    };
});

const sectionIndex = new Map(outline.flatMap((chapter) => chapter.sections.map((section) => [section.id, section])));
const chapterIndex = new Map(outline.map((chapter) => [chapter.id, chapter]));

export function findChapter(id) {
    return chapterIndex.get(id) ?? null;
}

export function findSection(id) {
    return sectionIndex.get(id) ?? null;
}

/**
 * Plan filtré par profil de lecture.
 * @param {(roles: string[]) => boolean} isVisible
 */
export function visibleOutline(isVisible) {
    return outline
        .filter((chapter) => isVisible(chapter.roles))
        .map((chapter) => ({ ...chapter, sections: chapter.sections.filter((section) => isVisible(section.roles)) }));
}
