# Backend Reform Modules

## Modules cibles

- `Patient`
  - Entites: `Patient`, `Antecedent`, `Allergy`, `ContactUrgence`
  - Etat: migre

- `ClinicalRecord`
  - Entites: `FicheMedicale`, `FicheEntretien`, `FicheEntretien*`, `FicheExamen`, `FicheExamen*`, `FicheBilan`, `FichePlanTraitement`, `FicheDocument`, `FicheObservation`, `ExamenDentaire`, `DocumentMedical`
  - Etat: migre

- `CareDelivery`
  - Entites: `Consultation`, `ActeMedical`, `Traitement`, `Ordonnance`, `OrdonnanceLigne`
  - Etat: migre

- `Scheduling`
  - Entites: `Rdv`, `Salle`, `Booking`, `Conge`
  - Etat: migre

- `Billing`
  - Entites: `Devis`, `ContenuDevis`, `Paiement`, `Transaction`, `ModeDePaiement`, `ChargeFixe`
  - Etat: migre

- `IdentityAccess`
  - Entites: `User`, `Employe`, `UserDevice`, `UserDeviceAccessLog`
  - Etat: migre

- `Communication`
  - Entites: `SmsQueue`, `SmsLog`, `SmsTemplate`, `SmsProviderConfig`, `Notification`
  - Etat: migre

- `Inventory`
  - Entites: `Consommable`, `Stock`
  - Etat: migre

- `Settings`
  - Entites: `AppSetting`
  - Etat: migre

- `Reporting`
  - Surface: controllers et services de rapports transverses
  - Etat: schema controllers demarre

- `Shared`
  - Surface: evenements et primitives transverses partagees entre modules
  - Etat: schema demarre

## Regles de migration

- Les entites migrees sortent de `App\\Entity` vers `App\\<Module>\\Entity`.
- Les repositories migres sortent de `App\\Repository` vers `App\\<Module>\\Repository`.
- Les services applicatifs ne doivent plus etre crees sous `App\\Service`.
- Les services vivent desormais sous `App\\<Module>\\Service`.
- Les evenements transverses partages entre plusieurs domaines peuvent vivre sous `App\\Shared\\Event`.
- Les relations Doctrine inter-modules restent autorisees pendant la transition.
- `config/packages/doctrine.yaml` mappe maintenant tout `src/` pour permettre la migration incrementale.
- `config/services.yaml` exclut deja `src/Patient/Entity` de l'autowiring service.
- Les controllers PHP ne doivent plus etre crees sous `src/Controller`.
- Les controllers vivent desormais sous `src/<Module>/Controller/<Interface>` avec des interfaces comme `Api`, `Admin`, `Reception`, `Medecin`, `Profile`, `Security` ou `ApiPortalPatient` selon le cas.
- `config/routes.yaml` charge exclusivement les controllers modulaires via `src/*/Controller`.

## Schema controllers demarre

- `Billing`: controllers `Api`, `Admin`, `Reception`
- `CareDelivery`: controllers `Api`, `Admin`, `Medecin`, `Reception`
- `ClinicalRecord`: controllers `Api`
- `Communication`: controllers `Api`, `Admin`, web
- `Config`: controllers `Api`, `Home`, `Admin`, `Medecin`, `Reception`, web
- `Focus`: controllers `Api`, `Admin`, `Medecin`, `Reception`
- `IdentityAccess`: controllers `Api`, `Admin`, `Profile`, `Security`
- `Inventory`: controllers `Api`, `Admin`
- `Patient`: controllers `Api`, `Admin`, `Medecin`, `Reception`, `ApiPortalPatient`
- `Reporting`: controllers `Api`, `Admin`, `Api/Report`
- `Scheduling`: controllers `Api`, `Admin`, `Medecin`, `Reception`
- `Settings`: controllers `Api`

## Schema services demarre

- `Billing`: services `CashdeskService`, `FinanceService`
- `CareDelivery`: services `ConsultationService`, `ConsultationNotificationService`
- `ClinicalRecord`: services `FicheMedicaleService`
- `Communication`: services `NotificationService`, `NotificationRealtimePublisher`, `NotificationRecipientResolver`, `MercureAuthorizationService`, `SmsService`, `SmsTemplateService`, `SmsConfigService`, `OrangeSmsClient`, `CryptoService`
- `Focus`: services `FocusRealtimePublisher`, `DashboardService`
- `IdentityAccess`: services `AuthService`, `EmployeeService`, `UserDeviceService`, `UserManagementService`
- `Inventory`: services `ConsommableService`
- `Patient`: services `PatientService`
- `Reporting`: services `ReportService`
- `Scheduling`: services `AgendaService`, `CongeService`, `RdvService`, `RdvNotificationService`, `SalleService`
- `Settings`: services `GlobalSettingsService`

## Schema support demarre

- `Communication`: forms, event subscribers de notification, commandes de notification, notifiers, security-adjacent Mercure, message et message handler SMS
- `IdentityAccess`: forms, event subscribers de session/device, commande admin, security
- `Scheduling`: form et event subscriber calendrier
- `Shared`: `EntityActionEvent`

## Surfaces transverses conservees

- `Config`: configuration applicative transverse et endpoints de referentiel
- `Dto`: contrats de transport/presentation partages, a rapprocher d'un module uniquement quand un usage exclusivement local est confirme
- `Enum`: enums transverses tant qu'ils restent consommes par plusieurs modules
- `Shared`: primitives et evenements cross-module

## Prochaine vague recommandee

1. `Scheduling`
2. `Billing`
3. `Communication`
4. Validation runtime Symfony/Doctrine quand les dependances de `backend-reform` seront installees

