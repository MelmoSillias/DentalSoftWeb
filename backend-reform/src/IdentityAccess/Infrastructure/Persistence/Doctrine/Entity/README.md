# IdentityAccess Doctrine Entity

ORM-mapped IdentityAccess entities live here under `App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\*`:

- `User`
- `UserDevice`
- `UserDeviceAccessLog`
- `SalaryPayment`
- `Employe`

Tables and column mappings are unchanged. Domain models remain pure under `App\IdentityAccess\Domain\Model` (no Doctrine attributes).

Persistence for IdentityAccess aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (where introduced)
- Legacy `App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
