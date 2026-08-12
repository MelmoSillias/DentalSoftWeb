# Patient Presentation Controllers (strangler)

HTTP routes for Patient remain in the legacy location during cutover:

- `App\Patient\Controller\Api\PatientController`

That controller already dispatches through `CommandBus` / `QueryBus` for migrated use-cases (CRUD, list/search/stats, dossier allergy/antecedent, portal, RDV/consultation, dossier, print, archive-file add/remove, patient consultations list).

No Presentation controller is registered yet: keep routes in the legacy controller to avoid duplicate route names. When an action is fully cut over and verified, move it here one at a time.

## Target

New or fully cut-over endpoints should live here:

```text
src/Patient/Presentation/Controller/Api/
```

Same route names, paths, and JSON shapes. Presentation stays thin: Request → Command/Query → JsonResponse.

Do not register duplicate routes while the legacy controller still owns them. Move one action at a time when the Application side is ready and callers are verified.
