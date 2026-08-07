-- =============================================================================
-- MERGE FICHES DOUBLONS — version corrigee (OneToOne UNIQUE)
-- =============================================================================
-- Erreur precedente : tentative de deplacer plusieurs bilans/examens/entretiens
-- vers la meme fiche (contrainte UNIQUE fiche_medicale_id).
--
-- Ordre corrige : 1) fusionner  2) supprimer doublon  3) deplacer orphelin
-- Repete 6 fois (max 6 fiches/patient vu en prod).
--
-- Executer TOUT le fichier d'un coup, ou via :
--   mysql -u USER -p orodentdb < merge_duplicate_fiches_medicales_RUN.sql
--
-- Si erreur : ROLLBACK;
-- =============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

DROP TABLE IF EXISTS _merge_fiche_keep;
DROP TABLE IF EXISTS _merge_fiche_dup;

CREATE TABLE _merge_fiche_keep AS
SELECT fm.patient_id, MIN(fm.id) AS keep_id
FROM fiche_medicale fm
INNER JOIN (
    SELECT patient_id, MIN(created_at) AS min_created
    FROM fiche_medicale
    GROUP BY patient_id
    HAVING COUNT(*) >= 2
) d ON fm.patient_id = d.patient_id AND fm.created_at = d.min_created
GROUP BY fm.patient_id;

CREATE TABLE _merge_fiche_dup AS
SELECT fm.patient_id, k.keep_id, fm.id AS dup_id
FROM fiche_medicale fm
INNER JOIN _merge_fiche_keep k ON k.patient_id = fm.patient_id
WHERE fm.id <> k.keep_id;

SELECT COUNT(*) AS patients_a_merger FROM _merge_fiche_keep;
SELECT COUNT(*) AS fiches_doublons_a_supprimer FROM _merge_fiche_dup;

UPDATE fiche_medicale keep_fm
INNER JOIN _merge_fiche_dup m ON keep_fm.id = m.keep_id
INNER JOIN fiche_medicale dup_fm ON dup_fm.id = m.dup_id
SET keep_fm.medecin_id = COALESCE(keep_fm.medecin_id, dup_fm.medecin_id);

-- =========================================================================
-- ENTRETIEN (6 passes pour patients avec 3+ fiches)
-- =========================================================================

UPDATE fiche_entretien k
INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id
INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id
SET
    k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END,
    k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END,
    k.allaitement = COALESCE(k.allaitement, d.allaitement),
    k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours),
    k.menstrues = COALESCE(k.menstrues, d.menstrues);

UPDATE fiche_entretien_affection fa INNER JOIN fiche_entretien d ON fa.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fa.entretien_id = k.id;
UPDATE fiche_entretien_habitude fh INNER JOIN fiche_entretien d ON fh.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fh.entretien_id = k.id;
UPDATE fiche_entretien_medicament fm INNER JOIN fiche_entretien d ON fm.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fm.entretien_id = k.id;
UPDATE fiche_entretien_question fq INNER JOIN fiche_entretien d ON fq.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fq.entretien_id = k.id;

DELETE d FROM fiche_entretien d
INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id
INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;

UPDATE fiche_entretien e
INNER JOIN (
    SELECT m.keep_id, MIN(m.dup_id) AS dup_id
    FROM _merge_fiche_dup m
    WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id)
      AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id)
    GROUP BY m.keep_id
) x ON e.fiche_medicale_id = x.dup_id
SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_entretien k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id SET k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END, k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END, k.allaitement = COALESCE(k.allaitement, d.allaitement), k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours), k.menstrues = COALESCE(k.menstrues, d.menstrues);
UPDATE fiche_entretien_affection fa INNER JOIN fiche_entretien d ON fa.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fa.entretien_id = k.id;
UPDATE fiche_entretien_habitude fh INNER JOIN fiche_entretien d ON fh.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fh.entretien_id = k.id;
UPDATE fiche_entretien_medicament fm INNER JOIN fiche_entretien d ON fm.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fm.entretien_id = k.id;
UPDATE fiche_entretien_question fq INNER JOIN fiche_entretien d ON fq.entretien_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id SET fq.entretien_id = k.id;
DELETE d FROM fiche_entretien d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_entretien e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_entretien k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id SET k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END, k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END, k.allaitement = COALESCE(k.allaitement, d.allaitement), k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours), k.menstrues = COALESCE(k.menstrues, d.menstrues);
DELETE d FROM fiche_entretien d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_entretien e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_entretien k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id SET k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END, k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END, k.allaitement = COALESCE(k.allaitement, d.allaitement), k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours), k.menstrues = COALESCE(k.menstrues, d.menstrues);
DELETE d FROM fiche_entretien d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_entretien e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_entretien k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id SET k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END, k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END, k.allaitement = COALESCE(k.allaitement, d.allaitement), k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours), k.menstrues = COALESCE(k.menstrues, d.menstrues);
DELETE d FROM fiche_entretien d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_entretien e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_entretien k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_entretien d ON d.fiche_medicale_id = m.dup_id SET k.motif_consultation = CASE WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '') WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation) THEN k.motif_consultation ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation) END, k.anamnese = CASE WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '') WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese) THEN k.anamnese ELSE CONCAT(k.anamnese, '|', d.anamnese) END, k.allaitement = COALESCE(k.allaitement, d.allaitement), k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours), k.menstrues = COALESCE(k.menstrues, d.menstrues);
DELETE d FROM fiche_entretien d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_entretien k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_entretien e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_entretien k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_entretien d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

-- =========================================================================
-- EXAMEN (6 passes)
-- =========================================================================

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
UPDATE fiche_examen_item fi INNER JOIN fiche_examen d ON fi.examen_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id SET fi.examen_id = k.id;
UPDATE fiche_examen_labo fl INNER JOIN fiche_examen d ON fl.examen_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id SET fl.examen_id = k.id;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
UPDATE fiche_examen_item fi INNER JOIN fiche_examen d ON fi.examen_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id SET fi.examen_id = k.id;
UPDATE fiche_examen_labo fl INNER JOIN fiche_examen d ON fl.examen_id = d.id INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id SET fl.examen_id = k.id;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

UPDATE fiche_examen k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_examen d ON d.fiche_medicale_id = m.dup_id SET k.examen_canaux_excreteurs = CASE WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs) END, k.diagnostic_suppose_examens = CASE WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens) END, k.tissus_mous_table = CASE WHEN k.tissus_mous_table IS NULL OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_mous_table ELSE k.tissus_mous_table END, k.tissus_durs_table = CASE WHEN k.tissus_durs_table IS NULL OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}') THEN d.tissus_durs_table ELSE k.tissus_durs_table END;
DELETE d FROM fiche_examen d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_examen k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_examen e INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_examen k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_examen d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON e.fiche_medicale_id = x.dup_id SET e.fiche_medicale_id = x.keep_id;

-- =========================================================================
-- BILAN (6 passes) — NE PAS deplacer avant fusion/suppression
-- =========================================================================

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END, k.radiographie_extra_buccale_hypothese = CASE WHEN NULLIF(TRIM(k.radiographie_extra_buccale_hypothese), '') IS NULL THEN NULLIF(TRIM(d.radiographie_extra_buccale_hypothese), '') WHEN NULLIF(TRIM(d.radiographie_extra_buccale_hypothese), '') IS NULL OR TRIM(d.radiographie_extra_buccale_hypothese) = TRIM(k.radiographie_extra_buccale_hypothese) THEN k.radiographie_extra_buccale_hypothese ELSE CONCAT(k.radiographie_extra_buccale_hypothese, '|', d.radiographie_extra_buccale_hypothese) END, k.radiographie_intra_buccale_hypothese = CASE WHEN NULLIF(TRIM(k.radiographie_intra_buccale_hypothese), '') IS NULL THEN NULLIF(TRIM(d.radiographie_intra_buccale_hypothese), '') WHEN NULLIF(TRIM(d.radiographie_intra_buccale_hypothese), '') IS NULL OR TRIM(d.radiographie_intra_buccale_hypothese) = TRIM(k.radiographie_intra_buccale_hypothese) THEN k.radiographie_intra_buccale_hypothese ELSE CONCAT(k.radiographie_intra_buccale_hypothese, '|', d.radiographie_intra_buccale_hypothese) END, k.nfs_detaillee = CASE WHEN NULLIF(TRIM(k.nfs_detaillee), '') IS NULL THEN NULLIF(TRIM(d.nfs_detaillee), '') WHEN NULLIF(TRIM(d.nfs_detaillee), '') IS NULL OR TRIM(d.nfs_detaillee) = TRIM(k.nfs_detaillee) THEN k.nfs_detaillee ELSE CONCAT(k.nfs_detaillee, '|', d.nfs_detaillee) END, k.tp_tca_inr = CASE WHEN NULLIF(TRIM(k.tp_tca_inr), '') IS NULL THEN NULLIF(TRIM(d.tp_tca_inr), '') WHEN NULLIF(TRIM(d.tp_tca_inr), '') IS NULL OR TRIM(d.tp_tca_inr) = TRIM(k.tp_tca_inr) THEN k.tp_tca_inr ELSE CONCAT(k.tp_tca_inr, '|', d.tp_tca_inr) END, k.uree = CASE WHEN NULLIF(TRIM(k.uree), '') IS NULL THEN NULLIF(TRIM(d.uree), '') WHEN NULLIF(TRIM(d.uree), '') IS NULL OR TRIM(d.uree) = TRIM(k.uree) THEN k.uree ELSE CONCAT(k.uree, '|', d.uree) END, k.creatininemie = CASE WHEN NULLIF(TRIM(k.creatininemie), '') IS NULL THEN NULLIF(TRIM(d.creatininemie), '') WHEN NULLIF(TRIM(d.creatininemie), '') IS NULL OR TRIM(d.creatininemie) = TRIM(k.creatininemie) THEN k.creatininemie ELSE CONCAT(k.creatininemie, '|', d.creatininemie) END, k.glycemie = CASE WHEN NULLIF(TRIM(k.glycemie), '') IS NULL THEN NULLIF(TRIM(d.glycemie), '') WHEN NULLIF(TRIM(d.glycemie), '') IS NULL OR TRIM(d.glycemie) = TRIM(k.glycemie) THEN k.glycemie ELSE CONCAT(k.glycemie, '|', d.glycemie) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

UPDATE fiche_bilan k INNER JOIN _merge_fiche_dup m ON k.fiche_medicale_id = m.keep_id INNER JOIN fiche_bilan d ON d.fiche_medicale_id = m.dup_id SET k.formule_dentaire = CASE WHEN k.formule_dentaire IS NULL OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}') THEN d.formule_dentaire ELSE k.formule_dentaire END, k.diagnostic_positif = CASE WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '') WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif) END, k.avis_medicales = CASE WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '') WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales) END;
DELETE d FROM fiche_bilan d INNER JOIN _merge_fiche_dup m ON d.fiche_medicale_id = m.dup_id INNER JOIN fiche_bilan k ON k.fiche_medicale_id = m.keep_id;
UPDATE fiche_bilan b INNER JOIN (SELECT m.keep_id, MIN(m.dup_id) AS dup_id FROM _merge_fiche_dup m WHERE NOT EXISTS (SELECT 1 FROM fiche_bilan k WHERE k.fiche_medicale_id = m.keep_id) AND EXISTS (SELECT 1 FROM fiche_bilan d WHERE d.fiche_medicale_id = m.dup_id) GROUP BY m.keep_id) x ON b.fiche_medicale_id = x.dup_id SET b.fiche_medicale_id = x.keep_id;

-- =========================================================================
-- Relations directes + nettoyage final OneToOne orphelins sur doublons
-- =========================================================================

UPDATE consultation c INNER JOIN _merge_fiche_dup m ON c.fiche_medicale_id = m.dup_id SET c.fiche_medicale_id = m.keep_id;
UPDATE devis dv INNER JOIN _merge_fiche_dup m ON dv.fiche_medicale_id = m.dup_id SET dv.fiche_medicale_id = m.keep_id;
UPDATE fiche_document fd INNER JOIN _merge_fiche_dup m ON fd.fiche_medicale_id = m.dup_id SET fd.fiche_medicale_id = m.keep_id;
UPDATE fiche_plan_traitement fp INNER JOIN _merge_fiche_dup m ON fp.fiche_medicale_id = m.dup_id SET fp.fiche_medicale_id = m.keep_id;

DELETE fe FROM fiche_entretien_affection fe INNER JOIN fiche_entretien e ON fe.entretien_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE fe FROM fiche_entretien_habitude fe INNER JOIN fiche_entretien e ON fe.entretien_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE fe FROM fiche_entretien_medicament fe INNER JOIN fiche_entretien e ON fe.entretien_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE fe FROM fiche_entretien_question fe INNER JOIN fiche_entretien e ON fe.entretien_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE e FROM fiche_entretien e INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;

DELETE fi FROM fiche_examen_item fi INNER JOIN fiche_examen e ON fi.examen_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE fl FROM fiche_examen_labo fl INNER JOIN fiche_examen e ON fl.examen_id = e.id INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;
DELETE e FROM fiche_examen e INNER JOIN _merge_fiche_dup m ON e.fiche_medicale_id = m.dup_id;

DELETE b FROM fiche_bilan b INNER JOIN _merge_fiche_dup m ON b.fiche_medicale_id = m.dup_id;

SELECT COUNT(*) AS fk_bilan_restantes_sur_doublons
FROM fiche_bilan b INNER JOIN _merge_fiche_dup m ON b.fiche_medicale_id = m.dup_id;

DELETE fm FROM fiche_medicale fm INNER JOIN _merge_fiche_dup m ON fm.id = m.dup_id;

SELECT COUNT(*) AS patients_avec_doublons_apres
FROM (SELECT patient_id FROM fiche_medicale GROUP BY patient_id HAVING COUNT(*) >= 2) t;

COMMIT;

DROP TABLE IF EXISTS _merge_fiche_keep;
DROP TABLE IF EXISTS _merge_fiche_dup;

SELECT COUNT(*) AS patients_avec_doublons_final
FROM (SELECT patient_id FROM fiche_medicale GROUP BY patient_id HAVING COUNT(*) >= 2) t;
