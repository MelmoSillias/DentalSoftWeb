-- Migration ciblée: FicheObservation -> FicheMedicale (5 fiches existantes)
-- Objectif:
-- 1) Conserver toutes les données legacy sans suppression
-- 2) Réécrire le multi-insert vers les nouvelles entités/tables
-- 3) Migrer uniquement 5 fiches pour validation progressive
--
-- Compatibilité: MySQL 8+

START TRANSACTION;

-- Sélection de 5 fiches legacy non encore reliées à fiche_medicale
DROP TEMPORARY TABLE IF EXISTS tmp_fo_selected;
CREATE TEMPORARY TABLE tmp_fo_selected AS
SELECT
    fo.id AS old_fiche_id,
    fo.patient_id,
    fo.created_at,
    fo.motif,
    fo.histoire_maladie,
    fo.soins_anterieurs,
    fo.exo_inspection,
    fo.exo_palpation,
    fo.endo_inspection,
    fo.endo_palpation,
    fo.occlusion,
    fo.examen_parodontal,
    fo.diagnostic,
    fo.traitement_urgence,
    fo.traitement_dentaire,
    fo.traitement_parodontal,
    fo.traitement_orthodontique,
    fo.autres,
    fo.tooths_check,
    ROW_NUMBER() OVER (ORDER BY fo.id) AS rn
FROM fiche_observation fo
WHERE NOT EXISTS (
    SELECT 1
    FROM consultation c
    WHERE c.fiche_id = fo.id
      AND c.fiche_medicale_id IS NOT NULL
)
AND NOT EXISTS (
    SELECT 1
    FROM devis d
    WHERE d.fiche_id = fo.id
      AND d.fiche_medicale_id IS NOT NULL
)
ORDER BY fo.id;

-- 1) Insert parent: fiche_medicale
INSERT INTO fiche_medicale (patient_id, medecin_id, created_at)
SELECT
    s.patient_id,
    (
        SELECT c.medecin_id
        FROM consultation c
        WHERE c.fiche_id = s.old_fiche_id
          AND c.medecin_id IS NOT NULL
        ORDER BY c.created_at ASC, c.id ASC
        LIMIT 1
    ) AS medecin_id,
    COALESCE(s.created_at, NOW()) AS created_at
FROM tmp_fo_selected s
ORDER BY s.rn;

SET @first_fm_id := LAST_INSERT_ID();

DROP TEMPORARY TABLE IF EXISTS tmp_fo_map;
CREATE TEMPORARY TABLE tmp_fo_map AS
SELECT
    s.old_fiche_id,
    (@first_fm_id + s.rn - 1) AS new_fiche_medicale_id
FROM tmp_fo_selected s
ORDER BY s.rn;

-- 2) Insert fiche_entretien
-- Mapping:
-- - motif -> motif_consultation
-- - histoire_maladie + soins_anterieurs -> anamnese
INSERT INTO fiche_entretien (
    fiche_medicale_id,
    motif_consultation,
    anamnese,
    allaitement,
    grossesse_en_cours,
    menstrues
)
SELECT
    m.new_fiche_medicale_id,
    NULLIF(TRIM(s.motif), ''),
    NULLIF(
        TRIM(
            CONCAT_WS(
                '\n\n',
                NULLIF(TRIM(s.histoire_maladie), ''),
                CASE
                    WHEN NULLIF(TRIM(s.soins_anterieurs), '') IS NOT NULL
                    THEN CONCAT('Soins antérieurs: ', TRIM(s.soins_anterieurs))
                    ELSE NULL
                END
            )
        ),
        ''
    ) AS anamnese,
    NULL,
    NULL,
    NULL
FROM tmp_fo_selected s
JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id;

-- 3) Insert fiche_examen
-- Mapping:
-- - occlusion -> occlusion
-- - exo/endo/parodontal -> examen_canaux_excreteurs (texte consolidé)
INSERT INTO fiche_examen (
    fiche_medicale_id,
    occlusion,
    mediane,
    classes_angle,
    vestibules,
    hbd,
    brossage,
    soccu,
    cinematique_mandibulaire,
    ouverture_buccale,
    temperature_buccale,
    amplitude_ouverture,
    bruits_articulaires,
    tissus_mous_table,
    tissus_durs_table,
    examen_canaux_excreteurs
)
SELECT
    m.new_fiche_medicale_id,
    NULLIF(TRIM(s.occlusion), ''),
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    JSON_ARRAY(),
    JSON_ARRAY(),
    NULLIF(
        TRIM(
            CONCAT_WS(
                '\n',
                CASE WHEN NULLIF(TRIM(s.exo_inspection), '') IS NOT NULL THEN CONCAT('Exo inspection: ', TRIM(s.exo_inspection)) END,
                CASE WHEN NULLIF(TRIM(s.exo_palpation), '') IS NOT NULL THEN CONCAT('Exo palpation: ', TRIM(s.exo_palpation)) END,
                CASE WHEN NULLIF(TRIM(s.endo_inspection), '') IS NOT NULL THEN CONCAT('Endo inspection: ', TRIM(s.endo_inspection)) END,
                CASE WHEN NULLIF(TRIM(s.endo_palpation), '') IS NOT NULL THEN CONCAT('Endo palpation: ', TRIM(s.endo_palpation)) END,
                CASE WHEN NULLIF(TRIM(s.examen_parodontal), '') IS NOT NULL THEN CONCAT('Examen parodontal: ', TRIM(s.examen_parodontal)) END
            )
        ),
        ''
    ) AS examen_canaux_excreteurs
FROM tmp_fo_selected s
JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id;

-- 4) Insert fiche_bilan
-- Mapping:
-- - tooths_check (JSON) -> formule_dentaire
-- - diagnostic -> diagnostic_positif
INSERT INTO fiche_bilan (
    fiche_medicale_id,
    formule_dentaire,
    radiographie_extra_buccale_hypothese,
    radiographie_intra_buccale_hypothese,
    nfs_detaillee,
    tp_tca_inr,
    uree,
    creatininemie,
    glycemie,
    diagnostic_positif
)
SELECT
    m.new_fiche_medicale_id,
    COALESCE(s.tooths_check, JSON_ARRAY()),
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULLIF(TRIM(s.diagnostic), '')
FROM tmp_fo_selected s
JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id;

-- 5) Insert fiche_plan_traitement (1 ligne par type de traitement non vide)
-- Mapping:
-- - traitement_urgence -> type='urgence'
-- - traitement_dentaire -> type='dentaire'
-- - traitement_parodontal -> type='parodontal'
-- - traitement_orthodontique -> type='orthodontique'
-- - autres -> type='autres'
INSERT INTO fiche_plan_traitement (
    fiche_medicale_id,
    plan_index,
    type,
    date_supposed,
    description
)
SELECT
    x.fiche_medicale_id,
    ROW_NUMBER() OVER (PARTITION BY x.fiche_medicale_id ORDER BY x.sort_order) AS plan_index,
    x.type,
    NULL,
    x.description
FROM (
    SELECT m.new_fiche_medicale_id AS fiche_medicale_id, 1 AS sort_order, 'urgence' AS type, NULLIF(TRIM(s.traitement_urgence), '') AS description
    FROM tmp_fo_selected s
    JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id

    UNION ALL

    SELECT m.new_fiche_medicale_id AS fiche_medicale_id, 2 AS sort_order, 'dentaire' AS type, NULLIF(TRIM(s.traitement_dentaire), '') AS description
    FROM tmp_fo_selected s
    JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id

    UNION ALL

    SELECT m.new_fiche_medicale_id AS fiche_medicale_id, 3 AS sort_order, 'parodontal' AS type, NULLIF(TRIM(s.traitement_parodontal), '') AS description
    FROM tmp_fo_selected s
    JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id

    UNION ALL

    SELECT m.new_fiche_medicale_id AS fiche_medicale_id, 4 AS sort_order, 'orthodontique' AS type, NULLIF(TRIM(s.traitement_orthodontique), '') AS description
    FROM tmp_fo_selected s
    JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id

    UNION ALL

    SELECT m.new_fiche_medicale_id AS fiche_medicale_id, 5 AS sort_order, 'autres' AS type, NULLIF(TRIM(s.autres), '') AS description
    FROM tmp_fo_selected s
    JOIN tmp_fo_map m ON m.old_fiche_id = s.old_fiche_id
) x
WHERE x.description IS NOT NULL;

-- 6) Migration des documents legacy vers fiche_document
INSERT INTO fiche_document (
    fiche_medicale_id,
    type,
    libelle,
    url
)
SELECT
    m.new_fiche_medicale_id,
    'legacy_document',
    COALESCE(NULLIF(TRIM(dm.libelle), ''), 'Document migré'),
    dm.fichier
FROM document_medical dm
JOIN tmp_fo_map m ON m.old_fiche_id = dm.fiche_id;

-- 7) Migration des examens legacy vers fiche_examen_labo
INSERT INTO fiche_examen_labo (
    examen_id,
    type,
    observation,
    resultat
)
SELECT
    fe.id AS examen_id,
    COALESCE(NULLIF(TRIM(ed.designation), ''), 'Examen legacy') AS type,
    CONCAT('Date examen legacy: ', DATE_FORMAT(ed.date, '%Y-%m-%d %H:%i:%s')),
    ed.resultat
FROM examen_dentaire ed
JOIN tmp_fo_map m ON m.old_fiche_id = ed.fiche_id
JOIN fiche_examen fe ON fe.fiche_medicale_id = m.new_fiche_medicale_id;

-- 8) Relier consultation/devis au nouveau modèle (sans toucher aux colonnes legacy)
UPDATE consultation c
JOIN tmp_fo_map m ON m.old_fiche_id = c.fiche_id
SET c.fiche_medicale_id = m.new_fiche_medicale_id
WHERE c.fiche_medicale_id IS NULL;

UPDATE devis d
JOIN tmp_fo_map m ON m.old_fiche_id = d.fiche_id
SET d.fiche_medicale_id = m.new_fiche_medicale_id
WHERE d.fiche_medicale_id IS NULL;

-- Vérification rapide des 5 jeux migrés
SELECT
    m.old_fiche_id,
    m.new_fiche_medicale_id,
    fm.patient_id,
    fm.created_at,
    fe.id AS fiche_examen_id,
    fb.id AS fiche_bilan_id,
    fen.id AS fiche_entretien_id
FROM tmp_fo_map m
JOIN fiche_medicale fm ON fm.id = m.new_fiche_medicale_id
LEFT JOIN fiche_examen fe ON fe.fiche_medicale_id = fm.id
LEFT JOIN fiche_bilan fb ON fb.fiche_medicale_id = fm.id
LEFT JOIN fiche_entretien fen ON fen.fiche_medicale_id = fm.id
ORDER BY m.old_fiche_id;

COMMIT;
