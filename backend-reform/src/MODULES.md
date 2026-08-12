# Modular Monolith + Pure DDD

## Target layout (every module)

```text
src/<Module>/
  Domain/
    Model/ Repository/ Event/ Exception/ Service/ ValueObject/
  Application/
    Command/ Query/ Dto/ Port/ Mapper/ Bus/
  Infrastructure/
    Persistence/Doctrine/{Entity,Repository,Mapper}
    Adapter/
  Presentation/
    Controller/{Api,Admin,...} Request/ Response/
```

Legacy flat folders (`Entity/`, `Service/`, `Controller/`) remain until each use-case is cut over. Concrete Doctrine `ServiceEntityRepository` classes live under `Infrastructure/Persistence/Doctrine/Repository/` (Domain ports stay in `Domain/Repository/`; `Doctrine*Repository` implements them).

## Dependency rules

1. **Domain** depends on nothing (no Doctrine, no HttpFoundation, no concrete other modules).
2. **Application** depends on Domain + ports only (no `EntityManager`).
3. **Infrastructure** implements Domain/Application ports; Doctrine lives only here.
4. **Presentation** is a thin HTTP adapter: request → Command/Query bus → JSON.
5. Cross-module: IDs + Application ports + domain events. No Domain ORM associations.

## Shared kernel

- `Shared/Application/Bus` — `CommandBus` / `QueryBus`
- `Shared/Infrastructure/Bus` — sync locator dispatch
- `Shared/Application/Port` — `Clock`, `TransactionManager`
- `Shared/Domain/ValueObject` — `Email`, `PhoneNumber`, …
- `Shared/Event/EntityActionEvent` — legacy notification bridge (kept during transition)

## Modules

| Module | Legacy flat | DDD scaffold | Runtime DDD |
|--------|-------------|--------------|-------------|
| Shared | Event | yes | Bus + VOs + ports |
| Patient | yes | yes | P1–P4 cutover (CRUD, reads, dossier, portal, RDV/consult, archive-file, patient consultations via bus); Doctrine entities live in `Infrastructure/Persistence/Doctrine/Entity`. Cross-module side-effects go through Application ports (`CloseActiveConsultationsPort`, `PatientCreatedSideEffectsPort`, `ScheduleAppointmentPort` → Scheduling `RdvService`); fat RDV create no longer lives in `PatientService`. |
| IdentityAccess | yes | yes | Auth/User + RH/Payroll/Medecin high-traffic (employees list/get/create/update, medecins/infirmiers, payroll list/create via bus) |
| Scheduling | yes | yes | Rdv cutover (create/action/stats/list via bus) |
| ClinicalRecord | yes | yes | FicheMedicale cutover (json + section updates via bus); Doctrine entities live in `Infrastructure/Persistence/Doctrine/Entity`; implements CareDelivery `ConsultationClinicalRecordPort` |
| CareDelivery | yes | yes | Consultation domain deepen (create/cloture strangler + actes/medecin + Ordonnance; ordonnance/facture/verify via bus); cross-module side-effects via outbound ports (`ConsultationBillingPort`, `ConsultationFocusPort`, `ConsultationNotificationPort`, `ConsultationSettingsPort`, `ConsultationStaffPort`, `ConsultationClinicalRecordPort`, `ConsultationPatientPort`); Doctrine entities live in `Infrastructure/Persistence/Doctrine/Entity` |
| Billing | yes | yes | strangler wave 4 (Devis + FactureAssurance + LotFactureAssurance status domain; Insured/Classic/Lot/Finance ports; cashdesk + lots + payment-methods/finances via bus); implements CareDelivery `ConsultationBillingPort` (`ConsultationBillingAdapter`); Doctrine entities live in `Infrastructure/Persistence/Doctrine/Entity` |
| Communication | yes | yes | strangler wave 2 (GetSmsStats + queue/send/template/notify writes via bus; Mercure/Messenger kept) |
| Inventory | yes | yes | strangler wave 2 (ListConsumables + Create/Update/Delete/stock via bus) |
| Settings | yes | yes | strangler wave 2 (GetGeneralSettings + UpdateGeneralSettings via bus; devices/test-mode still legacy); implements CareDelivery `ConsultationSettingsPort` |
| Reporting | controllers/services | yes | strangler started (ReportRequest + GetReport via QueryBus) |
| Focus | controllers/services | yes | strangler started (FocusSnapshot + GetDashboardStats via QueryBus); implements CareDelivery `ConsultationFocusPort` |

## Use-case migration checklist

1. Extract Domain model + VOs + invariants.
2. Add Doctrine entity under `Infrastructure/Persistence/Doctrine/Entity` (same tables).
3. Mapper Domain ↔ Doctrine.
4. Domain repository port + Doctrine implementation.
5. `*Command`/`*Query` + `*Handler` (implements `CommandHandler` / `QueryHandler`).
6. Presentation controller (same route name/path/JSON) or legacy controller → bus.
7. Unit tests for Domain + Handler before cutover.
8. Remove legacy Service method only when no callers remain.

## Naming

- Write: `CreateXCommand` + `CreateXHandler`
- Read: `GetXQuery` + `GetXHandler`
- Ports: `XRepository` (Domain), `DoctrineXRepository` (Infrastructure)

## Wave order

1. Shared
2. Patient (pilot)
3. IdentityAccess
4. Scheduling → ClinicalRecord → CareDelivery → Billing
5. Communication → Inventory → Settings
6. Reporting / Focus (read models)

## Config notes

- Doctrine maps legacy `src/<Module>/Entity` **or** `src/<Module>/Infrastructure/Persistence/Doctrine/Entity` (never both for the same tables). Patient, CareDelivery, ClinicalRecord, Billing, Scheduling, IdentityAccess, Communication, Inventory, and Settings use Infrastructure only (entities + ORM repositories under `Persistence/Doctrine/`).
- Services exclude Domain models and Doctrine entities (`*/Infrastructure/Persistence/Doctrine/Entity/`); handlers are tagged via `_instanceof`.
- Routes load legacy `Controller/` and DDD `Presentation/Controller/`.
