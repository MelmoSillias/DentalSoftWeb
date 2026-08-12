# CareDelivery Doctrine Entity

ORM-mapped CareDelivery entities live here under `App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\*`:

- `Consultation`
- `ActeMedical`
- `Ordonnance`
- `OrdonnanceLigne`
- `Traitement`

Tables and column mappings are unchanged. Domain models remain pure under `App\CareDelivery\Domain\Model` (no Doctrine attributes).

Persistence for CareDelivery aggregates:

- Doctrine entities: this folder
- Repository adapter: `../Repository/DoctrineConsultationRepository.php`
- Legacy `App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
