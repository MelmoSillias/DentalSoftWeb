# Migration des routes API

Les routes canoniques et historiques exécutent les mêmes contrôleurs et règles métier. Les anciennes URLs restent disponibles pendant la migration des deux clients. Les paramètres et corps JSON existants sont conservés. Les nouvelles ressources utilisent le pluriel, GET pour lire, POST pour créer ou demander une transition, PATCH pour modifier partiellement, PUT pour remplacer et DELETE pour supprimer.

Les commandes métier (clôture, validation, remboursement) restent explicites : remplacer un verbe par un nom sans adapter son comportement ne rendrait pas l’API plus sûre. Les listes spécialisées sont conservées lorsque leurs contrats diffèrent ; elles ne sont pas artificiellement fusionnées. Les uploads existants restent en multipart POST sur leurs routes dédiées ; PATCH patient accepte le JSON.

Le [contrat JSON complet](../../api/docs/route-migration.json) inclut aussi les routes conservées. La configuration exécutable des nouvelles URLs est [resources.yaml](../../api/config/routes/resources.yaml). Les réponses historiques restent compatibles ; cette phase ne prétend pas uniformiser tous les DTO.

| Ancienne méthode et URL | Méthode et URL canonique |
| --- | --- |
| GET `/api/factures-cabinet/{id}` | GET `/api/service-invoices/{id}` |
| POST `/api/factures-cabinet/{id}/pay` | POST `/api/service-invoices/{id}/payments` |
| GET `/api/factures` | GET `/api/invoices` |
| GET `/api/factures/classiques` | GET `/api/invoices/standard` |
| GET `/api/factures/assurances` | GET `/api/invoices/insurers` |
| GET `/api/factures/unpaid` | GET `/api/invoices/unpaid` |
| GET `/api/factures/unpaid-by-patient/{patientId}` | GET `/api/patients/{patientId}/invoices/unpaid` |
| GET `/api/factures/payments` | GET `/api/invoices/payments` |
| GET `/api/factures/{id}` | GET `/api/invoices/{id}` |
| POST `/api/factures/{id}/pay` | POST `/api/invoices/{id}/payments` |
| DELETE `/api/factures/{id}/payments/reset` | DELETE `/api/invoices/{id}/payments` |
| DELETE `/api/factures/assurance/{id}/payments/reset` | DELETE `/api/invoices/assurance/{id}/payments/reset` |
| GET `/api/prints/assurances/claims/{id}` | GET `/api/prints/insurers/claims/{id}` |
| GET `/api/assurances` | GET `/api/insurers` |
| POST `/api/assurances` | POST `/api/insurers` |
| PATCH `/api/assurances/{code}/toggle` | PATCH `/api/insurers/{code}/toggle` |
| PATCH `/api/assurances/{code}` | PATCH `/api/insurers/{code}` |
| GET `/api/assurances/claims` | GET `/api/insurance-claims` |
| POST `/api/assurances/claims/{id}/patient-pay` | POST `/api/insurance-claims/{id}/patient-pay` |
| GET `/api/assurances/dashboard` | GET `/api/insurers/dashboard` |
| GET `/api/assurances/{code}/lots` | GET `/api/insurers/{code}/batches` |
| POST `/api/assurances/{code}/lots` | POST `/api/insurers/{code}/batches` |
| GET `/api/assurances/lots/{id}` | GET `/api/insurance-batches/{id}` |
| PATCH `/api/assurances/lots/{id}` | PATCH `/api/insurance-batches/{id}` |
| POST `/api/assurances/lots/{id}/send` | POST `/api/insurance-batches/{id}/send` |
| POST `/api/assurances/lots/{id}/reopen` | POST `/api/insurance-batches/{id}/reopen` |
| POST `/api/assurances/lots/{id}/confirm` | POST `/api/insurance-batches/{id}/confirm` |
| POST `/api/assurances/lots/{id}/unconfirm` | POST `/api/insurance-batches/{id}/unconfirm` |
| POST `/api/assurances/lots/{id}/refund` | POST `/api/insurance-batches/{id}/refund` |
| POST `/api/assurances/lots/{id}/recover` | POST `/api/insurance-batches/{id}/recover` |
| PATCH `/api/assurances/lots/{id}/refunds/{transactionId}/cancel` | PATCH `/api/insurance-batches/{id}/refunds/{transactionId}/cancel` |
| PATCH `/api/assurances/lots/{id}/recover/cancel` | PATCH `/api/insurance-batches/{id}/recover/cancel` |
| POST `/api/assurances/lots/{id}/claims` | POST `/api/insurance-batches/{id}/claims` |
| POST `/api/assurances/claims/{factureId}/move-lot` | POST `/api/insurance-claims/{factureId}/move-lot` |
| DELETE `/api/assurances/lots/{id}/claims/{factureId}` | DELETE `/api/insurance-batches/{id}/claims/{factureId}` |
| POST `/api/transaction` | POST `/api/transactions` |
| POST `/api/transactions/intercompte` | POST `/api/transfers` |
| POST `/api/consultation/set_fiche/{ficheId}` | POST `/api/medical-records/{ficheId}/consultation-links` |
| ANY `/api/consultations/pending` | GET `/api/consultations/pending` |
| GET `/api/consultations/{consultation}/ordonnances` | GET `/api/consultations/{consultation}/prescriptions` |
| POST `/api/consultations/{consultation}/ordonnances` | POST `/api/consultations/{consultation}/prescriptions` |
| GET `/api/ordonnance/{id}` | GET `/api/prescriptions/{id}` |
| PUT `/api/ordonnance/{id}` | PUT `/api/prescriptions/{id}` |
| GET `/api/prints/ordonnances/{id}` | GET `/api/prints/prescriptions/{id}` |
| GET `/api/consultations/{consultation}/facture` | GET `/api/consultations/{consultation}/invoice` |
| PUT `/api/consultations/{consultation}/facture/update` | PUT `/api/consultations/{consultation}/invoice` |
| GET `/api/admin/consultation/{id}/details` | GET `/api/consultations/{id}/administrative-details` |
| GET `/api/consultations/{id}/details` | GET `/api/consultations/{id}` |
| GET `/api/fiches/{ficheId}/consultations/{consultationId}/json` | GET `/api/care-records/{ficheId}/consultations/{consultationId}` |
| POST `/api/fiches/{ficheId}/motif` | PATCH `/api/care-records/{ficheId}/reason` |
| POST `/api/fiches/{ficheId}/examens` | PATCH `/api/care-records/{ficheId}/examinations` |
| POST `/api/fiches/{ficheId}/traitements` | PATCH `/api/care-records/{ficheId}/treatments` |
| POST `/api/fiches/{ficheId}/devis` | PATCH `/api/care-records/{ficheId}/quotes` |
| POST `/api/fiches/{ficheId}/consultations/{consultationId}` | PATCH `/api/care-records/{ficheId}/consultations/{consultationId}` |
| POST `/api/fiches/{ficheId}/consultations/{consultationId}/cloture` | POST `/api/care-records/{ficheId}/consultations/{consultationId}/closure` |
| GET `/api/fiches-medicales/{ficheId}/json` | GET `/api/medical-records/{ficheId}` |
| POST `/api/fiches-medicales/{ficheId}/entretien` | PATCH `/api/medical-records/{ficheId}/interview` |
| POST `/api/fiches-medicales/{ficheId}/examens` | PATCH `/api/medical-records/{ficheId}/examinations` |
| POST `/api/fiches-medicales/{ficheId}/bilans` | PATCH `/api/medical-records/{ficheId}/assessments` |
| POST `/api/fiches-medicales/{ficheId}/plan-traitement` | PATCH `/api/medical-records/{ficheId}/treatment-plan` |
| POST `/api/fiches-medicales/{ficheId}/documents` | PATCH `/api/medical-records/{ficheId}/documents` |
| POST `/api/fiches-medicales/{ficheId}/devis` | PATCH `/api/medical-records/{ficheId}/quotes` |
| POST `/api/me/notifications/mark-read` | POST `/api/me/notification-read-receipts` |
| POST `/api/me/notifications/mark-all` | POST `/api/me/notification-read-receipts/all` |
| POST `/api/admin/notifications/send` | POST `/api/notifications` |
| GET `/api/sms/queue/{id}/details` | GET `/api/sms/queue/{id}` |
| POST `/api/sms/send/manual` | POST `/api/sms/messages` |
| GET `/api/medecins` | GET `/api/doctors` |
| GET `/api/infirmiers` | GET `/api/nurses` |
| GET `/api/employee/{id}` | GET `/api/employees/{id}` |
| PATCH `/api/me/change-password` | PATCH `/api/me/password` |
| GET `/api/token/validate` | GET `/api/auth/session` |
| GET `/api/device/status` | GET `/api/auth/device` |
| POST `/api/consumables/{id}/withdraw` | POST `/api/consumables/{id}/withdrawals` |
| POST `/api/consumables/{id}/stock` | POST `/api/consumables/{id}/stock-entries` |
| GET `/api/patients/overview-stats` | GET `/api/patients/statistics` |
| GET `/api/patients/medecin/overview-stats` | GET `/api/patients/doctor/statistics` |
| GET `/api/patients/medecin` | GET `/api/patients/doctor` |
| POST `/api/patient/add-with-consultation` | POST `/api/patient-intakes` |
| POST `/api/patient/add` | POST `/api/patients` |
| POST `/api/patient/{id}/update` | PATCH `/api/patients/{id}` |
| POST `/api/patient/{id}/archive-file` | POST `/api/patients/{id}/archive-files` |
| DELETE `/api/patient/{id}/archive-file` | DELETE `/api/patients/{id}/archive-files` |
| GET `/api/patient/{id}/consultation-en-cours` | GET `/api/patients/{id}/active-consultation` |
| DELETE `/api/patient/{id}` | DELETE `/api/patients/{id}` |
| PATCH `/api/patient/{id}/restore` | PATCH `/api/patients/{id}/restore` |
| GET `/api/patient/{id}` | GET `/api/patients/{id}` |
| POST `/api/patient/{patientId}/consultation/create` | POST `/api/patients/{patientId}/consultations` |
| POST `/api/patient/{id}/rdv/create` | POST `/api/patients/{id}/appointments` |
| GET `/api/patient/{id}/dossier` | GET `/api/patients/{id}/record` |
| GET `/api/patient/{id}/fiche/latest` | GET `/api/patients/{id}/medical-records/latest` |
| POST `/api/patient/{id}/fiches-medicales` | POST `/api/patients/{id}/medical-records` |
| GET `/api/patient/{id}/consultations` | GET `/api/patients/{id}/consultations` |
| GET `/api/patient/{id}/portal-user` | GET `/api/patients/{id}/portal-user` |
| POST `/api/patient/{id}/portal-user/create` | POST `/api/patients/{id}/portal-user` |
| POST `/api/patient/{id}/portal-user/reset-password` | POST `/api/patients/{id}/portal-user/reset-password` |
| PATCH `/api/patient/{id}/portal-user/active` | PATCH `/api/patients/{id}/portal-user/active` |
| PUT `/api/patient/{id}/dossier/update` | PUT `/api/patients/{id}/record` |
| POST `/api/patient/{id}/antecedents` | POST `/api/patients/{id}/medical-history` |
| DELETE `/api/patient/{id}/antecedents/{antecedentId}` | DELETE `/api/patients/{id}/medical-history/{antecedentId}` |
| POST `/api/patient/{id}/allergies` | POST `/api/patients/{id}/allergies` |
| DELETE `/api/patient/{id}/allergies/{allergyId}` | DELETE `/api/patients/{id}/allergies/{allergyId}` |
| GET `/api/prints/patient/{id}/dossier` | GET `/api/prints/patient/{id}/record` |
| GET `/api/appreciations/public` | GET `/api/public/reviews` |
| GET `/api/appreciations/public/export` | GET `/api/public/reviews/export` |
| POST `/api/appreciations/anonymous` | POST `/api/public/review-submissions` |
| POST `/api/portal-patient/me/appreciations` | POST `/api/patient-portal/me/reviews` |
| POST `/api/portal-patient/me/consultations/{consultationId}/appreciation` | POST `/api/patient-portal/me/consultations/{consultationId}/review` |
| GET `/api/portal-patient/me/appreciations` | GET `/api/patient-portal/me/reviews` |
| GET `/api/administration/appreciations` | GET `/api/review-moderation` |
| PATCH `/api/administration/appreciations/{id}/publish` | PATCH `/api/review-moderation/{id}/publish` |
| DELETE `/api/administration/appreciations/{id}` | DELETE `/api/review-moderation/{id}` |
| GET `/api/portal-patient/me/consultations` | GET `/api/patient-portal/me/consultations` |
| GET `/api/portal-patient/me/devis-factures` | GET `/api/patient-portal/me/billing-documents` |
| GET `/api/portal-patient/me/paiements` | GET `/api/patient-portal/me/payments` |
| GET `/api/portal-patient/me/paiements/{id}/recu` | GET `/api/patient-portal/me/payments/{id}/receipt` |
| GET `/api/portal-patient/me/rdvs` | GET `/api/patient-portal/me/appointments` |
| GET `/api/portal-patient/me/notifications` | GET `/api/patient-portal/me/notifications` |
| GET `/api/portal-patient/me/notifications/mercure` | GET `/api/patient-portal/me/notifications/mercure` |
| GET `/api/portal-patient/me/dashboard` | GET `/api/patient-portal/me/dashboard` |
| GET `/api/portal-patient/me/profil` | GET `/api/patient-portal/me/profile` |
| GET `/api/portal-patient/me/consultations/{id}` | GET `/api/patient-portal/me/consultations/{id}` |
| GET `/api/portal-patient/me/devis-factures/{id}` | GET `/api/patient-portal/me/billing-documents/{id}` |
| GET `/api/report/global-stats` | GET `/api/reports/global-stats` |
| GET `/api/report/nonperiodic/employees-distribution` | GET `/api/reports/nonperiodic/employees-distribution` |
| GET `/api/report/nonperiodic/low-stock-consumables` | GET `/api/reports/nonperiodic/low-stock-consumables` |
| GET `/api/report/medecin` | GET `/api/reports/doctor` |
| GET `/api/report/global/patients` | GET `/api/reports/global/patients` |
| GET `/api/report/global/patient-referrals` | GET `/api/reports/global/patient-referrals` |
| GET `/api/report/periodic/patients` | GET `/api/reports/periodic/patients` |
| GET `/api/report/periodic/consultations` | GET `/api/reports/periodic/consultations` |
| GET `/api/report/periodic/appointments` | GET `/api/reports/periodic/appointments` |
| GET `/api/report/periodic/room-usage` | GET `/api/reports/periodic/room-usage` |
| GET `/api/report/periodic/payment-balances` | GET `/api/reports/periodic/payment-balances` |
| GET `/api/report/periodic/payment-frequency` | GET `/api/reports/periodic/payment-frequency` |
| GET `/api/report/periodic/acts-stats` | GET `/api/reports/periodic/acts-stats` |
| GET `/api/report/periodic/doctor-reports` | GET `/api/reports/periodic/doctor-reports` |
| GET `/api/report/reception-stats` | GET `/api/reports/reception-stats` |
| POST `/api/conges` | POST `/api/employee-leaves` |
| GET `/api/conges` | GET `/api/employee-leaves` |
| GET `/api/conges/employees` | GET `/api/employee-leaves/employees` |
| PUT `/api/conges/{id}` | PUT `/api/employee-leaves/{id}` |
| DELETE `/api/conges/{id}` | DELETE `/api/employee-leaves/{id}` |
| POST `/api/rdv/create` | POST `/api/appointments` |
| POST `/api/rdv/{id}/{action}` | POST `/api/appointments/{id}/transitions/{action}` |
| GET `/api/rdv/stats` | GET `/api/appointments/statistics` |
| GET `/api/rdvs/stats/{date}` | GET `/api/appointments/statistics/dates/{date}` |
| GET `/api/rdvs/{date}` | GET `/api/appointments/dates/{date}` |
| GET `/api/rdvs` | GET `/api/appointments` |
| GET `/api/rdvs_pending` | GET `/api/appointments/pending` |
| GET `/api/rdvs/{date}/medecin` | GET `/api/me/appointments/dates/{date}` |
| GET `/api/salles` | GET `/api/rooms` |
| POST `/api/salles` | POST `/api/rooms` |
| PUT `/api/salles/{id}` | PUT `/api/rooms/{id}` |
| DELETE `/api/salles/{salle}` | DELETE `/api/rooms/{salle}` |
| PUT|PATCH `/api/settings/devices/{deviceId}/rename` | PATCH `/api/settings/devices/{deviceId}` |
| POST `/api/login_check` | POST `/api/auth/login` |
| POST `/api/login_patient_check` | POST `/api/patient-portal/auth/login` |
| GET `/api/assurances/claims/{id}` | GET `/api/insurance-claims/{id}` |

## Authentification

POST `/api/auth/login` et `/api/patient-portal/auth/login` acceptent `{ "email": "identifiant", "password": "..." }`, comme le contrat Medinote. Les logins historiques restent en `{ "username": "...", "password": "..." }`. Les nouveaux endpoints refresh/logout et leurs limites sont détaillés dans [l’authentification](04-authentification.md).

## Suppression des anciennes routes

Migrer les services HTTP cabinet puis patient, vérifier les appels d’impression et de temps réel, observer l’utilisation des URLs historiques et annoncer une date de retrait avant suppression. Les en-têtes `Deprecation` et `Link; rel="successor-version"` signalent les anciennes URLs ; aucune redirection ne rejoue les mutations. Les receipts d’idempotence ne sont pas partagés entre deux URLs : un client doit conserver la même URL lors d’une relance.
