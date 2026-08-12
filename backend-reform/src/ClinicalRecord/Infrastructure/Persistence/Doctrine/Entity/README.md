# ClinicalRecord Doctrine Entity

ORM-mapped ClinicalRecord entities live here under `App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\*`:

- `FicheMedicale`
- `FicheEntretien`
- `FicheEntretienMedicament`
- `FicheEntretienAffection`
- `FicheEntretienQuestion`
- `FicheEntretienHabitude`
- `FicheExamen`
- `FicheExamenItem`
- `FicheExamenLabo`
- `FicheBilan`
- `FichePlanTraitement`
- `FicheDocument`

Tables and column mappings are unchanged. Domain models remain pure under `App\ClinicalRecord\Domain\Model` (no Doctrine attributes).

Persistence for the ClinicalRecord aggregate:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/`
- Repository adapter: `../Repository/DoctrineFicheMedicaleRepository.php`
- Legacy `App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
