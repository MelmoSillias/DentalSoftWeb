
#### API — Auth & Profil
- `api_register` — `POST` → `/api/register`
- `api_me` — `GET` → `/api/me`
- `api_me_update` — `PUT|POST` → `/api/me`
- `api_me_change_password` — `PATCH` → `/api/me/change-password`
- `api_token_validate` — `GET` → `/api/token/validate`
- `api_login_check` — `ANY` → `/api/login_check`

#### API — Devis & Paiements
- `api_devis_list` — `GET` → `/api/devis`
- `api_devis_unpaid` — `GET` → `/api/devis/unpaid`
- `api_devis_payments` — `GET` → `/api/devis/payments`
- `api_devis_preview` — `GET` → `/api/devis/{id}`
- `api_devis_pay` — `POST` → `/api/devis/{id}/pay`
- `api_devis_print` — `GET` → `/api/devis/{id}/print`
- `api_invoice_print` — `GET` → `/api/invoices/{id}/print`
- `api_payments_print` — `GET` → `/api/payments/print`
- `api_payment_print` — `GET` → `/api/payments/{id}/print`
- `api_receipt_print` — `GET` → `/api/receipts/{id}/print`
- `api_finances_chart_data` — `GET` → `/api/finances/chart-data`
- `api_transactions_list` — `GET` → `/api/transactions`
- `api_transaction_create` — `POST` → `/api/transaction`
- `api_transactions_intercompte` — `POST` → `/api/transactions/intercompte`

#### API — Congés & Jours fériés
- `api_conges_create` — `POST` → `/api/conges`
- `api_conges_list` — `GET` → `/api/conges`
- `api_conges_employees` — `GET` → `/api/conges/employees`
- `api_jours_list` — `GET` → `/api/holidays`
- `api_add_ferie` — `POST` → `/api/holidays`
- `api_delete_ferie` — `DELETE` → `/api/holidays/{date}`
- `api_update_fermetures` — `PUT` → `/api/holidays/closures`

#### API — Consommables & Stocks
- `api_consommable_add` — `POST` → `/api/consumables`
- `api_consommable_edit` — `PUT` → `/api/consumables/{id}`
- `api_consommable_retrait` — `POST` → `/api/consumables/{id}/withdraw`
- `api_consommable_details` — `GET` → `/api/consumables/{id}`
- `api_consommable_add_stock` — `POST` → `/api/consumables/{id}/stock`
- `api_consommable_delete` — `DELETE` → `/api/consumables/{id}`
- `api_consommables` — `GET` → `/api/consumables`
- `api_stocks` — `GET` → `/api/stocks`
- `api_report_nonperiodic_low_stock` — `GET` → `/api/report/nonperiodic/low-stock-consumables`

#### API — Consultations & Ordonnances
- `api_consultations_closed` — `GET` → `/api/consultations/closed`
- `api_consultations_day` — `GET` → `/api/consultations/day`
- `api_consultation_delete` — `DELETE` → `/api/consultations/{id}`
- `api_consultation_ordonnances` — `GET` → `/api/consultations/{consultation}/ordonnances`
- `api_consultation_ordonnance_add` — `POST` → `/api/consultations/{consultation}/ordonnances`
- `api_ordonnance_get` — `GET` → `/api/ordonnance/{id}`
- `api_ordonnance_print` — `GET` → `/api/ordonnance/{id}/print`
- `api_consultation_facture` — `GET` → `/api/consultations/{consultation}/facture`
- `api_consultation_facture_update` — `PUT` → `/api/consultations/{consultation}/facture/update`
- `api_consultation_details` — `GET` → `/api/admin/consultation/{id}/details`
- `api_fiche_consultation_json` — `GET` → `/api/fiches/{ficheId}/consultations/{consultationId}/json`
- `api_fiche_consultation_update_motif` — `POST` → `/api/fiches/{ficheId}/motif`
- `api_fiche_consultation_update_examens` — `POST` → `/api/fiches/{ficheId}/examens`
- `api_fiche_consultation_update_traitements` — `POST` → `/api/fiches/{ficheId}/traitements`
- `api_fiche_consultation_update_devis` — `POST` → `/api/fiches/{ficheId}/devis`
- `api_fiche_consultation_update` — `POST` → `/api/fiches/{ficheId}/consultations/{consultationId}`
- `api_fiche_consultation_cloture` — `POST` → `/api/fiches/{ficheId}/consultations/{consultationId}/cloture`
- `api_consultation_check_active` — `GET` → `/api/patient/{id}/consultation-en-cours`

#### API — Dashboard
- `api_report_global_stats` — `GET` → `/api/report/global-stats`
- `api_report_nonperiodic_employees_distribution` — `GET` → `/api/report/nonperiodic/employees-distribution`
- `api_report_api_medecin_dashboard` — `GET` → `/api/report/medecin`
- `api_report_global_stats_patients` — `GET` → `/api/report/global/patients`
- `api_report_periodic_patients` — `GET` → `/api/report/periodic/patients`
- `api_report_periodic_consultations` — `GET` → `/api/report/periodic/consultations`
- `api_report_periodic_appointments` — `GET` → `/api/report/periodic/appointments`
- `api_report_periodic_room_usage` — `GET` → `/api/report/periodic/room-usage`
- `api_report_periodic_payment_balances` — `GET` → `/api/report/periodic/payment-balances`
- `api_report_periodic_payment_frequency` — `GET` → `/api/report/periodic/payment-frequency`
- `api_report_periodic_acts_stats` — `GET` → `/api/report/periodic/acts-stats`
- `api_report_periodic_doctor_reports` — `GET` → `/api/report/periodic/doctor-reports`
- `api_report_api_report_receptionniste` — `GET` → `/api/report/reception-stats`

#### API — Événements
- `api_events_all` — `GET` → `/api/events`
- `api_event_create_booking` — `POST` → `/api/events`
- `api_event_delete` — `DELETE` → `/api/events/{id}`
- `api_event_validate` — `POST` → `/api/events/{id}/validate`

#### API — Patients & Médecins
- `api_medecins` — `GET` → `/api/medecins`
- `api_patients` — `GET` → `/api/patients`
- `api_patients_by_medecin` — `GET` → `/api/patients/medecin`
- `api_patient_add` — `POST` → `/api/patient/add`
- `api_patient_update` — `POST` → `/api/patient/{id}/update`
- `api_patients_search` — `GET` → `/api/patients/search`
- `api_patient_details` — `GET` → `/api/patient/{id}`
- `api_consultation_create` — `POST` → `/api/patient/{patientId}/consultation/create`
- `api_patient_rdv_create` — `POST` → `/api/patient/{id}/rdv/create`
- `api_patient_dossier_get` — `GET` → `/api/patient/{id}/dossier`
- `api_patient_dossier_update` — `PUT` → `/api/patient/{id}/dossier/update`
- `patient_print_infos_perso` — `GET` → `/api/patient/{id}/dossier/print/infosperso`
- `patient_fiche_print` — `GET` → `/api/patient/{patientId}/fiche/{ficheId}/print`

#### API — Modes de paiement
- `api_modes_paiement_list` — `GET` → `/api/payment-methods`
- `api_modes_paiement_create` — `POST` → `/api/payment-methods`
- `api_modes_paiement_delete` — `DELETE` → `/api/payment-methods/{id}`
- `api_modes_paiement_toggle` — `PATCH` → `/api/payment-methods/{id}/toggle`

#### API — Employés & RH
- `api_employees_list` — `GET` → `/api/employees`
- `api_employees_create` — `POST` → `/api/employees`
- `api_employees_update` — `PUT` → `/api/employees/{id}`
- `api_employee_details` — `GET` → `/api/employee/{id}`

#### API — RDV
- `api_rdv_create` — `POST` → `/api/rdv/create`
- `api_rdv_action` — `POST` → `/api/rdv/{id}/{action}`
- `api_rdv_stats` — `GET` → `/api/rdv/stats`
- `api_rdvs_stats_by_date` — `GET` → `/api/rdvs/stats/{date}`
- `api_rdvs_by_date` — `GET` → `/api/rdvs/{date}`
- `api_rdvs_range` — `GET` → `/api/rdvs`
- `api_pending_rdvs_range` — `GET` → `/api/rdvs_pending`
- `api_rdvs_bymedecin` — `GET` → `/api/rdvs/{date}/medecin`

#### API — Rapports, Salles, Users
- `api_reports_data` — `GET` → `/api/reports/data`
- `api_salles` — `GET` → `/api/salles`
- `api_users_create` — `POST` → `/api/users`
- `api_users_list` — `GET` → `/api/users`
- `api_users_update` — `PUT` → `/api/users/{id}`
- `api_users_reset_password` — `POST` → `/api/users/{id}/reset-password`
- `api_users_delete` — `DELETE` → `/api/users/{id}`


#### Admin (web)
- `app_admin_dashboard` — `ANY` → `/admin/dashboard`
- `app_admin_finances` — `ANY` → `/admin/finances`
- `app_admin_finances_add` — `POST` → `/admin/finances/transactions`
- `app_admin_finances_transactions` — `GET` → `/admin/finances/transactions`
- `app_admin_manual` — `ANY` → `/admin/manual`
- `app_admin_notifications_send` — `ANY` → `/admin/notifications/envoi`
- `app_admin_patient` — `ANY` → `/admin/patients`
- `app_admin_patient_dossier` — `ANY` → `/admin/patient/{id}/dossier`
- `app_admin_gestion_rh` — `ANY` → `/admin/gestion-rh`
- `employee_details` — `GET` → `/admin/employee/details/{id}`
- `app_admin_rapports` — `ANY` → `/admin/rapports`
- `app_admin_salles` — `ANY` → `/admin/salles`
- `app_admin_salle_add` — `POST` → `/admin/salles/add`
- `app_admin_salle_edit` — `POST` → `/admin/salles/edit`
- `app_admin_salle_delete` — `POST` → `/admin/salles/delete/{id}`
- `app_admin_utilisateurs` — `ANY` → `/admin/utilisateurs`

#### App (web)
- `app_home` — `ANY` → `/`
- `app_manual` — `ANY` → `/manual`
- `app_login` — `ANY` → `/login`
- `app_logout` — `ANY` → `/logout`
- `app_profile` — `GET` → `/profile`
- `app_profile_password` — `POST` → `/profile/password`
- `app_profile_notifications` — `GET|POST` → `/profil/notifications`
- `app_notifications_list` — `GET` → `/notifications`
- `app_notifications_stream` — `GET` → `/notifications/stream`
- `app_notifications_latest` — `GET` → `/notifications/latest`
- `app_notifications_mark_read` — `POST` → `/notifications/mark-read`
- `app_notifications_go` — `GET` → `/notifications/go/{id}`

#### Médecin (web)
- `app_medecin_agenda` — `ANY` → `/medecin/agenda`
- `app_medecin_consultations_pending` — `ANY` → `/medecin/consultation/en-attente`
- `app_medecin_consultation_en_attente_json` — `GET` → `/medecin/consultation/en-attente.json`
- `app_medecin_consultation_edit` — `GET` → `/medecin/consultation/{id}/editer`
- `app_medecin_consultation_edit_new` — `GET` → `/medecin/consultation/{id}/editer/new`
- `app_medecin_consultation_details` — `ANY` → `/medecin/consultation/{id}/details`
- `app_medecin_consultation_details_json` — `GET` → `/medecin/consultation/{id}/details.json`
- `app_medecin_consultations_closed` — `ANY` → `/medecin/consultations/closed`
- `app_medecin_consultations_liste` — `ANY` → `/medecin/consultation/liste`
- `app_medecin_dashboard` — `ANY` → `/medecin/dashboard`
- `app_medecin_manual` — `ANY` → `/medecin/manual`
- `app_medecin_patient` — `ANY` → `/medecin/patients`
- `app_medecin_patient_dossier` — `ANY` → `/medecin/patient/{id}/dossier`
- `app_medecin_test` — `ANY` → `/medecin/test`

#### Réception (web)
- `app_reception_agenda` — `ANY` → `/reception/agenda`
- `app_reception_caisse` — `ANY` → `/reception/caisse`
- `app_reception_consultations_pending` — `ANY` → `/reception/consultation/en-attente`
- `app_reception_consultation_en_attente_json` — `GET` → `/reception/consultation/en-attente.json`
- `app_reception_dashboard` — `ANY` → `/reception/dashboard`
- `app_reception_manual` — `ANY` → `/reception/manual`
- `app_reception_patient` — `ANY` → `/reception/patients`
