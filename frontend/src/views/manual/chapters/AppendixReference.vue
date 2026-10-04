<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualSection from '../components/ManualSection.vue';

const Y = 'oui';
const N = '—';

/** Colonnes : Admin, Accueil (rôle Réceptionniste), Médecin. */
const rights = [
    ['Tableau de bord, accueil en cartes', Y, Y, Y],
    ['Mode Focus', 'Reception, Dentiste, Rendez-vous', 'Reception, Rendez-vous', 'Dentiste, Rendez-vous'],
    ['Agenda › Rendez-Vous', Y, Y, 'son planning'],
    ['Agenda › Evenements', Y, N, N],
    ['Patients › Liste et Dossier', Y, Y, 'ses patients'],
    ['Consultations › File d’attente', Y, 'via le Focus', Y],
    ['Consultations › Historique', Y, Y, Y],
    ['Fiche médicale complète', Y, N, Y],
    ['Clôture rapide', Y, 'si autorisée', Y],
    ['Consultations › Services cabinets', Y, Y, Y],
    ['Caisse › Encaissements', Y, Y, N],
    ['Modifier une facture sans paiement', Y, 'si autorisé', N],
    ['Réinitialiser une facture', Y, N, N],
    ['Rapports', 'complets', 'du jour, par médecin', 'ses chiffres'],
    ['Administration (RH, Finances, Utilisateurs, Salles, Consommables, Notifications, Avis)', Y, N, N],
    ['Paramètres généraux', Y, 'Apparence', N],
    ['API SMS', 'si Internet actif', N, N],
    ['Mon profil, Manuel d’utilisation', Y, Y, Y]
];

function cellClass(value) {
    if (value === Y) return 'm-yes';
    if (value === N) return 'm-no';
    return 'm-partial';
}

const troubleshooting = [
    ['L’écran « Appareil non autorise » s’affiche après la connexion.', 'Le poste n’a pas encore été approuvé.', 'Un administrateur approuve le poste dans Paramètres généraux › Appareils autorisés, puis cliquez « Verifier l\'autorisation ».'],
    ['Un écran « Accès refusé » s’ouvre.', 'Votre rôle n’autorise pas cette page (certaines cartes ou entrées restent visibles sans être autorisées).', 'Revenez à une page de votre profil (annexe A), ou demandez à l’administrateur de revoir votre rôle.'],
    ['« Nom d\'utilisateur ou mot de passe incorrect. »', 'Faute de frappe, ou mot de passe jamais posé sur le compte.', 'Vérifiez la saisie ; sinon l’administrateur pose un nouveau mot de passe avec la clé, dans Utilisateurs.'],
    ['Le médecin n’apparaît pas dans la liste des médecins.', 'Sa fiche RH existe mais pas son compte, ou son compte n’a pas le rôle Médecin.', 'Créez ou corrigez le compte dans Administration › Utilisateurs (section 1.8).'],
    ['Dialogue « Consultation en cours » à la création.', 'Le patient a une consultation non clôturée.', 'Continuez la consultation existante, ou annulez-la si elle a été créée par erreur et qu’aucune fiche n’y est liée.'],
    ['La consultation a été créée gratuite par erreur.', '« Consultation payante » est désactivé par défaut.', 'Ne validez pas la facture vide : demandez à l’administrateur de la corriger. À l’avenir, activez l’interrupteur à la création.'],
    ['La clôture est refusée.', 'Aucun médecin choisi, ou une section modifiée non sauvegardée.', 'Choisissez le médecin, sauvegardez chaque section, puis recommencez.'],
    ['Aucun mode de paiement n’est proposé.', 'Aucun mode actif dans Finances.', 'L’administrateur crée ou réactive un mode (section 1.5).'],
    ['Le montant saisi est refusé à l’encaissement.', 'Il dépasse le reste à payer, ou il est nul.', 'Saisissez un montant supérieur à zéro et au plus égal au reste.'],
    ['Le bouton « Modifier » de la facture est absent.', 'Un paiement, même partiel, existe déjà, ou votre rôle n’a pas ce droit.', 'Encaissez, ou faites réinitialiser la facture par l’administrateur puis ressaisissez les paiements.'],
    ['« Impression indisponible ».', 'L’imprimante ticket du poste ne répond pas.', 'Allumez et sélectionnez l’imprimante, puis relancez depuis l’onglet Paiements (bouton Reçu).'],
    ['L’onglet assurance est absent du formulaire patient.', 'Aucune assurance n’est activée.', 'L’administrateur active les organismes dans Finances › Assurances.'],
    ['La file du Mode Focus ne se met pas à jour.', 'Le temps réel est coupé ou la connexion a décroché.', 'Rallumez « Temps réel » ou cliquez le bouton d’actualisation.'],
    ['Un médecin ne voit pas le dossier ou le téléphone d’un patient.', 'Réglage « Interface médecin » des Paramètres généraux.', 'Comportement voulu par le cabinet ; l’administrateur peut le modifier.'],
    ['Les rappels SMS ne partent pas.', 'Fournisseur non configuré, Internet coupé, patient désabonné ou service d’envoi arrêté.', 'Vérifiez API SMS (test de connexion), la préférence du patient, puis contactez le support si le service serveur est arrêté.']
];

const glossary = [
    ['Acte', 'Soin du catalogue posé sur une consultation. Il est facturé au patient et compte dans la rémunération du médecin.'],
    ['Service cabinet', 'Prestation facturée au patient mais exclue de la rémunération du médecin : radiographie, produit, kit.'],
    ['Consultation', 'Dossier de la visite du jour. Elle naît ouverte, entre dans la file d’attente et se termine par la clôture.'],
    ['Clôture', 'Geste qui fige la consultation : médecin, salle et soins sont enregistrés, la fiche passe en lecture seule.'],
    ['Clôture rapide', 'Formulaire court permettant de clôturer une visite simple sans ouvrir la fiche médicale complète.'],
    ['Aide guidée', 'Visite interactive d’une page, lancée depuis le point d’interrogation de la barre du haut.'],
    ['Fiche médicale', 'Ensemble des sections cliniques d’une consultation : questionnaire, examens, plan, devis, séances.'],
    ['File d’attente', 'Liste des consultations ouvertes, de la plus ancienne à la plus récente.'],
    ['Reliquat', 'Reste à payer sur une facture antérieure. Signalé par une icône de portefeuille sur la ligne du patient.'],
    ['Reçu', 'Preuve d’un versement, imprimée en 80 mm après chaque paiement.'],
    ['Lot d’assurance', 'Regroupement de factures assurance envoyées ensemble à un organisme, puis remboursées.'],
    ['Mode de paiement', 'Moyen d’encaissement configuré par l’administrateur : espèces, virement, carte, mobile money.'],
    ['Mode Focus', 'Écran unique qui regroupe le travail du jour par mode : Reception, Dentiste, Rendez-vous.'],
    ['Appareil autorisé', 'Poste approuvé par un administrateur, seul capable d’afficher les données du cabinet.'],
    ['Temps réel', 'Rafraîchissement automatique de la file et des factures dans le Mode Focus.']
];
</script>

<template>
    <ManualChapter id="annexes">
        <ManualSection id="ax-droits">
            <p>Ce tableau résume ce que chaque profil voit. « Si autorisé » renvoie à un réglage de <span class="path">Paramètres › Paramètres généraux</span>.</p>
            <div class="m-table-wrap">
                <table class="m-table m-table--center">
                    <thead>
                        <tr>
                            <th style="text-align: left">Écran ou action</th>
                            <th>Admin</th>
                            <th>Accueil</th>
                            <th>Médecin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rights" :key="row[0]">
                            <td>{{ row[0] }}</td>
                            <td v-for="(value, index) in row.slice(1)" :key="index" :class="cellClass(value)">{{ value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="note">
                <p>
                    Un compte qui ne porte que <span class="m-code">ROLE_RECEPTIONNISTE</span> ou que <span class="m-code">ROLE_SECRETAIRE</span> (anciens comptes) n’ouvre que le Mode Focus, Mon profil et ce manuel ; la secrétaire garde l’onglet Apparence. Les autres entrées affichées dans son menu
                    renvoient « Accès refusé ».
                </p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="ax-depannage">
            <p>Les blocages les plus fréquents, classés par symptôme. Lisez d’abord la colonne « Cause probable » : la plupart des blocages viennent d’un réglage manquant, pas d’une panne.</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Symptôme</th>
                            <th style="width: 30%">Cause probable</th>
                            <th>Solution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in troubleshooting" :key="row[0]">
                            <td>{{ row[0] }}</td>
                            <td>{{ row[1] }}</td>
                            <td>{{ row[2] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="tip">
                <p>Avant d’appeler le support, notez la page, l’heure, le patient concerné et le message exact affiché. Une capture d’écran fait gagner beaucoup de temps.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="ax-glossaire">
            <dl class="m-dl">
                <template v-for="row in glossary" :key="row[0]">
                    <dt>{{ row[0] }}</dt>
                    <dd>{{ row[1] }}</dd>
                </template>
            </dl>
        </ManualSection>
    </ManualChapter>
</template>
