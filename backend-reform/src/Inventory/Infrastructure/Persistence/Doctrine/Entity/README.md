# Inventory Doctrine Entity

ORM-mapped Inventory entities live here under `App\Inventory\Infrastructure\Persistence\Doctrine\Entity\*`:

- `Stock`
- `Consommable`

Tables and column mappings are unchanged. Domain models remain pure under `App\Inventory\Domain\Model` (no Doctrine attributes).

Persistence for Inventory aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (where introduced)
- Legacy `App\Inventory\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
