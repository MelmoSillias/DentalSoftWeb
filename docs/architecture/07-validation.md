# Validation de la refonte

Vérification locale du 14 novembre 2026, PHP 8.2.12, dépendances installées depuis le lock Composer existant.

| Contrôle | Résultat |
| --- | --- |
| Suite avant migration | 131 tests, 636 assertions, deux tests MySQL optionnels ignorés |
| Suite finale après extraction Focus | 165 tests, 4 105 assertions, deux tests MySQL optionnels ignorés, aucun échec |
| Conteneur Symfony | Injection et types validés |
| Configuration YAML | 26 fichiers valides |
| Mapping Doctrine | Valide ; synchronisation avec la base de cabinet volontairement non exécutée |
| Composer | Configuration valide |
| Diagnostic éditeur | Aucune erreur signalée dans les fichiers vérifiés |
| Comparaison Focus avant/après | 12 réponses complètes strictement identiques |

La suite couvre notamment la caisse, les assurances, les règles de paie/INPS, les permissions, la confidentialité, les sessions, la rotation et le rollback des refresh tokens, les espaces cabinet/patient, les appareils, les collisions d'emails, le routage réel et la résolution des classes déplacées. La désérialisation d'un ancien message Messenger est vérifiée. Les projections du tableau de bord utilisent une base SQLite en mémoire et une horloge fixe.

Les deux tests MySQL existants restent soumis à leur activation explicite. Cette vérification ne constitue pas un essai de concurrence entre plusieurs workers MariaDB : le scénario de rotation teste notamment le refus d'une consommation par un lecteur devenu obsolète. La synchronisation et la migration d'une base de cabinet existante n'ont pas été lancées.

## Mise en service

Appliquer la [migration des refresh tokens](../../api/migrations/Version20261114090000.php) dans le processus de déploiement prévu, puis vider/réchauffer le cache et redémarrer les workers. Conserver les volumes, clés JWT et données existants. Les utilisateurs doivent se reconnecter, car les anciens JWT ne contiennent pas les nouveaux claims. Planifier la commande de purge des refresh tokens expirés.

Le frontend n'a pas été restructuré dans cette phase. Il continue à utiliser ses routes historiques et le champ `token` ; l'[analyse de sa migration](05-refonte-frontend.md) détaille l'adoption des nouvelles routes, du refresh et du découpage par domaines. Les couplages Doctrine historiques restant dans d'autres services sont décrits dans l'[architecture backend](02-backend-cible.md).
