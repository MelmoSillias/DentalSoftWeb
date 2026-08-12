# Settings Doctrine Entity

ORM-mapped Settings entities live here under `App\Settings\Infrastructure\Persistence\Doctrine\Entity\*`:

- `AppSetting`

Tables and column mappings are unchanged. Domain models remain pure under `App\Settings\Domain\Model` (no Doctrine attributes).

Persistence for Settings aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (where introduced)
- Legacy `App\Settings\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
