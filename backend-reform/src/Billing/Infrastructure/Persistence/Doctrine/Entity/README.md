# Billing Doctrine Entity

ORM-mapped Billing entities live here under `App\Billing\Infrastructure\Persistence\Doctrine\Entity\*`:

- `Assurance`
- `ChargeFixe`
- `ContenuDevis`
- `Devis`
- `Facture`
- `FactureAssurance`
- `LotFactureAssurance`
- `ModeDePaiement`
- `Paiement`
- `Transaction`

Tables and column mappings are unchanged. Domain models remain pure under `App\Billing\Domain\Model` (no Doctrine attributes).

Persistence for Billing aggregates:

- Doctrine entities: this folder
- Domain ↔ Doctrine mapping: `../Mapper/` (where introduced)
- Repository adapter: `../Repository/` (e.g. `DoctrineDevisRepository.php`)
- Legacy `App\Billing\Infrastructure\Persistence\Doctrine\Repository\*` classes remain the Doctrine `repositoryClass` targets for now

Cross-module associations may still reference these entities by FQCN until they are replaced by IDs / application ports.
