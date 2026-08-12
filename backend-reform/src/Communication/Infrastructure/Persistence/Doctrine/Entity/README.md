# Communication Doctrine Entity

ORM-mapped Communication entities live here under `App\Communication\Infrastructure\Persistence\Doctrine\Entity\*`:

- `SmsTemplate`
- `SmsLog`
- `Notification`
- `SmsProviderConfig`
- `SmsQueue`

Tables and column mappings are unchanged. Domain models remain pure under `App\Communication\Domain\Model` (no Doctrine attributes).

Persistence for Communication aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (where introduced)
- Legacy `App\Communication\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
