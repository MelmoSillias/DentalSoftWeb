# Mise en production du suivi INPS

Cette livraison modifie la base, les API de paie et l’interface. Déployer les deux applications ensemble, pour chaque cabinet ayant sa propre base. Aucune nouvelle variable d’environnement n’est nécessaire.

## Avant le déploiement

1. Sauvegarder la base et vérifier que la sauvegarde peut être restaurée. Valider la livraison sur une copie de production.
2. Suspendre les saisies de paie et de trésorerie pendant la migration et le déploiement. Éviter de faire tourner simultanément plusieurs instances qui exécutent les migrations.
3. Contrôler les paies historiques : le net et les retenues d’un même employé/mois doivent être cohérents entre les versements. La répétition du même INPS sur plusieurs paiements est normale dans l’ancien modèle : le suivi le compte une seule fois.

Contrôle SQL en lecture seule, avant migration :

```sql
SELECT employe_id, year, month,
       COUNT(*) AS paiements,
       MIN(inps) AS inps_min, MAX(inps) AS inps_max,
       MIN(calculated_amount) AS net_min, MAX(calculated_amount) AS net_max,
       SUM(paid_amount) AS total_verse
FROM salary_payment
WHERE frequence_snapshot = 'mensuel'
GROUP BY employe_id, year, month
HAVING MIN(inps) <> MAX(inps)
    OR MIN(calculated_amount) <> MAX(calculated_amount)
    OR SUM(paid_amount) > MAX(calculated_amount);
```

Les résultats éventuels nécessitent un contrôle métier avant de remettre l’application en service. Aucune correction arbitraire des anciennes valeurs n’est faite par la migration.

## Migration et déploiement

La migration [Version20261101120000](../backend-reform/migrations/Version20261101120000.php) ajoute les statuts, versions et historiques de paie, les périodes INPS et les remises. Elle identifie les transactions déjà liées à la paie et conserve les données existantes. Elle harmonise les montants de trésorerie à deux décimales, comme ceux de la paie.

Depuis le répertoire du backend, sur le serveur cible :

```sh
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
php bin/console doctrine:migrations:status --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console app:doctrine:ensure-proxies --env=prod
```

Sur Dokploy, le [script de démarrage](../backend-reform/docker/entrypoint.sh) exécute déjà migration et préparation du cache lorsque `RUN_MIGRATIONS=1` (valeur par défaut). Vérifier les journaux et le statut de migration. Un échec de migration bloque désormais le démarrage ; ne pas le contourner en désactivant les migrations sur une base incomplète.

Ne pas utiliser `doctrine:schema:update --force` à la place des migrations. La migration cible MariaDB/MySQL, comme les déploiements existants. Ses modifications DDL ne sont pas transactionnelles : en cas d’échec partiel, inspecter la base avant toute reprise.

Déployer ensuite le frontend avec la configuration habituelle du cabinet. Demander aux utilisateurs d’appliquer la mise à jour PWA, puis de recharger/reconnecter les sessions. Un ancien frontend ne fournit pas les informations de contrôle désormais requises pour les écritures de paie.

## Reprendre les remises INPS historiques

Les retenues historiques sont calculées automatiquement à partir des paies actives. Les anciennes remises à la direction ne peuvent pas être déduites automatiquement des données de paie.

Pour chaque trimestre concerné, dans Gestion RH → INPS :

- Vérifier les retenues affichées avec les documents de paie.
- Si une ancienne remise est déjà enregistrée comme dépense dans Finances, utiliser « La sortie est déjà enregistrée dans Finances » et indiquer le numéro de cette transaction. Saisir les mêmes montant, date et mode, ainsi que le bénéficiaire. La dépense est liée sans créer une seconde sortie. La transaction doit être manuelle, validée et non liée à une autre opération.
- Si la remise n’a jamais été enregistrée dans la trésorerie de cette application, vérifier l’effet sur les soldes d’ouverture avant de la saisir comme une nouvelle remise historique. Ne pas recréer une dépense déjà incluse dans un solde initial.
- Un trimestre réellement soldé peut être clôturé après rapprochement. La clôture bloque également les écritures de paie de ses trois mois. La réouverture nécessite un motif et reste dans l’historique.

Une correction ultérieure d’INPS conserve les remises réelles et affiche un complément ou un excédent. Un excédent n’est pas transféré automatiquement sur un autre trimestre. Il doit être régularisé sur justificatif ; cette livraison ne crée pas automatiquement de remboursement ni de réaffectation entre trimestres.

## Vérification après livraison

Avec un administrateur, sur l’environnement de recette :

1. Enregistrer deux versements d’un salaire : la retenue doit être comptée une seule fois.
2. Vérifier le regroupement des paiements et leur affichage dans la fenêtre de salaire.
3. Vérifier le refus d’un dépassement du net, d’un changement de calcul avec plusieurs paiements et de l’annulation du premier tant qu’un autre reste actif.
4. Corriger un versement : la transaction d’origine reste visible comme annulée et une nouvelle transaction porte le montant corrigé. Les soldes excluent l’ancienne transaction.
5. Enregistrer une remise, imprimer son bordereau, puis corriger la retenue : la confirmation précise l’effet, la remise reste conservée et le solde INPS change.
6. Vérifier qu’une transaction de paie/INPS ne peut plus être modifiée, rejetée ou supprimée directement depuis Finances.
7. Vérifier qu’un utilisateur non administrateur ne peut pas accéder aux API de paie et INPS.

Les tests automatisés d’intégrité utilisent une base SQLite en mémoire ; ils ne modifient aucune base de cabinet. La validation de la migration et des accès concurrents MariaDB reste à faire en recette avant production.

## Retour arrière

La migration refuse volontairement un retour arrière qui supprimerait les historiques financiers. Préférer une correction de livraison. Si une restauration devient nécessaire, coordonner restauration de la sauvegarde et retour aux versions compatibles des deux applications, après avoir sauvegardé et rapproché toutes les opérations réalisées depuis le déploiement.
