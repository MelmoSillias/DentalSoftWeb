# Modifications à apporter à la gestion financière et à la caisse

## 1. Implémentation existante

Actuellement, la gestion financière de l'application est basée sur trois entités :

- **Mode de paiement** : représente les différents moyens de paiement disponibles dans la clinique, tels que les espèces, les chèques, le mobile money, les virements bancaires et les assurances.
  - type (espèces, chèque, mobile money, virement bancaire, assurance)
  - libelle (description ou nom du mode de paiement)
  - actif (indique si le mode de paiement est actuellement actif ou non)

- **Devis** (Facture) : les differentes factures des clients àprès les consultations.
  - montant (le montant total à payer pour le devis)
  - reste (le montant restant à payer pour le devis)
  - statut (indique si le devis est payé, en attente de paiement, ou annulé)
  - date (la date de création du devis)
  - type (1 : facture proforma, 2 : facture finale)
  - paiements (une liste des paiementsDevis associés à ce devis)
  - contenus (une liste des prestations ou produits inclus dans le devis)

- **PaiementDevis** : représente les paiements effectués pour les devis ou tickets de consultation, en lien avec les différents modes de paiement.
  - devise (le devis ou ticket de consultation associé à ce paiement : si lié à un devis, il s'agit d'un paiement pour une facture ; si lié à un ticket de consultation, il s'agit d'un paiement pour une consultation)
  - consultation (la consultation associé à ce paiement, s'il s'agit d'un paiement pour une consultation)
  - montant (le montant payé pour ce paiement)
  - date (la date du paiement)
  - mode de paiement (le mode de paiement utilisé pour ce paiement)
  - transaction (la transaction financière associée à ce paiement, si applicable)

- **Transaction** : représente les transactions financières réelles de chaque mode de paiement, avec des montants associés et des dates.
  - mode de paiement (le mode de paiement associé à cette transaction)
  - montant (le montant de la transaction)
  - date (la date de la transaction)

le type de mode de paiement n'influs pas actuellement sur la gestion de la caisse. tout les paiements sont traités de la même manière, indépendamment du mode de paiement utilisé.

un mode de paiement par défaut est défini et il est de type espèces.
tout les paiements de type espèces sont supposés être encaissés directement dans la caisse, tandis que les autres types de paiements sont considérés comme des paiements électroniques qui n'affectent pas directement la trésorerie physique de la clinique.

## 2. Les attentes des modifications

### 2.1. Gestion des modes de paiement

Organisation de l’interface

Afin d’améliorer la lisibilité et l’expérience utilisateur, la page sera structurée en deux onglets (Tabs) :

**1. Onglet** : “Tableaux”

Cet onglet contiendra :

En haut : le tableau des transactions
En bas : le tableau des modes de paiement

**2. Onglet** : “Graphiques”

Cet onglet affichera :

les graphes existants et des nouveaux graphiques spécifiques aux modes de paiement, tels que la répartition des paiements par mode de paiement, l’évolution des paiements par mode de paiement dans le temps, etc.

## Structuration des modes de paiement"

les modes de paiement seront regroupés en rowsgroup dans la table.

Regroupés sous un en-tête :

1. Modes de paiement classiques

Inclut :

Espèces
Chèque
Mobile Money
Virement bancaire

2. Assurances

Regroupées sous un en-tête :

Assurances

Spécificités :

Une colonne supplémentaire :
➜ Pourcentage de prise en charge (%)
Ce pourcentage représente la part couverte par l’assurance lors d’un paiement

**Création / Modification d’un mode de paiement**

Lorsqu’un mode de paiement est de type assurance :

Un champ obligatoire doit apparaître :
Pourcentage de prise en charge
Cette valeur sera utilisée automatiquement :

- lors du paiement des consultations
- lors du règlement des factures associées

## 2.2. Dialogues d’ajout de consultation

Cas : Consultation payante

Lorsque l’option “Consultation payante” est activée :

1. Modes de paiement proposés
Le dropdown principal affiche uniquement :
➜ les modes de paiement classiques

Les assurances sont exclues par défaut
2. Gestion des assurances

Un bouton toggle “Assurance” apparaît sous l’option “Consultation payante”.

Si activé :

Un input group est affiché, contenant :

Un dropdown des assurances
Un champ pourcentage de prise en charge
Rempli automatiquement selon l’assurance choisie
Modifiable si nécessaire
Un champ reste à payer par le client

Calculé automatiquement :

Reste à payer = Montant total - (Montant × % assurance)

## 2.3. Dialogues de paiement des factures

Lors du paiement d’une facture, même logique que pour les consultations payantes s’applique :

1. Modes de paiement proposés par défaut sont les modes de paiement classiques
2. Option d’activer les assurances via un toggle
3. Si les assurances sont activées, affichage d’un input group similaire à celui des consultations payantes, avec dropdown des assurances, champ pourcentage de prise en charge et champ reste à payer par le client.

NB: mais le paiement par assurance ne doit être proposé qu'une seule fois par facture et sur les factures qui n'ont pas encore de paiement enregistré.

au niveau backend dans les deux cas (consultation payante et paiement de facture), il faudra crées un couple d'enregistrement PaiementDevis et Transaction le paiement effectué par un mode de paiement classique, et un enregistrement simple transaction pour les paiements effectués par assurance, avec le montant correspondant à la part prise en charge par l’assurance.

il faudra aussi ajouter un champ de type boolean dans l’entité Transaction pour savoir si une transaction a été validée ou pas, les transactions de types espèces et mobile money seront validées automatiquement, tandis que les transactions des autres types de paiement (chèque, virement bancaire et assurance) seront créées avec un statut non validé, en attente de validation manuelle par l’administrateur.

la validation de fera dans la table des transactions, en cliquant sur un bouton de validation à côté de chaque transaction en attente de validation, ce qui mettra à jour le statut de la transaction et, dans le cas d’un paiement par chèque ou virement bancaire, créera également un enregistrement PaiementDevis associé à la transaction validée.

il faudra aussi ajouter une colonne "Statut" dans la table des transactions pour indiquer si une transaction est en attente de validation, validée ou rejetée.

aussi dans les statistiques, il mettre en evidence les paiements en attente de validation, validés et permettre de filtrer les transactions selon leur statut.

## 2.4. Au niveau de la table paiementDevis dans caisse 

il faudra ajouter un filtre de recherche par mode de paiement, pour permettre à l’utilisateur de filtrer les paiements affichés dans la caisse en fonction du mode de paiement utilisé. seul les classiques car les autres n'auront pas d'enregistrement dans paiementDevis, mais seront visibles dans les transactions.


il faudra aussi separer les soldes par mode de paiement, en affichant le solde total au niveau des stats cards. et affichée par defaut le solde des espèces, avec la possibilité de basculer pour voir les soldes des autres modes de paiement.

## 3. Implémentation proposée

propose une implémentation détaillée des modifications attendues, en précisant les étapes à suivre, les changements à apporter au niveau de la base de données, du backend et du frontend, ainsi que les tests à réaliser pour valider ces modifications.
