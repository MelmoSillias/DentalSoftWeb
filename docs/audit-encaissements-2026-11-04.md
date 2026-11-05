# Audit des encaissements du 4 novembre 2026

## Conclusion

Les deux sauvegardes contiennent les mêmes **16 paiements du 4 novembre, pour 350 000 FCFA**. Les montants, dates et liens de ces paiements et de leurs transactions validées sont identiques. Aucun paiement manquant ou montant financier modifié n'explique l'écart annoncé.

Il existe toutefois une modification de classification : la migration supprime l'attribution « cabinet » des actes. La radio de 10 000 FCFA de la consultation 9678, auparavant exclue du chiffre médecin, entre désormais dans ce chiffre. Les nouvelles tables de services cabinet sont vides dans la sauvegarde après migration : cette radio n'y a pas été transférée.

Les 370 000 FCFA annoncés résultent de l'addition du **facturé médecin** ancien (340 000) et d'un paiement d'ancienne consultation (30 000). Cette addition comprend une facture du jour impayée de 30 000 et exclut la radio cabinet payée de 10 000.

**370 000 − 30 000 impayés + 10 000 de radio cabinet payée = 350 000 FCFA réellement encaissés auprès des patients.**

## Sources et périmètre

- Ancien commit : `e40b30e7526d5274948d4dfc6d2d78d799d76329` (23 septembre 2026).
- Nouveau commit : `422c299c0890adb33b485f3d33ac71b59e0a61bf` (3 novembre 2026).
- Avant migration : `manual_backup_20261104_184040.sql`, schéma avec attribution des actes.
- Après migration : `manual_backup_20261104_185900.sql`, schéma sans attribution et avec tables services cabinet.
- Fenêtre testée : **2026-11-04 00:00:00 à 23:59:59**, d'après les dates enregistrées. Aucun résultat périodique sur d'autres journées n'est présenté. La consultation du 3 novembre est examinée uniquement parce que son paiement a été reçu le 4 novembre.
- Le composant joint en lecture seule a été consulté ; il n'a pas été modifié.
- Aucun import SQL, aucune connexion à la base applicative et aucune modification du code métier. Les modifications préexistantes dans le projet sont conservées.

## Résultats des calculs métiers

| Indicateur | Ancien commit / avant migration | Nouveau commit / après migration |
| --- | ---: | ---: |
| Facturé dans les rapports médecins | 340 000 | 350 000 |
| Paiements sur les consultations du jour attribués aux médecins | 310 000 | 320 000 |
| Paiements reçus sur consultations antérieures | 30 000 | 30 000 |
| Total encaissé attribué aux médecins, reliquats inclus | 340 000 | 350 000 |
| Facturé et payé attribué séparément au cabinet | 10 000 | 0 |
| Facturé du jour non payé | 30 000 | 30 000 |
| Encaissements patients, médecins et cabinet réunis | **350 000** | **350 000** |

Le nouveau calcul appliqué à la sauvegarde **avant** migration donne également 350 000 encaissés et 350 000 facturés. Le changement du périmètre métier suffit donc à reproduire les nouveaux résultats, même avant suppression physique de la colonne d'attribution.

## Les trois écritures qui expliquent l'écart

| Nature | Consultation | Facture | Acte / paiement | Montant | Constat |
| --- | ---: | ---: | --- | ---: | --- |
| Facturé du jour impayé | 9670 | 9228 | Acte 6846 | 30 000 | Extraction du 04/11 ; facture non réglée ; aucun paiement associé dans les sauvegardes |
| Paiement reçu sur consultation antérieure | 9646 | 9207 | Paiement 6286 ; transaction 6380 | 30 000 | Consultation du 03/11, paiement du 04/11 à 09:43, validé |
| Radio payée auparavant attribuée au cabinet | 9678 | 9225 | Acte 6834 ; paiement 6296 ; transaction 6391 | 10 000 | Attribution `cabinet` avant migration ; paiement du 04/11 à 13:32, validé et conservé |

Le reliquat payé de 30 000 et la facture impayée de 30 000 concernent **deux consultations différentes**. Leur montant identique peut masquer la confusion entre facturation et encaissement.

La note libre de l'acte 6846 contient « RESTE 50000 », mais son prix structuré est 30 000 et sa quantité vaut 1. Le calcul utilise ces champs structurés, identiques dans les deux sauvegardes.

## Comparaison des données de la journée

Les assertions ont vérifié l'égalité des colonnes communes avant/après pour :

- les 28 consultations datées du 4 novembre ;
- leurs actes et factures ;
- les 16 paiements datés du 4 novembre ;
- les transactions datées du 4 novembre, notamment montant, statut et rattachement au paiement.

Tous les paiements patients du jour ont exactement une transaction associée, validée, de même montant et datée du jour. Les références de consultation et de facture nécessaires au paiement sur activité antérieure sont également conservées.

La suppression de l'attribution est explicite dans [Version20260928180000.php](../backend-reform/migrations/Version20260928180000.php) : `ALTER TABLE acte_medical DROP attribution`. Cette migration crée les tables de services cabinet mais ne contient pas de transfert des anciens actes cabinet. Sur la journée étudiée, l'effet identifié est la radio de 10 000 FCFA.

## Particularité de l'impression détaillée

Les fonctions d'impression des deux commits ont été exécutées avec les données produites par leur service métier :

| Récapitulatif de la liste des soins détaillée | Ancien commit | Nouveau commit |
| --- | ---: | ---: |
| Total facturé | 340 000 | 350 000 |
| Total payé sur les soins du jour, affiché « Total encaissé » auparavant | 320 000 | 320 000 |
| Total des paiements de reliquats | 30 000 | 30 000 |
| Somme des paiements du jour et des reliquats | **350 000** | **350 000** |

Dans l'ancien service, le champ de ligne `montant` exclut la part cabinet, tandis que `montantPaye` reprend les paiements de toute la consultation. L'impression additionne ces champs : elle affiche donc un facturé hors cabinet de 340 000 et des paiements sur soins incluant la radio cabinet de 320 000. Ce défaut de cohérence entre colonnes existait déjà.

L'ancien code ne reproduit pas 370 000 comme encaissement réel sur ces sauvegardes. Il reproduit **340 000 facturés + 30 000 de reliquats payés**, mais le bon calcul de l'impression est **320 000 payés sur les soins + 30 000 de reliquats = 350 000**. Si un ancien document indique explicitement 370 000 dans une colonne d'encaissement, ce document n'est pas reproduit par les deux commits et les sauvegardes fournis.

## Services et vérification technique

- [ReportService.php](../backend-reform/src/Reporting/Service/ReportService.php) : l'ancienne version utilisait `ActAttributionService` pour exclure les actes cabinet du facturé et répartir les paiements. La nouvelle version affecte les actes de consultation et leurs paiements intégralement aux médecins.
- [CabinetServiceBillingService.php](../backend-reform/src/Billing/Service/CabinetServiceBillingService.php) : les services cabinet sont désormais suivis dans des structures distinctes, vides dans la sauvegarde après migration.
- [DoctorReportsTable.vue](../frontend/src/components/rapport/common/DoctorReportsTable.vue) : les sommes du facturé, des paiements des soins et des paiements de reliquats sont distinctes. Le nouveau libellé « Base de rémunération (assurance incluse) » ne signifie pas que toute la facturation est encaissée.
- Le mécanisme de sélection des paiements validés et le rattachement du paiement de 30 000 à une activité antérieure ne changent pas le résultat du jour.
- La rémunération calculée des médecins de cette journée est identique dans les deux versions : 94 500 FCFA. Les 10 000 reclassés concernent ici un médecin dont la rémunération calculée dans ces données est nulle ; ce résultat ne préjuge pas d'autres journées.

**13 assertions réussies**, comprenant lecture des dumps sans exécution SQL, rapprochement des paiements, exécution PHP des services et entités extraits de chaque commit, comparaison du nouveau calcul sur les deux sauvegardes, et exécution JavaScript des fonctions réelles d'impression. Les objets sont reconstruits en mémoire ; ce n'est pas un test de déploiement complet ni une migration rejouée sur un serveur SQL.

Les résultats chiffrés et les assertions figurent dans [audit-encaissements-2026-11-04-resultats.json](./audit-encaissements-2026-11-04-resultats.json).

Le total de 350 000 concerne les **paiements patients** du périmètre demandé. Une entrée manuelle distincte de 450 000, libellée « REMBOURSEMENT SANLAM », existe aussi le 4 novembre et ne constitue pas un paiement patient de cette liste. Elle ne participe pas à l'écart de 20 000 étudié.

## Suites recommandées

1. Présenter séparément le facturé, les paiements sur soins du jour, les paiements de reliquats et les impayés. Le total encaissé patients doit être 320 000 + 30 000 = 350 000.
2. Corriger la reprise des anciennes attributions cabinet avant de se fier aux répartitions médecin/cabinet historiques. Pour la journée étudiée, préserver la classification de l'acte 6834 et de son paiement sans créer un deuxième encaissement.
3. Harmoniser les périmètres des colonnes imprimées et les libellés. Dans le composant joint, un libellé « Paiement de réliquats » pointe sur `reliquat`, qui représente un reste non encaissé, tandis que les reliquats effectivement reçus figurent dans `revenue_reliquats`.

Aucune de ces corrections n'a été appliquée dans le cadre de cet audit.
