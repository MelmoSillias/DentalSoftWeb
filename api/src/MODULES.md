# Contextes du monolithe

Voir l'[architecture de référence](../../docs/architecture/02-backend-cible.md).

Les contextes utilisent Domain, Application, Infrastructure et Presentation. La [séparation des responsabilités applicatives](../../docs/architecture/08-separation-responsabilites.md) définit les sous-sections Query, Handler, UseCase, ReadModel, Mapper, Policy et Exception, les extractions réalisées et les limites restantes. Les entités sont mappées explicitement par Doctrine ; les repositories sont liés à leurs ports dans la configuration. Les exceptions historiques (QueryBuilder et associations traversantes) sont documentées et ne constituent pas le modèle des nouvelles fonctionnalités.
