<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualRoleBadge from '../components/ManualRoleBadge.vue';
import ManualSection from '../components/ManualSection.vue';
</script>

<template>
    <ManualChapter id="avant-propos">
        <p class="m-lead">
            Ce manuel suit l’ordre dans lequel on apprend le logiciel. Le chapitre 1 se lit une seule fois, le jour de la mise en service. Le chapitre 2 décrit le geste de tous les jours. Le chapitre 3 montre comment tenir toute la journée sur un seul écran. Le chapitre 4 est une référence que l’on consulte page par page,
            et le chapitre 5 rejoue des journées réelles, des plus simples aux plus délicates.
        </p>

        <div class="m-grid-3">
            <div class="m-card m-card--accent">
                <h4>Vous installez le cabinet</h4>
                <p>Lisez le chapitre 1 en entier, dans l’ordre de la checklist, puis faites le test à blanc de la section 1.11.</p>
            </div>
            <div class="m-card m-card--accent">
                <h4>Vous êtes à l’accueil</h4>
                <p>Lisez le chapitre 2, puis le mode Reception du chapitre 3. Gardez l’annexe Dépannage à portée de main.</p>
            </div>
            <div class="m-card m-card--accent">
                <h4>Vous êtes médecin</h4>
                <p>Lisez la section 2.4, le mode Dentiste du chapitre 3 et la fiche « Fiche médicale » du chapitre 4.</p>
            </div>
        </div>

        <ManualSection id="conventions">
            <p>Le manuel utilise toujours les mêmes repères visuels. Les reconnaître fait gagner du temps à la lecture.</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Repère</th>
                            <th>Signification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="ui">Créer</span></td>
                            <td>Un libellé tel qu’il apparaît à l’écran : bouton, onglet, champ ou option. Il est recopié à l’identique, y compris lorsqu’un accent manque dans le logiciel.</td>
                        </tr>
                        <tr>
                            <td><span class="path">Caisse › Encaissements</span></td>
                            <td>Un chemin dans le menu latéral : la rubrique, puis l’entrée à cliquer.</td>
                        </tr>
                        <tr>
                            <td><span class="msg">« Patient sauvegardé. »</span></td>
                            <td>Un message affiché par le logiciel, en général dans une notification en haut à droite.</td>
                        </tr>
                        <tr>
                            <td>Étapes numérotées</td>
                            <td>Une procédure à suivre dans l’ordre. Une étape bordée de rouge marquée « Action critique » engage de l’argent ou ne s’annule pas.</td>
                        </tr>
                        <tr>
                            <td><ManualRoleBadge :roles="['admin', 'accueil', 'medecin']" /></td>
                            <td>Les profils concernés par la section. Le filtre « Lire en tant que » du sommaire masque les sections qui ne vous concernent pas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ManualCallout type="tip">
                <p>Un raccourci ou une bonne pratique qui évite une erreur fréquente.</p>
            </ManualCallout>
            <ManualCallout type="note">
                <p>Une règle liée à un rôle, à un réglage du cabinet ou à un cas particulier.</p>
            </ManualCallout>
            <ManualCallout type="warn">
                <p>Un point qui provoque souvent un blocage ou une incohérence s’il est négligé.</p>
            </ManualCallout>
            <ManualCallout type="critical">
                <p>Une action définitive : suppression, réinitialisation, annulation. Lisez l’encadré avant de cliquer.</p>
            </ManualCallout>

            <ManualFigure
                label="Exemple de capture annotée"
                number="0.1"
                compact
                caption="Les captures sont représentées par des cadres hachurés. Les pastilles rouges renvoient à la légende placée sous le cadre."
                :markers="[
                    { x: 12, y: 30, text: 'Élément de l’écran désigné par le texte.' },
                    { x: 82, y: 70, text: 'Deuxième élément, décrit dans l’ordre de lecture.' }
                ]"
            />
        </ManualSection>

        <ManualSection id="profils">
            <p>
                Chaque compte reçoit un rôle. Le rôle décide des rubriques visibles dans le menu et des boutons proposés sur chaque écran. Une page non autorisée renvoie vers un écran « Accès refusé » : ce n’est pas une panne, c’est le rôle qui ne le permet pas.
            </p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Profil</th>
                            <th>Rôle technique</th>
                            <th>Travail principal</th>
                            <th>Écrans habituels</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Administrateur</td>
                            <td><span class="m-code">ROLE_ADMIN</span></td>
                            <td>Configure le cabinet, gère le personnel, l’argent et les corrections.</td>
                            <td>Tout le menu, y compris Administration et Paramètres.</td>
                        </tr>
                        <tr>
                            <td>Accueil (réceptionniste)</td>
                            <td><span class="m-code">ROLE_RECEPTION</span>, <span class="m-code">ROLE_RECEPTIONNISTE</span>, <span class="m-code">ROLE_SECRETAIRE</span></td>
                            <td>Reçoit les patients, ouvre les consultations, encaisse, tient l’agenda.</td>
                            <td>Mode Focus, Agenda, Patients, Historique, Services cabinets, Caisse, Rapports, apparence des Paramètres généraux.</td>
                        </tr>
                        <tr>
                            <td>Médecin</td>
                            <td><span class="m-code">ROLE_MEDECIN</span></td>
                            <td>Soigne, remplit la fiche médicale, clôture la consultation.</td>
                            <td>Mode Focus (Dentiste), File d’attente, Patients, Historique, Agenda, Rapports. Pas de Caisse.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="note">
                <p>
                    Un compte créé avec le rôle <span class="ui">Réceptionniste</span> dans <span class="path">Administration › Utilisateurs</span> reçoit les trois rôles techniques de l’accueil ; dans ce manuel, il porte la pastille <ManualRoleBadge :roles="['accueil']" />. Le menu du
                    compte l’affiche comme « Réception ». La matrice complète des droits figure en annexe A.
                </p>
                <p>
                    Certains comptes plus anciens ne portent que <span class="m-code">ROLE_RECEPTIONNISTE</span> ou <span class="m-code">ROLE_SECRETAIRE</span>. Ils travaillent uniquement dans le Mode Focus : les entrées Tableau de bord, Caisse ou Patients leur renvoient « Accès refusé ». Pour leur ouvrir tout l’accueil,
                    l’administrateur réenregistre le compte avec le rôle Réceptionniste.
                </p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="navigation">
            <p>L’écran se compose de trois zones stables, présentes sur toutes les pages.</p>
            <ManualFigure
                label="Structure de l’écran"
                number="0.2"
                caption="Barre du haut, menu latéral et zone de travail."
                :markers="[
                    { x: 8, y: 12, text: 'Barre du haut : bouton du menu, date et heure, mode sombre, Aide guidée (point d’interrogation), cloche des notifications et menu du compte.' },
                    { x: 8, y: 55, text: 'Menu latéral : les rubriques autorisées pour votre rôle (Accueil, Agenda, Patients, Consultations, Caisse, Rapports, Administration, Paramètres).' },
                    { x: 55, y: 50, text: 'Zone de travail : la page ouverte, ses filtres, ses tableaux et ses boutons.' },
                    { x: 92, y: 12, text: 'Menu du compte : « Mon profil », « Manuel d’utilisation » et « Déconnexion ».' }
                ]"
            />
            <h4>Deux façons d’arriver dans l’application</h4>
            <div class="m-grid-2">
                <div class="m-card">
                    <h4>Navigation classique</h4>
                    <p>Après la connexion, le <strong>tableau de bord</strong> s’ouvre. On passe d’une page à l’autre par le menu latéral.</p>
                </div>
                <div class="m-card">
                    <h4>Accueil en cartes</h4>
                    <p>
                        Après la connexion, la page « Accueil » présente une grille de cartes reprenant les entrées du menu autorisées pour votre rôle. Un clic sur une carte ouvre la page. Le choix entre <span class="ui">Classique (sidebar)</span> et <span class="ui">Accueil cartes</span> se fait par poste dans
                        <span class="path">Paramètres › Paramètres généraux</span>, onglet Apparence.
                    </p>
                </div>
            </div>
            <h4>L’aide guidée, en complément du manuel</h4>
            <p>
                Sur la plupart des pages, le bouton <span class="ui">Aide guidée</span> de la barre du haut (point d’interrogation) propose « Choisissez une action à découvrir sur cette page. » : une visite pas à pas met en surbrillance les éléments de l’écran. Si la page n’en a pas, un message
                l’indique. Le manuel explique le pourquoi et l’enchaînement ; l’aide guidée montre où cliquer.
            </p>
            <ManualCallout type="tip">
                <p>Ce manuel est accessible à tout moment depuis le menu du compte, en haut à droite : <span class="ui">Manuel d'utilisation</span>. Il figure aussi tout en bas du menu latéral, dans la rubrique <span class="ui">Documentation</span>.</p>
            </ManualCallout>
        </ManualSection>
    </ManualChapter>
</template>
