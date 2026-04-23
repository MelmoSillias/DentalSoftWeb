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
  - Entites: `Devis`, `ContenuDevis`, `PaiementDevis`, `Transaction`, `ModeDePaiement`, `ChargeFixe`
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

## Regles de migration

- Les entites migrees sortent de `App\\Entity` vers `App\\<Module>\\Entity`.
- Les repositories migres sortent de `App\\Repository` vers `App\\<Module>\\Repository`.
- Les services applicatifs peuvent rester provisoirement dans `App\\Service` tant que les imports sont mis a jour.
- Les relations Doctrine inter-modules restent autorisees pendant la transition.
- `config/packages/doctrine.yaml` mappe maintenant tout `src/` pour permettre la migration incrementale.
- `config/services.yaml` exclut deja `src/Patient/Entity` de l'autowiring service.

## Prochaine vague recommandee

1. `Scheduling`
2. `Billing`
3. `Communication`
4. Validation runtime Symfony/Doctrine quand les dependances de `backend-reform` seront installees
