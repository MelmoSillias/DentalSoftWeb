# Patient Doctrine Entity

ORM-mapped Patient entities live here under `App\Patient\Infrastructure\Persistence\Doctrine\Entity\*`:

- `Patient`
- `Allergy`
- `Antecedent`
- `ContactUrgence`
- `PatientAssuranceProfile`
- `Appreciation`

Tables and column mappings are unchanged. Domain models remain pure under `App\Patient\Domain\Model` (no Doctrine attributes).

Persistence for the Patient aggregate:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/PatientMapper.php`
- Repository adapter: `../Repository/DoctrinePatientRepository.php`
- Legacy `App\Patient\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
