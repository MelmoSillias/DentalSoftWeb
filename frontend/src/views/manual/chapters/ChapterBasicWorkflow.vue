<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualDecision from '../components/ManualDecision.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualSection from '../components/ManualSection.vue';
import ManualStep from '../components/ManualStep.vue';
import ManualSteps from '../components/ManualSteps.vue';
</script>

<template>
    <ManualChapter id="workflow">
        <ManualSection id="wf-vue">
            <p>Une visite ordinaire suit toujours le même fil. Chaque maillon a son écran et son responsable ; une fois le fil compris, tout le reste du logiciel s’y rattache.</p>
            <ol class="m-flow">
                <li><strong>Patient</strong><span>Accueil · créer ou retrouver</span></li>
                <li><strong>Consultation</strong><span>Accueil · ouvrir</span></li>
                <li><strong>Soins</strong><span>Médecin · fiche et clôture</span></li>
                <li><strong>Paiement</strong><span>Accueil · régler la facture</span></li>
                <li><strong>Reçu</strong><span>Accueil · imprimer</span></li>
                <li><strong>Suivi</strong><span>Tous · rendez-vous</span></li>
            </ol>
            <div class="m-situation">
                <p class="m-situation__label">Le cas suivi dans ce chapitre</p>
                <p>Mardi, 9 h 12. Awa Koné se présente à l’accueil pour une douleur à une molaire. Elle n’est jamais venue au cabinet. Le Dr Diarra consulte ce matin. Elle paiera en espèces et repartira avec un reçu et un rendez-vous de contrôle.</p>
                <dl>
                    <div>
                        <dt>Patiente</dt>
                        <dd>Awa Koné, nouvelle</dd>
                    </div>
                    <div>
                        <dt>Médecin</dt>
                        <dd>Dr Diarra</dd>
                    </div>
                    <div>
                        <dt>Consultation</dt>
                        <dd>10 000 F, payante</dd>
                    </div>
                    <div>
                        <dt>Paiement</dt>
                        <dd>Espèces, reçu 80 mm</dd>
                    </div>
                </dl>
            </div>
            <ManualCallout type="tip">
                <p>Tout ce chapitre peut aussi se faire depuis le Mode Focus, sans changer de page (chapitre 3). Il est présenté ici avec les pages classiques parce qu’elles montrent chaque étape séparément.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-patient">
            <p>Avant de créer une fiche, cherchez toujours la personne : le logiciel ne détecte pas les doublons, et un doublon disperse l’historique, les factures et les reliquats sur deux dossiers.</p>
            <ManualSteps>
                <ManualStep>Menu <span class="path">Patients › Liste</span>. Tapez le nom ou le numéro de téléphone dans <span class="ui">Rechercher un patient</span>.</ManualStep>
                <ManualStep>Aucun résultat : cliquez <span class="ui">Ajouter un patient</span>.</ManualStep>
                <ManualStep>
                    Onglet <span class="ui">Informations personnelles</span> : saisissez au minimum le <span class="ui">Nom</span> et le <span class="ui">Téléphone</span>, puis le reste si la patiente le donne (prénom, sexe, date de naissance ou âge, adresse, profession, contact d’urgence). La date de naissance et
                    l’âge se calculent l’un l’autre.
                </ManualStep>
                <ManualStep>
                    Onglet <span class="ui">Paramètres SMS</span> : <span class="ui">Autoriser les rappels de rendez-vous</span> est coché par défaut. Décochez-le si la patiente refuse d’être contactée ; choisissez aussi si elle reçoit reçus, tickets et factures par SMS.
                </ManualStep>
                <ManualStep>
                    Si la patiente est assurée, onglet <span class="ui">Informations assurances</span> : cochez <span class="ui">Patient assuré</span>, choisissez l’<span class="ui">Assurance</span> et le <span class="ui">Taux de couverture (%)</span>, puis les champs demandés par cet organisme.
                </ManualStep>
                <ManualStep result="« Patient sauvegardé. » La nouvelle ligne clignote brièvement en vert dans la liste.">Cliquez <span class="ui">Créer</span>, puis <span class="ui">Confirmer</span>.</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Création patient"
                number="2.1"
                caption="Le dialogue « Ajouter un patient » et ses onglets."
                :markers="[
                    { x: 18, y: 14, text: 'Onglets : informations personnelles, SMS, assurance (si une assurance est active).' },
                    { x: 30, y: 42, text: 'Nom et téléphone, marqués d’un astérisque.' },
                    { x: 72, y: 42, text: 'Date de naissance ou âge : l’un calcule l’autre.' },
                    { x: 88, y: 88, text: 'Bouton Créer.' }
                ]"
            />
            <ManualCallout type="warn">
                <p>Nom et téléphone sont signalés par un astérisque, mais l’écran peut laisser passer un champ vide : le serveur refuse alors l’enregistrement. Remplissez-les systématiquement.</p>
            </ManualCallout>
            <ManualCallout type="tip">
                <p>Renseignez « Comment a-t-il connu le cabinet ? ». Dix secondes de saisie alimentent le graphique d’origine des patients, utile pour savoir quelle communication fonctionne.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-consultation">
            <p>La consultation est le dossier de la visite du jour. Elle porte le médecin, le prix de la consultation et, à la clôture, les soins réalisés. C’est elle qui génère la facture.</p>
            <ManualSteps>
                <ManualStep>Sur la ligne d’Awa Koné, cliquez l’icône stéthoscope <span class="ui">Nouvelle consultation</span>.</ManualStep>
                <ManualStep>La patiente est déjà sélectionnée. Choisissez le <span class="ui">Médecin</span> : le Dr Diarra. S’il n’y a qu’un médecin, il est présélectionné.</ManualStep>
                <ManualStep>Contrôlez la <span class="ui">Date et heure</span>.</ManualStep>
                <ManualStep critical>
                    Activez <span class="ui">Consultation payante</span> : l’interrupteur est sur « Gratuite » par défaut. Le texte devient « Payante (montant) ». Si le cabinet autorise la modification du prix, choisissez un tarif proposé ou saisissez un montant.
                </ManualStep>
                <ManualStep>La patiente n’étant pas assurée, choisissez le <span class="ui">Mode de paiement patient</span> : Espèces.</ManualStep>
                <ManualStep result="« Consultation créée. » Awa Koné entre dans la file d’attente.">
                    Cliquez <span class="ui">Créer</span> et confirmez. Pendant une dizaine de secondes, la notification propose <span class="ui">Imprimer le ticket</span> : c’est le ticket de passage de la consultation, à remettre à la patiente.
                </ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Nouvelle consultation"
                number="2.2"
                caption="Consultation payante d’une patiente non assurée : le mode de paiement est demandé dès la création."
                :markers="[
                    { x: 25, y: 22, text: 'Patiente verrouillée sur la ligne choisie.' },
                    { x: 72, y: 22, text: 'Médecin, obligatoire si le cabinet l’exige.' },
                    { x: 30, y: 60, text: 'Interrupteur Consultation payante (Gratuite par défaut) et tarif.' },
                    { x: 72, y: 60, text: 'Mode de paiement patient.' }
                ]"
            />
            <ManualDecision
                question="Le dialogue « Consultation en cours » s’affiche : une consultation est déjà ouverte pour ce patient."
                :options="[
                    { when: 'Visite en cours', then: 'Cliquez « Compris » : le patient est déjà dans la file. Continuez avec la consultation existante.' },
                    { when: 'Ouverte par erreur', then: 'Si aucune fiche n’y est liée, cliquez « Annuler la consultation » (« La consultation en cours a été supprimée. »), puis recréez-la correctement.' },
                    { when: 'Fiche déjà liée', then: 'Le message indique qu’elle ne peut pas être supprimée. Faites-la clôturer par le médecin.' }
                ]"
            />
            <ManualCallout type="note">
                <p>Une seule consultation peut être ouverte par patient. C’est volontaire : cela empêche de facturer deux fois la même visite.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-cloture">
            <p>La clôture enregistre ce qui a été fait (médecin, salle, soins) et fige la séance. Deux chemins existent selon la complexité de la visite.</p>
            <div class="m-grid-2">
                <div class="m-card m-card--accent">
                    <h4>Visite simple · à l’accueil</h4>
                    <p>
                        Depuis le Mode Focus (mode Reception) ou l’Historique, ouvrez la consultation puis <span class="ui">Clôture rapide</span>. Renseignez <span class="ui">Médecin</span>, <span class="ui">Salle</span>, <span class="ui">Aide(s) soignant(e)s</span> et <span class="ui">Soins réalisés</span>, puis
                        <span class="ui">Clôturer</span>.
                    </p>
                    <p>Sauf réglage contraire, le médecin tape son mot de passe et clique <span class="ui">Vérifier</span> avant que l’accueil accède au formulaire.</p>
                </div>
                <div class="m-card m-card--accent">
                    <h4>Visite clinique · au fauteuil</h4>
                    <p>
                        Le médecin ouvre <span class="path">Consultations › File d'attente</span> et double-clique la carte, ou sélectionne la ligne dans le mode Dentiste du Focus. Il enregistre chaque section de la fiche, ajoute soins et ordonnance, puis <span class="ui">Clôturer</span>.
                    </p>
                </div>
            </div>
            <p>Pour Awa Koné, le Dr Diarra utilise la fiche complète :</p>
            <ManualSteps>
                <ManualStep>Il ouvre la fiche depuis la file. Le badge indique « En cours ».</ManualStep>
                <ManualStep>Il remplit les sections utiles (Questionnaire médical, Examens, Plan de traitement) et clique <span class="ui">Sauvegarder</span> sur chacune.</ManualStep>
                <ManualStep>Dans <span class="ui">Consultation en cours</span>, il choisit la salle, le type « Première Consultation », ajoute les soins réalisés et, si besoin, une ordonnance avec <span class="ui">Nouvelle ordonnance</span>.</ManualStep>
                <ManualStep critical result="La consultation est clôturée ; la fiche passe en lecture seule. La facture des soins est prête à être encaissée.">
                    Il clique <span class="ui">Clôturer</span> et confirme « Cloturer definitivement cette consultation ? ».
                </ManualStep>
            </ManualSteps>
            <ManualFigure label="Clôture de consultation" number="2.3" compact caption="Après la clôture, la fiche ne se modifie plus." />
            <ManualCallout type="critical">
                <p>La clôture est définitive pour la partie clinique. Vérifiez les soins ajoutés avant de confirmer : ce sont eux qui déterminent le montant de la facture et la part du médecin.</p>
            </ManualCallout>
            <ManualCallout type="note">
                <p>
                    La clôture est refusée tant qu’aucun médecin n’est choisi (« Veuillez sélectionner un médecin avant de sauvegarder ou clôturer. ») ou qu’une section modifiée n’a pas été sauvegardée (« Toutes les sections doivent être sauvegardées avant la clôture. »).
                </p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-paiement">
            <ManualSteps>
                <ManualStep>Menu <span class="path">Caisse › Encaissements</span>. Pour l’accueil, la page s’ouvre sur la journée.</ManualStep>
                <ManualStep>Repérez la facture d’Awa Koné. Son statut est <span class="ui">Impayé</span>.</ManualStep>
                <ManualStep>Cliquez <span class="ui">Régler</span>. Le dialogue « Régler la facture » affiche Total facture, Déjà payé et Reste patient ; le montant proposé est le reste.</ManualStep>
                <ManualStep>
                    Contrôlez le <span class="ui">Mode de paiement patient</span>, la <span class="ui">Date</span> et l’<span class="ui">Heure</span>. Pour un acompte, diminuez le <span class="ui">Montant patient</span> : il doit être supérieur à zéro et ne pas dépasser le reste. « Reste après paiement » affiche ce qui
                    restera dû.
                </ManualStep>
                <ManualStep critical result="« Paiement enregistré ». La facture passe à Payé, ou à Partiellement payé s’il reste un solde (le dialogue reste alors ouvert).">Cliquez <span class="ui">Confirmer le paiement</span>.</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Encaissement"
                number="2.4"
                caption="Le dialogue « Régler la facture »."
                :markers="[
                    { x: 22, y: 25, text: 'Total, déjà payé et reste à payer.' },
                    { x: 70, y: 45, text: 'Montant, prérempli au reste.' },
                    { x: 30, y: 65, text: 'Mode de paiement, date et heure.' },
                    { x: 85, y: 88, text: 'Confirmer le paiement.' }
                ]"
            />
            <ManualDecision
                question="La patiente ne peut pas tout payer aujourd’hui."
                :options="[
                    { when: 'Elle paie une partie', then: 'Saisissez le montant versé. La facture devient Partiellement payé ; le reste sera signalé à sa prochaine visite.' },
                    { when: 'Elle ne paie rien', then: 'N’enregistrez rien. La facture reste Impayé et apparaît comme reliquat. Ne supprimez pas la facture.' }
                ]"
            />
            <ManualCallout type="note">
                <p>
                    Une facture d’un montant nul ne propose pas <span class="ui">Régler</span> mais <span class="ui">Valider</span> : cela confirme qu’elle est vide, sans créer de paiement (« Facture vide validée »). Dès qu’un premier paiement existe, la facture ne se modifie plus : vérifiez les lignes avant
                    d’encaisser un acompte.
                </p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-recu">
            <ManualSteps>
                <ManualStep>Juste après le paiement, la notification affiche <span class="ui">Imprimer le reçu</span> pendant une dizaine de secondes. Cliquez-le.</ManualStep>

                <ManualStep>Notification disparue ? Ouvrez l’onglet <span class="ui">Paiements</span> de la caisse, retrouvez la ligne et cliquez <span class="ui">Reçu</span>.</ManualStep>
                <ManualStep result="Le reçu sort sur l’imprimante ticket 80 mm du poste.">Remettez le reçu à la patiente.</ManualStep>
            </ManualSteps>
            <ManualFigure label="Reçu de paiement" number="2.5" compact caption="Le reçu ne couvre que le versement du jour, pas toute la facture." />
            <div class="m-table-wrap">
                <table class="m-table">
                    <caption>
                        Trois documents à ne pas confondre
                    </caption>
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Quand il sort</th>
                            <th>Ce qu’il prouve</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ticket de consultation</td>
                            <td>À la création de la consultation</td>
                            <td>Le passage du patient et son ordre dans la journée.</td>
                        </tr>
                        <tr>
                            <td>Facture</td>
                            <td>Bouton Aperçu, depuis la caisse ou le Focus</td>
                            <td>Le détail des actes et le montant dû.</td>
                        </tr>
                        <tr>
                            <td>Reçu</td>
                            <td>Après chaque paiement</td>
                            <td>La somme versée ce jour-là et le mode de paiement.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="tip">
                <p>Si l’écran indique « Impression indisponible », la facture n’est pas en cause : vérifiez que l’imprimante ticket du poste est allumée et sélectionnée, puis relancez depuis l’onglet Paiements.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="wf-rdv">
            <ManualSteps>
                <ManualStep>Menu <span class="path">Agenda › Rendez-Vous</span>. Passez de <span class="ui">Vue hebdomadaire</span> à <span class="ui">Vue journalière</span> pour voir les créneaux du Dr Diarra.</ManualStep>
                <ManualStep>Cliquez un créneau marqué « Disponible », ou le bouton <span class="ui">+</span>. Le médecin et l’heure se remplissent.</ManualStep>
                <ManualStep>Choisissez Awa Koné, une <span class="ui">Durée (minutes)</span> et un <span class="ui">Motif</span>, par exemple « Contrôle ».</ManualStep>
                <ManualStep result="« Rendez-vous créé. » Le rendez-vous est au statut En attente.">Cliquez <span class="ui">Créer</span>.</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Nouveau rendez-vous"
                number="2.6"
                caption="Depuis l’agenda, le créneau est prérempli ; depuis le dossier patient (bouton RDV), c’est le patient qui l’est."
                :markers="[
                    { x: 20, y: 20, text: 'Bascule entre vue hebdomadaire et vue journalière.' },
                    { x: 55, y: 55, text: 'Créneau libre du médecin.' },
                    { x: 88, y: 20, text: 'Bouton + pour un rendez-vous hors créneau.' }
                ]"
            />
            <ManualDecision
                question="Le jour venu, l’accueil choisit « Valider » sur le rendez-vous. Le dialogue « Valider le rendez-vous » demande : « Créer une consultation maintenant ? »"
                :options="[
                    { when: 'Patient présent', then: 'Répondez Oui, puis Valider : la consultation est créée et le patient entre dans la file.' },
                    { when: 'Validation à l’avance', then: 'Répondez Non, puis Valider : « Le rendez-vous sera validé sans consultation ni attribution de médecin. » Créez la consultation à son arrivée.' }
                ]"
            />
            <ManualCallout type="warn">
                <p>Répondre Oui pour un patient absent laisse une consultation ouverte dans la file toute la journée, et bloque toute nouvelle consultation pour lui tant qu’elle n’est pas annulée ou clôturée.</p>
            </ManualCallout>
        </ManualSection>
    </ManualChapter>
</template>
