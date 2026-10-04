<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualSection from '../components/ManualSection.vue';
import ManualStep from '../components/ManualStep.vue';
import ManualSteps from '../components/ManualSteps.vue';

const actions = [
    ['Rechercher un patient', 'Reception, colonne Nouveaux patients', 'Au moins deux caractères : nom, prénom ou téléphone.'],
    ['Créer un patient', 'Reception, bouton Ajouter', 'Le patient apparaît dans la colonne du jour.'],
    ['Modifier un patient', 'Reception, crayon de la carte', 'Le même formulaire que la liste des patients s’ouvre.'],
    ['Créer une consultation', 'Reception, stéthoscope de la carte', 'La consultation entre dans la file ; le ticket est proposé si elle est payante.'],
    ['Enregistrer un service cabinet', 'Reception, menu de la carte ou détail', 'Le service est facturé au patient, hors rémunération du médecin.'],
    ['Poser un rendez-vous', 'Menu de la carte (Nouveau RDV), ou mode Rendez-vous', 'Le rendez-vous est créé au statut En attente.'],
    ['Clôturer une visite simple', 'Reception, détail › Clôture rapide', 'La consultation est terminée ; la facture reste à encaisser.'],
    ['Remplir et clôturer la fiche', 'Dentiste, fiche au centre', 'Sections sauvegardées, consultation figée.'],
    ['Régler une facture', 'Reception, bloc facture du détail', 'Paiement enregistré, reçu proposé ; les autres factures impayées s’ouvrent en onglets.'],
    ['Valider une facture à 0', 'Reception, bloc facture du détail', 'La facture vide est confirmée sans paiement.'],
    ['Annuler une consultation', 'Reception, ligne de la file', 'Suppression définitive, après confirmation.'],
    ['Imprimer', 'Reçu, ticket, aperçu de facture, fiche, devis, ordonnance', 'Le document correspondant sort sur l’imprimante du poste.'],
    ['Voir les encaissements du jour', 'Bouton Encaissements', 'Statistiques de la journée, ventilées par mode de paiement.']
];
</script>

<template>
    <ManualChapter id="focus">
        <ManualSection id="fo-principe">
            <p>
                Dans les pages classiques, chaque étape d’une visite a son écran : la liste des patients, la file d’attente, la fiche, la caisse, l’agenda. Le Mode Focus les réunit sur une seule page, centrée sur la journée en cours. On ne change plus de page : on change de mode, et les
                informations suivent.
            </p>
            <div class="m-schema" aria-label="Schéma des trois modes">
                <p class="m-schema__top"><i class="pi pi-bolt" aria-hidden="true"></i>Mode Focus · aujourd’hui · une seule page</p>
                <div class="m-schema__cols">
                    <div class="m-schema__col m-schema__col--reception">
                        <strong>Reception</strong>
                        <small>Pour l’accueil et l’administrateur</small>
                        <ul>
                            <li>Patients du jour et recherche</li>
                            <li>File d’attente</li>
                            <li>Détail : facture, paiement, reçu</li>
                            <li>Clôture rapide</li>
                        </ul>
                    </div>
                    <div class="m-schema__col m-schema__col--dentiste">
                        <strong>Dentiste</strong>
                        <small>Pour le médecin et l’administrateur</small>
                        <ul>
                            <li>Dossier clinique du patient</li>
                            <li>Fiche médicale au centre</li>
                            <li>File de ses consultations</li>
                            <li>Clôture</li>
                        </ul>
                    </div>
                    <div class="m-schema__col m-schema__col--rdv">
                        <strong>Rendez-vous</strong>
                        <small>Pour tous les profils</small>
                        <ul>
                            <li>Agenda du jour embarqué</li>
                            <li>Créneaux libres</li>
                            <li>Validation et report</li>
                        </ul>
                    </div>
                </div>
                <p class="m-schema__foot">Un patient sélectionné en Reception peut être ouvert en Dentiste ; un rendez-vous validé avec consultation fait entrer le patient dans la file. Le temps réel garde la file et les factures à jour.</p>
            </div>
            <p>Les modes s’affichent dans le sélecteur du haut sous les libellés <span class="ui">Reception</span>, <span class="ui">Dentiste</span> et <span class="ui">Rendez-vous</span>. Ceux qui sont proposés dépendent du profil :</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Profil</th>
                            <th>Modes visibles</th>
                            <th>Mode à l’ouverture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Administrateur</td>
                            <td>Reception, Dentiste, Rendez-vous</td>
                            <td>Reception</td>
                        </tr>
                        <tr>
                            <td>Accueil</td>
                            <td>Reception, Rendez-vous</td>
                            <td>Reception</td>
                        </tr>
                        <tr>
                            <td>Médecin</td>
                            <td>Dentiste, Rendez-vous</td>
                            <td>Dentiste</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="note">
                <p>La date du Mode Focus est toujours aujourd’hui. Pour retrouver une visite passée, une facture du mois dernier ou un rendez-vous de la semaine prochaine, utilisez les pages Historique, Caisse et Agenda.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="fo-usages">
            <div class="m-grid-2">
                <div class="m-card">
                    <h4>Cas d’usage</h4>
                    <ul class="m-list">
                        <li>L’accueil enchaîne arrivées, tickets et règlements pendant les heures d’affluence.</li>
                        <li>Le médecin passe d’un patient de la file à sa fiche sans revenir à une liste.</li>
                        <li>Un patient revient avec un reste à payer : le reliquat s’affiche sur sa ligne et se règle dans le même dialogue.</li>
                        <li>Un compte d’accueil limité, sans accès à la Caisse, encaisse quand même la journée.</li>
                    </ul>
                </div>
                <div class="m-card">
                    <h4>Avantages</h4>
                    <ul class="m-list m-list--check">
                        <li><strong>Gain de temps</strong> : une visite complète se traite en quelques clics, sans menu.</li>
                        <li><strong>Fluidité</strong> : le patient sélectionné reste affiché pendant tous les gestes.</li>
                        <li><strong>Moins d’oublis</strong> : reliquats et factures non réglées sont visibles sur la ligne.</li>
                        <li><strong>Vue partagée</strong> : avec le temps réel, l’accueil voit les clôtures du médecin dès qu’elles ont lieu.</li>
                    </ul>
                </div>
            </div>
        </ManualSection>

        <ManualSection id="fo-comparaison">
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Besoin</th>
                            <th>Mode Focus</th>
                            <th>Menu classique</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Patient qui arrive maintenant</td>
                            <td>Colonne des patients, bouton Ajouter</td>
                            <td>Patients › Liste</td>
                        </tr>
                        <tr>
                            <td>Suivre la file du jour</td>
                            <td>Colonne File d’attente, aujourd’hui uniquement</td>
                            <td>Consultations › File d’attente (admin et médecin)</td>
                        </tr>
                        <tr>
                            <td>Encaisser la journée</td>
                            <td>Régler depuis le détail, reçu immédiat</td>
                            <td>Caisse, avec périodes, filtres et lots d’assurance</td>
                        </tr>
                        <tr>
                            <td>Remplir la fiche médicale</td>
                            <td>Mode Dentiste, fiche au centre</td>
                            <td>Fiche ouverte depuis la file d’attente</td>
                        </tr>
                        <tr>
                            <td>Chercher dans le passé</td>
                            <td>Non prévu</td>
                            <td>Historique, Caisse, Dossier patient</td>
                        </tr>
                        <tr>
                            <td>Assurances, paie, rapports</td>
                            <td>Non prévu</td>
                            <td>Caisse, Gestion RH, Finances, Rapports</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="tip">
                <p>Règle simple : le Mode Focus pour aujourd’hui, le menu classique pour tout le reste.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="fo-reception">
            <p>
                Sur un grand écran, trois colonnes côte à côte : <span class="ui">Nouveaux patients</span>, <span class="ui">File d'attente</span> et <span class="ui">Détails</span>. Sur un écran plus étroit, une barre d’onglets <span class="ui">Patients</span> <span class="ui">File</span>
                <span class="ui">Détails</span> n’en affiche qu’une à la fois.
            </p>
            <ManualFigure
                label="Mode Focus, réception"
                number="3.1"
                caption="Les trois colonnes du mode Reception."
                :markers="[
                    { x: 92, y: 8, text: 'Sélecteur de mode, bouton Temps réel, actualisation et bouton Encaissements.' },
                    { x: 15, y: 40, text: 'Nouveaux patients : recherche, bouton Ajouter et cartes patient.' },
                    { x: 48, y: 40, text: 'File d’attente du jour.' },
                    { x: 82, y: 40, text: 'Détail de la consultation sélectionnée et sa facture.' },
                    { x: 30, y: 72, text: 'Icône portefeuille : reliquat d’une facture antérieure.' }
                ]"
            />
            <h4>Accueillir et ouvrir la consultation</h4>
            <ManualSteps>
                <ManualStep>Tapez au moins deux caractères dans la recherche « Nom, prénom ou téléphone... ».</ManualStep>
                <ManualStep>Patient introuvable : <span class="ui">Ajouter</span> ouvre le formulaire « Nouveau patient » (« Ajouter un patient depuis le mode focus »).</ManualStep>
                <ManualStep>
                    Sur la carte du patient : stéthoscope pour <span class="ui">Nouvelle consultation</span>, crayon pour <span class="ui">Modifier le patient</span>, ou <span class="ui">Plus d'actions</span>, qui propose Nouvelle consultation, Enregistrer un service cabinet, Nouveau RDV, Ouvrir
                    dossier et Modifier. Le clic droit sur la carte ouvre le même menu.
                </ManualStep>
                <ManualStep result="La ligne apparaît dans la file d’attente.">Créez la consultation comme décrit en 2.3.</ManualStep>
            </ManualSteps>
            <h4>Suivre la visite et encaisser</h4>
            <ManualSteps>
                <ManualStep>
                    Cliquez la ligne dans la file. Le détail s’ouvre dans la colonne de droite, sans changer de page. L’interrupteur de la file, allumé par défaut, affiche aussi les consultations terminées du jour ; coupez-le pour ne garder que les patients encore en attente.
                </ManualStep>
                <ManualStep>
                    Pour une visite simple, cliquez <span class="ui">Clôture rapide</span>. Pour une visite clinique, laissez le médecin clôturer depuis le mode Dentiste ; l’administrateur et le médecin peuvent y basculer avec <span class="ui">Ouvrir fiche médicale du patient</span>.
                </ManualStep>
                <ManualStep>Dans le bloc facture, cliquez <span class="ui">Régler</span>. S’il existe d’autres factures impayées pour ce patient, elles apparaissent en onglets dans le même dialogue.</ManualStep>
                <ManualStep critical result="« Paiement enregistré. » La notification propose « Imprimer le reçu ».">Contrôlez montant et mode, puis <span class="ui">Confirmer le paiement</span>. Le pied du dialogue rappelle le « Reliquat total » du patient.</ManualStep>
            </ManualSteps>
            <div class="m-table-wrap">
                <table class="m-table">
                    <caption>
                        Boutons du bloc facture
                    </caption>
                    <thead>
                        <tr>
                            <th>Bouton</th>
                            <th>Visible quand…</th>
                            <th>Effet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Régler</td>
                            <td>Il reste un montant à payer.</td>
                            <td>Ouvre l’encaissement, prérempli au reste.</td>
                        </tr>
                        <tr>
                            <td>Valider</td>
                            <td>La facture est vide (0 F) et pas encore validée.</td>
                            <td>« Valider la facture vide » : la confirme sans paiement.</td>
                        </tr>
                        <tr>
                            <td>Modifier</td>
                            <td>Facture non nulle, sans aucun paiement ; administrateur, ou accueil si le cabinet l’autorise. Jamais pour le médecin.</td>
                            <td>Corrige les lignes de la facture.</td>
                        </tr>
                        <tr>
                            <td>Aperçu</td>
                            <td>Dès qu’une facture existe.</td>
                            <td>Affiche puis imprime la facture.</td>
                        </tr>
                        <tr>
                            <td>Réinitialiser</td>
                            <td>Administrateur uniquement, dans le dialogue de paiement ou d’aperçu.</td>
                            <td>Efface immédiatement tous les paiements de la facture (« Facture réinitialisée. »).</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="critical">
                <p>
                    Dans le Mode Focus, <span class="ui">Réinitialiser</span> agit immédiatement, sans demande de confirmation : tous les paiements de la facture sont effacés. Notez-les avant de cliquer. <span class="ui">Annuler</span> une consultation de la file demande « Annuler cette consultation ? Cette action
                    est irréversible. ». Aucune de ces deux actions ne se restaure.
                </p>
            </ManualCallout>
            <p>Le bouton <span class="ui">Encaissements</span> affiche le total encaissé du jour en FCFA. Il ouvre « Statistiques d'encaissements du jour » :</p>
            <ul class="m-list">
                <li><strong>Consultations</strong>, <strong>Encaissements du jour</strong>, <strong>Reste à payer</strong> et nombre de <strong>Paiements</strong> ;</li>
                <li><strong>État des factures</strong> : payées, partielles, impayées, vides non validées ;</li>
                <li><strong>Assurances</strong> : parts patient encaissées ;</li>
                <li><strong>Encaissements par mode de paiement</strong>.</li>
            </ul>
            <p>C’est l’outil de contrôle du tiroir en fin de journée, pas la caisse du mois.</p>
            <ManualCallout type="tip">
                <p>Laissez <span class="ui">Temps réel</span> allumé sur le poste d’accueil : la file se met à jour dès qu’une consultation, un paiement ou un patient change. La mention « Sync en cours » signale un rafraîchissement. Si le réseau a décroché, le bouton d’actualisation recharge tout.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="fo-dentiste">
            <ManualFigure
                label="Mode Focus, dentiste"
                number="3.2"
                caption="Dossier à gauche, fiche au centre, file à droite."
                :markers="[
                    { x: 14, y: 40, text: 'Dossier patient : antécédents, allergies, ordonnances.' },
                    { x: 50, y: 40, text: 'Fiche médicale de la consultation en cours.' },
                    { x: 86, y: 40, text: 'File des consultations ; interrupteur Terminées.' },
                    { x: 88, y: 88, text: 'Bouton Clôturer.' }
                ]"
            />
            <ManualSteps>
                <ManualStep>La file affiche les consultations en attente. L’interrupteur <span class="ui">Terminées</span> est coupé par défaut : les séances déjà closes sont masquées.</ManualStep>
                <ManualStep>Cliquez une ligne. La fiche s’ouvre au centre avec le badge « En cours ».</ManualStep>
                <ManualStep>Dans le <span class="ui">Dossier patient</span> à gauche, ajoutez si besoin un antécédent, une allergie ou une ordonnance. Ces ajouts ne sont possibles que tant que la consultation n’est pas close.</ManualStep>
                <ManualStep>
                    Remplissez les sections dans l’ordre affiché : Consultation en cours, Questionnaire médical, Examens, Images &amp; documents, Bilans, Plan de traitement, Devis, Seances. Sauvegardez chaque section avec son icône d’enregistrement.
                </ManualStep>
                <ManualStep critical result="« Consultation cloturee ». Le bandeau « Cette consultation a été cloturée. » s’affiche et la fiche passe en lecture seule.">
                    Cliquez <span class="ui">Clôturer</span> et confirmez « Cloturer definitivement cette consultation ? ».
                </ManualStep>
            </ManualSteps>
            <ManualCallout type="note">
                <p>
                    Pour garder l’écran épuré, le Mode Focus n’affiche ni « Enregistrer tout » ni l’auto-sauvegarde : chaque section se sauvegarde séparément. Les boutons « Imprimer dossier » et « Modifier » du dossier patient sont également masqués ; ils restent disponibles dans Patients › Dossier. En formulaire
                    simplifié, une seule « Synthèse clinique » remplace les sections cliniques.
                </p>
            </ManualCallout>
            <ManualCallout type="tip">
                <p>« Fermer la sélection » remet l’écran sur « Aucun patient ». Sur un écran étroit, deux onglets <span class="ui">File</span> et <span class="ui">Dossier</span> remplacent les colonnes.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="fo-rdv">
            <p>
                Le mode Rendez-vous embarque l’agenda du jour dans le Mode Focus. Il sert à vérifier les créneaux à venir, à valider un rendez-vous quand le patient arrive, ou à reporter un rendez-vous au téléphone, sans quitter la file des yeux. Son fonctionnement est celui de la page Agenda (fiche 4 correspondante).
            </p>
            <ManualCallout type="note">
                <p>L’agenda embarqué ne suit pas le temps réel de la file : après une modification faite sur un autre poste, actualisez-le.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="fo-actions">
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Où dans le Mode Focus</th>
                            <th>Effet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in actions" :key="row[0]">
                            <td>{{ row[0] }}</td>
                            <td>{{ row[1] }}</td>
                            <td>{{ row[2] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </ManualSection>
    </ManualChapter>
</template>
