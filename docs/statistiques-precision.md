# Statistiques : corrections et nouveaux libellés

## Règles de calcul

- Les totaux de trésorerie, courbes de flux, soldes et synthèses financières excluent les transactions annulées, rejetées et en attente. Seules les transactions validées sont comptées.
- Les flux suivent leur **date de transaction**. Le tableau croisé financier existant conserve sa date de validation : ses libellés indiquent déjà cette base.
- Le facturé concerne l'activité de la période sélectionnée. La part patient et la part assurance incluent désormais les services cabinet. Leur somme égale le montant facturé.
- Les encaissements patients sont séparés entre activité de la période et activité hors période. Ils couvrent tous les modes de paiement, pas seulement les espèces.
- Les remboursements assurance reçus sont distincts des paiements patients et proviennent des transactions validées de remboursement des lots.
- Les créances assurance affichées sont un **état actuel, toutes périodes**, calculé sur les factures actives, avec déduction des remboursements partiels validés au niveau des lots. Une facture cabinet annulée n'y figure pas. Aucun remboursement partiel d'un lot mixte n'est arbitrairement attribué à une prestation individuelle.
- Le reste patient des services cabinet suit désormais la logique du rapport médecin : **part patient facturée sur la période moins paiements patients reçus dans cette période sur ces prestations**, avec un minimum de zéro par prestation. Ce chiffre n'est pas une dette actuelle ni un arrêté comptable historique. Un paiement ultérieur ne le réduit pas. L'ancien reste actuel est conservé dans le champ API `resteActuel` pour les services.
- La part assurance médecin reste incluse dans sa base de rémunération **sans attendre son remboursement**. Aucun changement de taux ni de formule de rémunération n'a été effectué.

Exemple : radio facturée 10 000 FCFA le 2 novembre, assurance à 80 %, paiement patient de 500 FCFA le 3 et de 1 500 FCFA le 20. Le rapport du 1er au 3 novembre indique 2 000 FCFA de part patient, 8 000 FCFA de part assurance, 500 FCFA encaissés sur cette prestation et 1 500 FCFA non encaissés dans la période. Le reste actuel est pourtant nul après le paiement du 20.

## Vue d'ensemble en bento

Les cases sont classées par nombre de sous-statistiques. Les cases avec au moins six détails sont les plus grandes ; celles avec trois à cinq détails sont intermédiaires ; celles avec un ou deux détails sont plus petites ; les effectifs simples sont compacts. La grille se réorganise sur tablette et mobile. Le mode graphique et l'impression sont conservés, avec la période sélectionnée et les précisions de calcul.

| Ancien affichage | Nouvel affichage |
| --- | --- |
| Nouveaux patients | Patients distincts reçus, avec nouvelles inscriptions et ventilation des patients reçus |
| Résultat (transactions) | Variation nette de trésorerie |
| Revenus | Entrées de trésorerie validées |
| Dépenses | Sorties de trésorerie validées |
| Total facturé | Montant facturé sur la période |
| Part à la charge des patients | Part facturée aux patients, services cabinet inclus |
| Part à la charge des assurances | Part facturée aux assurances, services cabinet inclus |
| Encaissé (patients et services) | Paiements patients reçus |
| Masse salariale | Rémunérations calculées |
| Part fixe | Fixe contractuel des employés actifs, non proratisé |
| Part variable | Variable calculé sur la période |
| Employés (total) | Employés actifs |
| Salles de consultation | Salles enregistrées |
| Produits consommables | Références de consommables |
| Utilisateurs | Utilisateurs par rôle, avec avertissement du cumul possible des rôles |

Les cases présentent les détails suivants :

- **Montant facturé sur la période** : parts patient et assurance, dont services cabinet, dont consultations et actes médicaux, paiements patients de la période sur ces prestations, part patient non encaissée dans la période.
- **Services cabinet facturés** : nombre de prestations, parts patient et assurance, encaissé sur les prestations de la période, encaissé sur les prestations hors période, part patient non encaissée dans la période.
- **Patients distincts reçus** : nouvelles inscriptions, nouvellement inscrits et reçus, déjà inscrits et reçus, consultations supplémentaires des mêmes patients, nombre actuel de dossiers actifs.
- **Variation nette de trésorerie** : entrées validées, sorties validées, dont remboursements assurance reçus. Les entrées peuvent aussi comprendre des recettes manuelles.
- **Paiements patients reçus** : sur activité de la période, sur activité hors période, dont services cabinet.
- **Remboursements assurance reçus** : part assurance facturée sur la période et assurance à recouvrer à ce jour, toutes périodes.
- **Rémunérations calculées** : fixe contractuel et variable de la période. La case distingue calcul et salaire effectivement versé et rappelle la règle de rémunération assurance.
- **Utilisateurs par rôle** : administrateurs, réceptionnistes, médecins. Le total est la somme des effectifs par rôle, pas nécessairement un nombre de comptes uniques.
- **Employés actifs**, **salles enregistrées**, **références de consommables** : cases compactes, effectifs actuels.

## Patients et consultations

- **Nouvelles inscriptions** : dossiers créés dans la période, que le patient ait été reçu ou non.
- **Patients distincts reçus** : patients ayant au moins une consultation dans la période, dédupliqués.
- **Nouvellement inscrits et reçus** : patients reçus et inscrits dans cette période.
- **Déjà inscrits et reçus** : patients reçus mais inscrits avant le début de la période.
- **Consultations supplémentaires** : consultations au-delà de la première pour les mêmes patients pendant la période. Cela ne présume pas de la nature médicale du suivi.

Cette séparation est appliquée à la vue d'ensemble, aux détails d'activité et aux rapports médecins. Dans les rapports médecins, une première apparition dans la période ne suffit plus à classer un ancien patient comme nouveau. Les détails d'un médecin sont dédupliqués pour ce médecin ; les patients de plusieurs médecins ne doivent pas être additionnés pour obtenir le nombre global de patients distincts.

Le graphique patients des détails d'activité compare les patients nouvellement inscrits **et reçus** aux patients déjà inscrits **et reçus**, plutôt que de mélanger inscriptions et visites. Le total des consultations des détails médecin additionne désormais payantes et gratuites.

## Réception et rapports médecins

| Ancien libellé | Nouveau libellé / contenu |
| --- | --- |
| Factures payées | Consultations payantes ; ajout des consultations gratuites |
| Rendez-vous planifiés, comptant uniquement ceux en attente | Rendez-vous planifiés, tous statuts, avec nombres en attente, confirmés et annulés séparés |
| Absences patients | Rendez-vous annulés ; aucune absence n'est déduite d'une annulation |
| Encaissé, côté réception | Entrées validées en espèces |
| Montant encaissé (total), côté réception | Entrées validées — tous modes |
| Montant encaissé / Total encaissé, lorsque le chiffre inclut l'assurance rémunérée | Base de rémunération (assurance incluse) |
| Part assurance, dans le détail médecin | Part assurance rémunérée |
| Dont espèces / Encaissé (espèces), lorsque tous les modes sont additionnés | Paiements patients reçus — tous modes |
| Réliquat patient / Reliquat patient | Part patient non encaissée dans la période |
| Reste / Reste à encaisser, dans les statistiques services cabinet | Part patient non encaissée dans la période |

Les graphiques des effectifs médecin et de réception comparent uniquement des volumes d'activité ; les valeurs salariales et les FCFA restent dans leurs cases dédiées. Le graphique de répartition médecin compare l'encaissement sur l'activité de la période à la part patient non encaissée sur cette activité. La rémunération assurance ne se présente plus comme un encaissement reçu. Les libellés concernés sont également corrigés dans les impressions des rapports médecins.

## Vérification

Des tests couvrent les transactions annulées/rejetées/en attente, la séparation des parts assurance et patient, les paiements de prestations hors période, les paiements ultérieurs, les créances assurance après remboursement partiel, les patients distincts et les consultations répétées. Un test garantit que la rémunération assurance médecin continue avant remboursement de l'assureur.

Cette évolution n'applique aucune migration en base et ne modifie pas les écritures financières existantes.
