# Scheduling Doctrine Entity

ORM-mapped Scheduling entities live here under `App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\*`:

- `Rdv`
- `Booking`
- `Salle`
- `Conge`

Tables and column mappings are unchanged. Domain models remain pure under `App\Scheduling\Domain\Model` (no Doctrine attributes).

Persistence for Scheduling aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (where introduced)
- Legacy `App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
