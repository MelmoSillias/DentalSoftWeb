<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualSection from '../components/ManualSection.vue';
import ManualStep from '../components/ManualStep.vue';
import ManualSteps from '../components/ManualSteps.vue';

const checklist = [
    { id: 'mr-postes', what: 'Autoriser les postes du cabinet', why: 'Un poste non approuvé reste bloqué sur l’écran d’attente.', where: 'Paramètres généraux › Administration' },
    { id: 'mr-cabinet', what: 'Nom du centre, horaires, règles de consultation', why: 'Les SMS, la grille de l’agenda et l’obligation de médecin en dépendent.', where: 'Paramètres généraux › Cabinet' },
    { id: 'mr-tarifs', what: 'Tarifs de consultation et catalogue des soins', why: 'La consultation payante et les actes proposent ces montants.', where: 'Paramètres généraux › Cabinet' },
    { id: 'mr-paiements', what: 'Au moins un mode de paiement actif', why: 'Sans mode actif, ni l’encaissement ni la paie ne sont possibles.', where: 'Administration › Finances' },
    { id: 'mr-salles', what: 'Les salles de soins', why: 'La clôture demande dans quelle salle le patient a été vu.', where: 'Administration › Salles' },
    { id: 'mr-employes', what: 'Les employés, dont les médecins', why: 'Un médecin est d’abord une fiche RH de type Médecin.', where: 'Administration › Gestion RH' },
    { id: 'mr-comptes', what: 'Les comptes et leurs mots de passe', why: 'La fiche RH ne permet pas de se connecter ; le compte, si.', where: 'Administration › Utilisateurs' },
    { id: 'mr-assurances', what: 'Les assurances acceptées', why: 'L’onglet assurance du patient n’apparaît que si une assurance est active.', where: 'Administration › Finances' },
    { id: 'mr-fermetures', what: 'Les fermetures prévues', why: 'Évite de poser des rendez-vous un jour fermé.', where: 'Agenda › Evenements' },
    { id: 'mr-test', what: 'Le test à blanc', why: 'Vérifie d’un coup médecin, tarif, paiement et imprimante.', where: 'Section 1.11' }
];

function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <ManualChapter id="mise-en-route">
        <p class="m-lead">
            Le logiciel est livré vide : pas de médecin, pas de tarif, pas de moyen de paiement. Tant que ces éléments manquent, l’accueil peut créer une fiche patient, mais la consultation ne peut pas être attribuée, la facture n’a pas de montant juste et l’encaissement reste bloqué. Ce chapitre se fait une seule fois, avec un compte
            administrateur, de préférence avant l’ouverture du cabinet. Comptez environ une heure.
        </p>

        <ManualSection id="mr-checklist">
            <p>Suivez cet ordre : chaque élément s’appuie sur les précédents. Les employés doivent exister avant leurs comptes, et les modes de paiement avant le premier règlement comme avant la première paie.</p>
            <ol class="m-checklist">
                <li v-for="item in checklist" :key="item.id">
                    <span class="m-checklist__n"></span>
                    <span class="m-checklist__what">
                        <strong>{{ item.what }}</strong>
                        <span>{{ item.why }}</span>
                    </span>
                    <span class="m-checklist__where">
                        {{ item.where }}
                        <a :href="`#${item.id}`" @click.prevent="goTo(item.id)">Voir le guide</a>
                    </span>
                </li>
            </ol>
            <ManualCallout type="note">
                <p>Les SMS, le portail patient, les consommables et les avis patients sont utiles mais pas nécessaires le premier jour. Terminez d’abord cette checklist.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-postes">
            <p>Par sécurité, chaque ordinateur ou tablette doit être approuvé une fois par un administrateur avant de pouvoir afficher des données. L’approbation porte sur l’appareil, pas sur la personne.</p>
            <ManualSteps>
                <ManualStep>Ouvrez l’adresse du cabinet dans le navigateur. Le formulaire « Connexion » s’affiche.</ManualStep>
                <ManualStep>Saisissez le <span class="ui">Nom d'utilisateur</span> et le <span class="ui">Mot de passe</span>, puis cliquez <span class="ui">Se connecter</span>.</ManualStep>
                <ManualStep result="Si le poste est déjà connu, l’application s’ouvre. Sinon, l’écran « Appareil non autorise » s’affiche.">Patientez pendant la vérification du poste.</ManualStep>
                <ManualStep critical>
                    Sur un poste déjà autorisé, l’administrateur ouvre <span class="path">Paramètres › Paramètres généraux</span>, onglet Administration, bloc <span class="ui">Appareils autorisés</span>, repère l’appareil en attente et clique <span class="ui">Approuver</span>. Il confirme le message
                    <span class="msg">« Confirmer pour approuver cet appareil pour tout le cabinet ? »</span>.
                    <template #figure>
                        <ManualFigure
                            label="Appareils autorisés"
                            number="1.1"
                            compact
                            caption="Liste des appareils : la ligne en attente porte les boutons Approuver et Refuser."
                            :markers="[
                                { x: 20, y: 35, text: 'Nom et navigateur de l’appareil qui demande l’accès.' },
                                { x: 86, y: 35, text: 'Approuver, Refuser, Renommer ou Supprimer.' }
                            ]"
                        />
                    </template>
                </ManualStep>
                <ManualStep result="L’application s’ouvre dès que le statut passe à approuvé.">Sur le poste en attente, cliquez <span class="ui">Verifier l'autorisation</span>.</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Appareil en attente"
                number="1.2"
                compact
                caption="« Cet appareil n'est pas encore autorise. » Tant que l’administrateur n’a pas approuvé le poste, aucune donnée du cabinet n’est visible."
            />
            <ManualCallout type="warn">
                <p>
                    Un appareil approuvé est ouvert à tous les comptes du cabinet. Refusez tout poste que vous ne reconnaissez pas : il affichera « Cet appareil a ete refuse. Contactez un administrateur. ». L’option <span class="ui">Approbation automatique</span> supprime l’écran d’attente pour les nouveaux postes ; ne l’activez
                    que si le serveur n’est joignable que depuis le réseau du cabinet.
                </p>
            </ManualCallout>
            <ManualCallout type="tip">
                <p>Renommez chaque appareil approuvé (« Accueil », « Fauteuil 1 », « Bureau ») : le <span class="ui">Journal d'accès</span> devient lisible et un poste perdu se retrouve en un coup d’œil.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-cabinet">
            <p>
                Menu <span class="path">Paramètres › Paramètres généraux</span>, onglet Cabinet. La page est découpée en blocs, chacun avec son propre bouton <span class="ui">Enregistrer</span>. Enregistrer un bloc ne sauvegarde pas les autres : après chaque bloc modifié, cliquez son bouton.
            </p>
            <ManualSteps>
                <ManualStep>
                    Bloc <span class="ui">Identité &amp; SMS</span> : saisissez <span class="ui">Nom du centre (SMS)</span>, par exemple le nom commercial du cabinet. Ce texte signe les rappels et les reçus envoyés par SMS.
                </ManualStep>
                <ManualStep>
                    Bloc <span class="ui">Horaires d'ouverture</span> : renseignez <span class="ui">Heure d'ouverture</span> et <span class="ui">Heure de fermeture</span>, au format 24 h. La vue journalière de l’agenda commence et s’arrête à ces bornes.
                </ManualStep>
                <ManualStep>Blocs <span class="ui">Consultations &amp; réception</span>, <span class="ui">Fiche clinique</span>, <span class="ui">Caisse &amp; finances</span> et <span class="ui">Interface médecin</span> : décidez des règles de travail, résumées dans le tableau ci-dessous.</ManualStep>
                <ManualStep result="Une notification confirme l’enregistrement de chaque bloc.">Cliquez <span class="ui">Enregistrer</span> sur chaque bloc modifié.</ManualStep>
            </ManualSteps>

            <div class="m-table-wrap">
                <table class="m-table">
                    <caption>
                        Réglages à décider avant l’ouverture
                    </caption>
                    <thead>
                        <tr>
                            <th>Réglage (bloc)</th>
                            <th>Effet</th>
                            <th>Recommandation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Médecin requis à la création (Consultations &amp; réception)</td>
                            <td>Le champ Médecin devient obligatoire dans « Nouvelle consultation ».</td>
                            <td>Laissez-le activé dès que plusieurs médecins consultent.</td>
                        </tr>
                        <tr>
                            <td>Créer une consultation par défaut à la validation d’un RDV (Consultations &amp; réception)</td>
                            <td>À la validation d’un rendez-vous, la réponse « Oui » est présélectionnée pour créer la consultation.</td>
                            <td>Utile si les patients viennent surtout sur rendez-vous et sont validés à leur arrivée.</td>
                        </tr>
                        <tr>
                            <td>Afficher la clôture rapide pour la réception (Consultations &amp; réception)</td>
                            <td>L’accueil peut clôturer une visite simple sans ouvrir la fiche médicale.</td>
                            <td>Activez-le pour les cabinets à fort passage.</td>
                        </tr>
                        <tr>
                            <td>Bypass code médecin en clôture rapide (Consultations &amp; réception)</td>
                            <td>Supprime la saisie du mot de passe du médecin avant la clôture rapide faite par l’accueil.</td>
                            <td>Laissez-le désactivé : le médecin valide ainsi lui-même la clôture.</td>
                        </tr>
                        <tr>
                            <td>Formulaire simplifié de fiche consultation (Fiche clinique)</td>
                            <td>La fiche médicale se réduit à une « Synthèse clinique », au devis, aux séances et à la consultation en cours.</td>
                            <td>Pour les cabinets qui ne tiennent pas de dossier clinique détaillé.</td>
                        </tr>
                        <tr>
                            <td>Modification de facture par les secrétaires (Caisse &amp; finances)</td>
                            <td>L’accueil peut corriger une facture tant qu’aucun paiement n’y est enregistré.</td>
                            <td>Désactivé par défaut. À réserver à un accueil expérimenté.</td>
                        </tr>
                        <tr>
                            <td>Masquer le dossier ou les téléphones (Interface médecin)</td>
                            <td>Les médecins non administrateurs ne voient plus le dossier patient ou les numéros de téléphone.</td>
                            <td>Selon la politique de confidentialité du cabinet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualFigure
                label="Paramètres généraux du cabinet"
                number="1.3"
                caption="Chaque bloc a son propre bouton Enregistrer."
                :markers="[
                    { x: 15, y: 15, text: 'Onglets : Cabinet, Portail patient, Administration, et Apparence.' },
                    { x: 28, y: 45, text: 'Identité & SMS : nom du centre utilisé dans les messages.' },
                    { x: 72, y: 45, text: 'Horaires d’ouverture : bornes de la vue journalière de l’agenda.' },
                    { x: 88, y: 72, text: 'Bouton Enregistrer propre au bloc.' }
                ]"
            />
            <ManualCallout type="tip">
                <p>
                    La clôture rapide et le bypass sont liés : si la clôture rapide est masquée pour la réception, le bypass apparaît grisé. Commencez prudemment (clôture rapide activée, bypass désactivé), puis assouplissez après quelques jours d’usage.
                </p>
            </ManualCallout>
            <ManualCallout type="note">
                <p>L’onglet Apparence (thème, couleurs, police, navigation) se règle par poste et s’enregistre avec son bouton <span class="ui">Sauvegarder</span>. L’accueil peut aussi le régler sur son propre poste.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-tarifs">
            <p>Toujours dans l’onglet Cabinet des Paramètres généraux, trois réglages fixent l’argent facturé : le prix de la consultation, la liberté laissée à l’accueil sur ce prix, et le catalogue des actes.</p>
            <h4>Tarifs de consultation</h4>
            <ManualSteps>
                <ManualStep>Bloc <span class="ui">Consultations &amp; réception</span> : saisissez le <span class="ui">Prix consultation par défaut</span>, en FCFA (au moins 1).</ManualStep>
                <ManualStep>Dans <span class="ui">Prix de consultation proposés</span>, ajoutez une ligne par tarif, par exemple « Première consultation » et « Contrôle ». Enregistrez le bloc.</ManualStep>
                <ManualStep>
                    Bloc <span class="ui">Caisse &amp; finances</span> : cochez <span class="ui">Prix de consultation modifiable à la création</span> si l’accueil doit pouvoir choisir un tarif proposé ou saisir un montant. Décoché, c’est le prix par défaut qui s’applique. Enregistrez le bloc.
                </ManualStep>
            </ManualSteps>
            <h4>Catalogue des soins</h4>
            <ManualSteps>
                <ManualStep>Bloc <span class="ui">Catalogue des soins</span> : cliquez <span class="ui">Ajouter une catégorie</span>, par exemple « Soins conservateurs ».</ManualStep>
                <ManualStep>Ajoutez chaque acte de la catégorie avec sa description et son montant.</ManualStep>
                <ManualStep>
                    Dans la partie <span class="ui">Services cabinet</span>, cliquez <span class="ui">Ajouter</span> pour chaque prestation facturée au patient mais exclue de la rémunération du médecin : radiographie, produit, kit.
                </ManualStep>
                <ManualStep result="Les actes apparaissent dans la fiche médicale et la clôture rapide ; les services cabinet dans « Services cabinets ».">Cliquez <span class="ui">Enregistrer</span> sur le catalogue.</ManualStep>
            </ManualSteps>
            <ManualFigure label="Catalogue des soins" number="1.4" compact caption="Catégories, actes et montants. Une catégorie ne se supprime que lorsqu’elle est vide." />
            <ManualCallout type="note">
                <p>Distinguer acte et service cabinet est important pour la paie : un médecin rémunéré au pourcentage est payé sur les actes, jamais sur les services cabinet.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-paiements">
            <p>Menu <span class="path">Administration › Finances</span>, onglet <span class="ui">Mode de paiement</span>. La caisse, le Mode Focus et la paie utilisent tous cette même liste.</p>
            <ManualSteps>
                <ManualStep>Cliquez <span class="ui">Ajouter</span>. Le dialogue « Ajouter un mode de paiement » s’ouvre.</ManualStep>
                <ManualStep>Dans <span class="ui">Libelle</span>, saisissez le nom affiché au moment d’encaisser, par exemple « Espèces caisse » ou « Orange Money ».</ManualStep>
                <ManualStep>
                    Dans <span class="ui">Type de mode</span>, choisissez <span class="ui">Espèces</span>, <span class="ui">Virement bancaire</span>, <span class="ui">Carte bancaire</span> ou <span class="ui">Mobile Money</span>. Ajoutez des <span class="ui">Notes</span> si besoin.
                </ManualStep>
                <ManualStep result="« Mode créé. » Le mode apparaît comme actif.">Cliquez <span class="ui">Enregistrer</span>, puis confirmez « Confirmer la création du mode ? ».</ManualStep>
                <ManualStep>Recommencez pour chaque moyen réellement accepté à l’accueil.</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Modes de paiement"
                number="1.5"
                compact
                caption="Au moins un mode doit rester actif avant le premier règlement."
                :markers="[
                    { x: 88, y: 18, text: 'Bouton Ajouter.' },
                    { x: 72, y: 55, text: 'Icônes Activer / Désactiver et modification.' }
                ]"
            />
            <ManualCallout type="tip">
                <p>Créez un mode par caisse physique ou par compte (par exemple « Espèces caisse » et « Orange Money ») : les statistiques d’encaissement du jour sont ventilées par mode, ce qui facilite le contrôle du tiroir.</p>
            </ManualCallout>
            <ManualCallout type="note">
                <p>
                    Un mode désactivé (icône <span class="ui">Désactiver</span>, puis confirmation) disparaît des nouveaux paiements ; les paiements déjà enregistrés conservent son nom. Certains modes fournis avec le logiciel sont protégés : « Ce mode ne peut pas être désactivé. »
                </p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-salles">
            <ManualSteps>
                <ManualStep>Menu <span class="path">Administration › Salles</span>, bouton <span class="ui">Ajouter une salle</span>.</ManualStep>
                <ManualStep>Saisissez le <span class="ui">Nom</span>, court et explicite, par exemple « Fauteuil 1 ». La <span class="ui">Description</span> est facultative.</ManualStep>
                <ManualStep result="« La salle a été créée. » Elle devient sélectionnable dans la fiche médicale et la clôture rapide.">Cliquez <span class="ui">Ajouter</span>, puis <span class="ui">Oui, ajouter</span>.</ManualStep>
            </ManualSteps>
            <ManualFigure label="Ajout d’une salle" number="1.6" compact caption="Le nom saisi ici est celui que le médecin choisit en clôturant." />
        </ManualSection>

        <ManualSection id="mr-employes">
            <p>
                Il n’existe pas d’écran « Créer un médecin ». Un médecin se crée en deux temps : une fiche dans la gestion RH (qui il est, comment il est payé, quels jours il travaille), puis un compte de connexion (section suivante).
            </p>
            <ManualSteps>
                <ManualStep>Menu <span class="path">Administration › Gestion RH</span>, onglet <span class="ui">Employes</span>, bouton <span class="ui">Nouvel employe</span>. Le dialogue « Ajouter un employé » s’ouvre.</ManualStep>
                <ManualStep>Renseignez <span class="ui">Nom</span>, <span class="ui">Prénom</span> et <span class="ui">Téléphone</span> (obligatoires). L’e-mail est facultatif.</ManualStep>
                <ManualStep>
                    Choisissez le <span class="ui">Type de poste</span> : <span class="ui">Médecin</span>, <span class="ui">Aide soignant(e)</span>, <span class="ui">Réceptionniste</span>, <span class="ui">Admin</span> ou <span class="ui">Autre</span>. Ce choix détermine le rôle proposé à la
                    création du compte.
                </ManualStep>
                <ManualStep>
                    Renseignez la <span class="ui">Description de fonction</span> (obligatoire), la <span class="ui">Date d'embauche</span> et le <span class="ui">Type de contrat</span> : CDI, CDD, Stage ou Prestataire. Hors CDI, indiquez la durée (3 mois par défaut).
                </ManualStep>
                <ManualStep>
                    Rémunération : la <span class="ui">Fréquence</span> (Mensuel ou Journalier), puis le <span class="ui">Type de salaire</span> : Non défini, Fixe, ou <span class="ui">Pourcentage</span> (médecin uniquement, 35 % proposé, 100 % au plus). Une prime peut s’ajouter : montant
                    fixe, ou <span class="ui">% sur actes posés</span> (médecin uniquement, 10 % proposé).
                </ManualStep>
                <ManualStep>Cochez les <span class="ui">Jours travaillés</span> (du lundi au samedi) et joignez les pièces du dossier si nécessaire.</ManualStep>
                <ManualStep result="« Employe cree. »">Cliquez <span class="ui">Enregistrer</span>, puis <span class="ui">Confirmer</span> au message « Confirmer la creation ? ».</ManualStep>
            </ManualSteps>
            <ManualFigure
                label="Nouvel employé de type Médecin"
                number="1.7"
                caption="Le formulaire employé : identité, poste, contrat, rémunération et jours travaillés."
                :markers="[
                    { x: 25, y: 22, text: 'Identité et coordonnées.' },
                    { x: 72, y: 22, text: 'Type de poste : Médecin.' },
                    { x: 30, y: 62, text: 'Fréquence, type de salaire Pourcentage et prime.' },
                    { x: 75, y: 62, text: 'Jours travaillés.' }
                ]"
            />
            <ManualCallout type="warn">
                <p>Le type de poste n’est plus modifiable depuis la fiche « Détails employé » une fois l’employé créé. Vérifiez-le avant d’enregistrer.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-comptes">
            <p>Le compte permet à une personne de se connecter. Le mot de passe ne se saisit pas à la création : il se pose juste après, avec l’icône de clé.</p>
            <ManualSteps>
                <ManualStep>Menu <span class="path">Administration › Utilisateurs</span>, bouton <span class="ui">Nouvel utilisateur</span>. Le dialogue « Ajouter un utilisateur » s’ouvre.</ManualStep>
                <ManualStep>Saisissez le <span class="ui">Nom d'utilisateur</span>, celui que la personne tapera à la connexion.</ManualStep>
                <ManualStep>
                    Sur l’onglet <span class="ui">User / Employe (Staff)</span>, choisissez l’<span class="ui">Employe associe</span>. Le <span class="ui">Role</span> se remplit d’après son poste : Médecin pour un médecin, Admin pour un admin, Réceptionniste pour une réceptionniste ou une aide-soignante.
                    Pour un poste <span class="ui">Autre</span>, choisissez le rôle à la main.
                </ManualStep>
                <ManualStep result="« Utilisateur créé. » Le compte existe mais ne peut pas encore se connecter.">Cliquez <span class="ui">Créer</span> et confirmez.</ManualStep>
                <ManualStep critical result="« Mot de passe réinitialisé. » La personne peut se connecter ; un médecin apparaît désormais dans l’agenda et les consultations.">
                    Cliquez l’icône de clé de la ligne. Dans « Réinitialiser le mot de passe », saisissez le <span class="ui">Nouveau mot de passe</span>, cliquez <span class="ui">Réinitialiser</span> et confirmez. Remettez le mot de passe à la personne.
                </ManualStep>
            </ManualSteps>
            <ManualFigure label="Nouveau compte utilisateur" number="1.8" compact caption="Le compte est rattaché à la fiche RH : le rôle suit le type de poste." />
            <ManualCallout type="warn">
                <p>Oublier le compte d’un médecin est l’erreur la plus fréquente de l’installation : sa fiche RH existe, mais il n’apparaît dans aucune liste de médecins tant que son compte n’est pas créé.</p>
            </ManualCallout>
            <ManualCallout type="note">
                <p>Le rôle Réceptionniste ouvre tout l’accueil, y compris la Caisse. Une aide-soignante n’a besoin d’un compte que si elle doit réellement travailler à l’accueil.</p>
            </ManualCallout>
            <ManualCallout type="tip">
                <p>Demandez à chaque personne de changer son mot de passe dès sa première connexion, depuis <span class="ui">Mon profil</span>.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-assurances">
            <p>
                Menu <span class="path">Administration › Finances</span>, onglet <span class="ui">Assurances</span>. La liste des organismes est fournie avec le logiciel : on ne crée pas d’assurance, on active celles que le cabinet accepte.
            </p>
            <ManualSteps>
                <ManualStep>Repérez chaque organisme conventionné avec le cabinet.</ManualStep>
                <ManualStep>Cliquez <span class="ui">Activer</span>. Ajustez si besoin son nom affiché ; le code reste figé.</ManualStep>
                <ManualStep result="« Statut assurance mis à jour. » L’onglet assurance apparaît dans le formulaire patient.">Laissez désactivés les organismes non acceptés.</ManualStep>
            </ManualSteps>
            <ManualCallout type="note">
                <p>Désactiver une assurance la retire des nouveaux dossiers. Les patients déjà rattachés conservent leurs informations.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-fermetures">
            <p>Un événement se crée, puis se valide : ce sont deux gestes distincts.</p>
            <ManualSteps>
                <ManualStep>Menu <span class="path">Agenda › Evenements</span>, bouton <span class="ui">Nouvel Événement</span>.</ManualStep>
                <ManualStep>
                    Renseignez <span class="ui">Date de début</span>, <span class="ui">Date de fin</span> et un <span class="ui">Titre</span> explicite, par exemple « Fermeture – congés annuels ». La <span class="ui">Description</span> est facultative.
                </ManualStep>
                <ManualStep result="« Événement ajouté avec succès. »">Cliquez <span class="ui">Enregistrer</span>.</ManualStep>
                <ManualStep result="« Événement validé avec succès. » L’événement est marqué « Confirmé ».">
                    Faites un clic droit sur l’événement, choisissez <span class="ui">Valider</span> dans le dialogue « Actions », puis confirmez.
                </ManualStep>
            </ManualSteps>
            <ManualCallout type="note">
                <p>Les événements sont réservés à l’administrateur. Ils matérialisent une plage occupée du cabinet ; ce ne sont pas des rendez-vous patients.</p>
            </ManualCallout>
        </ManualSection>

        <ManualSection id="mr-test">
            <p>Avant d’accueillir le premier vrai patient, faites un parcours complet sur un patient fictif. Il valide en dix minutes tout ce qui précède.</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Geste</th>
                            <th>Ce qu’il vérifie</th>
                            <th>Si ça bloque</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Créer le patient « Test Installation »</td>
                            <td>Formulaire patient, assurances actives.</td>
                            <td>Onglet assurance absent : aucune assurance activée (1.9).</td>
                        </tr>
                        <tr>
                            <td>Ouvrir une consultation en activant « Consultation payante »</td>
                            <td>Médecin visible, tarif proposé.</td>
                            <td>Aucun médecin : compte manquant (1.8). Montant inattendu : tarifs (1.4).</td>
                        </tr>
                        <tr>
                            <td>Clôturer avec un acte et une salle</td>
                            <td>Catalogue, salles.</td>
                            <td>Liste vide : catalogue (1.4) ou salles (1.6).</td>
                        </tr>
                        <tr>
                            <td>Encaisser 100 F en espèces</td>
                            <td>Modes de paiement.</td>
                            <td>Aucun mode proposé : 1.5.</td>
                        </tr>
                        <tr>
                            <td>Imprimer le reçu</td>
                            <td>Imprimante ticket 80 mm du poste.</td>
                            <td>« Impression indisponible » : vérifier l’imprimante du poste.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ManualCallout type="critical" title="Avant l’ouverture">
                <p>
                    Une fois le test réussi, effacez ses traces pour que les rapports du premier mois soient justes. Le plus propre est de faire le test en mode test : onglet Administration des Paramètres généraux, cochez <span class="ui">Activer le mode test global</span> puis
                    <span class="ui">Appliquer</span>. Après le test, décochez-le, cliquez <span class="ui">Appliquer</span> et choisissez <span class="ui">Supprimer les données de test</span>. Le bouton <span class="ui">Nettoyer les tests</span> fait le même ménage à tout moment.
                </p>
            </ManualCallout>
        </ManualSection>
    </ManualChapter>
</template>
