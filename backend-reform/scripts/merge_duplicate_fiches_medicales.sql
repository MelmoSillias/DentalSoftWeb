-- =============================================================================
-- Merge des fiches médicales en double (patients avec >= 2 fiches)
-- =============================================================================
-- Règles :
--   - Fiche conservée = première créée (MIN(created_at), puis MIN(id))
--   - Textes concaténés avec '|' (vides / doublons exacts ignorés)
--   - Booléens : COALESCE(keep, dup)
--   - JSON : conserver le keep s'il est non vide, sinon le dup
--   - Relations OneToMany réaffectées à la fiche conservée
--   - OneToOne (entretien / examen / bilan) fusionnés puis doublons supprimés
--   - Transaction + ROLLBACK automatique en cas d'erreur
--
-- IMPORTANT : faire un backup avant d'exécuter.
-- Usage :
--   1. Exécuter la section DIAGNOSTIC seule pour inspecter
--   2. Exécuter le reste (PROCÉDURE) pour merger
-- =============================================================================

-- ---------------------------------------------------------------------------
-- DIAGNOSTIC (lecture seule)
-- ---------------------------------------------------------------------------
SELECT
    fm.patient_id,
    p.nom,
    p.prenom,
    COUNT(*) AS nb_fiches,
    MIN(fm.created_at) AS premiere_creation,
    MAX(fm.created_at) AS derniere_creation,
    GROUP_CONCAT(fm.id ORDER BY fm.created_at ASC, fm.id ASC) AS fiche_ids
FROM fiche_medicale fm
INNER JOIN patient p ON p.id = fm.patient_id
GROUP BY fm.patient_id, p.nom, p.prenom
HAVING COUNT(*) >= 2
ORDER BY nb_fiches DESC, fm.patient_id;

DROP PROCEDURE IF EXISTS merge_duplicate_fiches_medicales;

DELIMITER //

CREATE PROCEDURE merge_duplicate_fiches_medicales()
BEGIN
    DECLARE v_dup_count INT DEFAULT 0;
    DECLARE v_remaining INT DEFAULT 0;
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_keep_id INT;
    DECLARE v_dup_id INT;
    DECLARE v_keep_entretien_id INT;
    DECLARE v_dup_entretien_id INT;
    DECLARE v_keep_examen_id INT;
    DECLARE v_dup_examen_id INT;
    DECLARE v_keep_bilan_id INT;
    DECLARE v_dup_bilan_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_keep;
        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_dup;
        RESIGNAL;
    END;

    START TRANSACTION;

    DROP TEMPORARY TABLE IF EXISTS tmp_fiche_keep;
    DROP TEMPORARY TABLE IF EXISTS tmp_fiche_dup;

    -- Fiche à conserver par patient : première créée
    CREATE TEMPORARY TABLE tmp_fiche_keep AS
    SELECT patient_id, id AS keep_id
    FROM (
        SELECT
            fm.patient_id,
            fm.id,
            ROW_NUMBER() OVER (
                PARTITION BY fm.patient_id
                ORDER BY fm.created_at ASC, fm.id ASC
            ) AS rn
        FROM fiche_medicale fm
        INNER JOIN (
            SELECT patient_id
            FROM fiche_medicale
            GROUP BY patient_id
            HAVING COUNT(*) >= 2
        ) d ON d.patient_id = fm.patient_id
    ) ranked
    WHERE rn = 1;

    -- Toutes les fiches doublons à merger puis supprimer
    CREATE TEMPORARY TABLE tmp_fiche_dup AS
    SELECT
        fm.patient_id,
        k.keep_id,
        fm.id AS dup_id
    FROM fiche_medicale fm
    INNER JOIN tmp_fiche_keep k ON k.patient_id = fm.patient_id
    WHERE fm.id <> k.keep_id;

    SELECT COUNT(*) INTO v_dup_count FROM tmp_fiche_dup;

    IF v_dup_count = 0 THEN
        COMMIT;
        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_keep;
        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_dup;
        SELECT 'Aucune fiche en double à merger.' AS message;
    ELSE
        -- Curseur sur chaque couple keep/dup
        BEGIN
            DECLARE cur_dup CURSOR FOR
                SELECT keep_id, dup_id
                FROM tmp_fiche_dup
                ORDER BY patient_id, dup_id;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

            OPEN cur_dup;

            read_loop: LOOP
                FETCH cur_dup INTO v_keep_id, v_dup_id;
                IF v_done = 1 THEN
                    LEAVE read_loop;
                END IF;

                -- medecin_id : garder keep, sinon dup
                UPDATE fiche_medicale keep_fm
                INNER JOIN fiche_medicale dup_fm ON dup_fm.id = v_dup_id
                SET keep_fm.medecin_id = COALESCE(keep_fm.medecin_id, dup_fm.medecin_id)
                WHERE keep_fm.id = v_keep_id;

                -- ========== ENTRETIEN (OneToOne) ==========
                -- Scalar subqueries (not SELECT INTO) to avoid NOT FOUND aborting the cursor loop
                SET v_keep_entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = v_keep_id LIMIT 1);
                SET v_dup_entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = v_dup_id LIMIT 1);

                IF v_dup_entretien_id IS NOT NULL AND v_keep_entretien_id IS NULL THEN
                    UPDATE fiche_entretien
                    SET fiche_medicale_id = v_keep_id
                    WHERE id = v_dup_entretien_id;
                    SET v_keep_entretien_id = v_dup_entretien_id;
                    SET v_dup_entretien_id = NULL;
                ELSEIF v_dup_entretien_id IS NOT NULL AND v_keep_entretien_id IS NOT NULL THEN
                    UPDATE fiche_entretien k
                    INNER JOIN fiche_entretien d ON d.id = v_dup_entretien_id
                    SET
                        k.motif_consultation = CASE
                            WHEN NULLIF(TRIM(k.motif_consultation), '') IS NULL THEN NULLIF(TRIM(d.motif_consultation), '')
                            WHEN NULLIF(TRIM(d.motif_consultation), '') IS NULL OR TRIM(d.motif_consultation) = TRIM(k.motif_consultation)
                                THEN k.motif_consultation
                            ELSE CONCAT(k.motif_consultation, '|', d.motif_consultation)
                        END,
                        k.anamnese = CASE
                            WHEN NULLIF(TRIM(k.anamnese), '') IS NULL THEN NULLIF(TRIM(d.anamnese), '')
                            WHEN NULLIF(TRIM(d.anamnese), '') IS NULL OR TRIM(d.anamnese) = TRIM(k.anamnese)
                                THEN k.anamnese
                            ELSE CONCAT(k.anamnese, '|', d.anamnese)
                        END,
                        k.allaitement = COALESCE(k.allaitement, d.allaitement),
                        k.grossesse_en_cours = COALESCE(k.grossesse_en_cours, d.grossesse_en_cours),
                        k.menstrues = COALESCE(k.menstrues, d.menstrues)
                    WHERE k.id = v_keep_entretien_id;

                    UPDATE fiche_entretien_affection SET entretien_id = v_keep_entretien_id WHERE entretien_id = v_dup_entretien_id;
                    UPDATE fiche_entretien_habitude SET entretien_id = v_keep_entretien_id WHERE entretien_id = v_dup_entretien_id;
                    UPDATE fiche_entretien_medicament SET entretien_id = v_keep_entretien_id WHERE entretien_id = v_dup_entretien_id;
                    UPDATE fiche_entretien_question SET entretien_id = v_keep_entretien_id WHERE entretien_id = v_dup_entretien_id;

                    DELETE FROM fiche_entretien WHERE id = v_dup_entretien_id;
                END IF;

                -- ========== EXAMEN (OneToOne) ==========
                SET v_keep_examen_id = (SELECT id FROM fiche_examen WHERE fiche_medicale_id = v_keep_id LIMIT 1);
                SET v_dup_examen_id = (SELECT id FROM fiche_examen WHERE fiche_medicale_id = v_dup_id LIMIT 1);

                IF v_dup_examen_id IS NOT NULL AND v_keep_examen_id IS NULL THEN
                    UPDATE fiche_examen
                    SET fiche_medicale_id = v_keep_id
                    WHERE id = v_dup_examen_id;
                    SET v_keep_examen_id = v_dup_examen_id;
                    SET v_dup_examen_id = NULL;
                ELSEIF v_dup_examen_id IS NOT NULL AND v_keep_examen_id IS NOT NULL THEN
                    UPDATE fiche_examen k
                    INNER JOIN fiche_examen d ON d.id = v_dup_examen_id
                    SET
                        k.occlusion = LEFT(CASE
                            WHEN NULLIF(TRIM(k.occlusion), '') IS NULL THEN NULLIF(TRIM(d.occlusion), '')
                            WHEN NULLIF(TRIM(d.occlusion), '') IS NULL OR TRIM(d.occlusion) = TRIM(k.occlusion) THEN k.occlusion
                            ELSE CONCAT(k.occlusion, '|', d.occlusion)
                        END, 255),
                        k.mediane = LEFT(CASE
                            WHEN NULLIF(TRIM(k.mediane), '') IS NULL THEN NULLIF(TRIM(d.mediane), '')
                            WHEN NULLIF(TRIM(d.mediane), '') IS NULL OR TRIM(d.mediane) = TRIM(k.mediane) THEN k.mediane
                            ELSE CONCAT(k.mediane, '|', d.mediane)
                        END, 255),
                        k.classes_angle = LEFT(CASE
                            WHEN NULLIF(TRIM(k.classes_angle), '') IS NULL THEN NULLIF(TRIM(d.classes_angle), '')
                            WHEN NULLIF(TRIM(d.classes_angle), '') IS NULL OR TRIM(d.classes_angle) = TRIM(k.classes_angle) THEN k.classes_angle
                            ELSE CONCAT(k.classes_angle, '|', d.classes_angle)
                        END, 255),
                        k.vestibules = LEFT(CASE
                            WHEN NULLIF(TRIM(k.vestibules), '') IS NULL THEN NULLIF(TRIM(d.vestibules), '')
                            WHEN NULLIF(TRIM(d.vestibules), '') IS NULL OR TRIM(d.vestibules) = TRIM(k.vestibules) THEN k.vestibules
                            ELSE CONCAT(k.vestibules, '|', d.vestibules)
                        END, 255),
                        k.hbd = LEFT(CASE
                            WHEN NULLIF(TRIM(k.hbd), '') IS NULL THEN NULLIF(TRIM(d.hbd), '')
                            WHEN NULLIF(TRIM(d.hbd), '') IS NULL OR TRIM(d.hbd) = TRIM(k.hbd) THEN k.hbd
                            ELSE CONCAT(k.hbd, '|', d.hbd)
                        END, 255),
                        k.brossage = LEFT(CASE
                            WHEN NULLIF(TRIM(k.brossage), '') IS NULL THEN NULLIF(TRIM(d.brossage), '')
                            WHEN NULLIF(TRIM(d.brossage), '') IS NULL OR TRIM(d.brossage) = TRIM(k.brossage) THEN k.brossage
                            ELSE CONCAT(k.brossage, '|', d.brossage)
                        END, 255),
                        k.soccu = LEFT(CASE
                            WHEN NULLIF(TRIM(k.soccu), '') IS NULL THEN NULLIF(TRIM(d.soccu), '')
                            WHEN NULLIF(TRIM(d.soccu), '') IS NULL OR TRIM(d.soccu) = TRIM(k.soccu) THEN k.soccu
                            ELSE CONCAT(k.soccu, '|', d.soccu)
                        END, 255),
                        k.cinematique_mandibulaire = LEFT(CASE
                            WHEN NULLIF(TRIM(k.cinematique_mandibulaire), '') IS NULL THEN NULLIF(TRIM(d.cinematique_mandibulaire), '')
                            WHEN NULLIF(TRIM(d.cinematique_mandibulaire), '') IS NULL OR TRIM(d.cinematique_mandibulaire) = TRIM(k.cinematique_mandibulaire) THEN k.cinematique_mandibulaire
                            ELSE CONCAT(k.cinematique_mandibulaire, '|', d.cinematique_mandibulaire)
                        END, 255),
                        k.ouverture_buccale = LEFT(CASE
                            WHEN NULLIF(TRIM(k.ouverture_buccale), '') IS NULL THEN NULLIF(TRIM(d.ouverture_buccale), '')
                            WHEN NULLIF(TRIM(d.ouverture_buccale), '') IS NULL OR TRIM(d.ouverture_buccale) = TRIM(k.ouverture_buccale) THEN k.ouverture_buccale
                            ELSE CONCAT(k.ouverture_buccale, '|', d.ouverture_buccale)
                        END, 255),
                        k.temperature_buccale = LEFT(CASE
                            WHEN NULLIF(TRIM(k.temperature_buccale), '') IS NULL THEN NULLIF(TRIM(d.temperature_buccale), '')
                            WHEN NULLIF(TRIM(d.temperature_buccale), '') IS NULL OR TRIM(d.temperature_buccale) = TRIM(k.temperature_buccale) THEN k.temperature_buccale
                            ELSE CONCAT(k.temperature_buccale, '|', d.temperature_buccale)
                        END, 255),
                        k.amplitude_ouverture = LEFT(CASE
                            WHEN NULLIF(TRIM(k.amplitude_ouverture), '') IS NULL THEN NULLIF(TRIM(d.amplitude_ouverture), '')
                            WHEN NULLIF(TRIM(d.amplitude_ouverture), '') IS NULL OR TRIM(d.amplitude_ouverture) = TRIM(k.amplitude_ouverture) THEN k.amplitude_ouverture
                            ELSE CONCAT(k.amplitude_ouverture, '|', d.amplitude_ouverture)
                        END, 255),
                        k.bruits_articulaires = LEFT(CASE
                            WHEN NULLIF(TRIM(k.bruits_articulaires), '') IS NULL THEN NULLIF(TRIM(d.bruits_articulaires), '')
                            WHEN NULLIF(TRIM(d.bruits_articulaires), '') IS NULL OR TRIM(d.bruits_articulaires) = TRIM(k.bruits_articulaires) THEN k.bruits_articulaires
                            ELSE CONCAT(k.bruits_articulaires, '|', d.bruits_articulaires)
                        END, 255),
                        k.examen_canaux_excreteurs = CASE
                            WHEN NULLIF(TRIM(k.examen_canaux_excreteurs), '') IS NULL THEN NULLIF(TRIM(d.examen_canaux_excreteurs), '')
                            WHEN NULLIF(TRIM(d.examen_canaux_excreteurs), '') IS NULL OR TRIM(d.examen_canaux_excreteurs) = TRIM(k.examen_canaux_excreteurs) THEN k.examen_canaux_excreteurs
                            ELSE CONCAT(k.examen_canaux_excreteurs, '|', d.examen_canaux_excreteurs)
                        END,
                        k.diagnostic_suppose_examens = CASE
                            WHEN NULLIF(TRIM(k.diagnostic_suppose_examens), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_suppose_examens), '')
                            WHEN NULLIF(TRIM(d.diagnostic_suppose_examens), '') IS NULL OR TRIM(d.diagnostic_suppose_examens) = TRIM(k.diagnostic_suppose_examens) THEN k.diagnostic_suppose_examens
                            ELSE CONCAT(k.diagnostic_suppose_examens, '|', d.diagnostic_suppose_examens)
                        END,
                        k.tissus_mous_table = CASE
                            WHEN k.tissus_mous_table IS NULL
                              OR CAST(k.tissus_mous_table AS CHAR) IN ('', 'null', '[]', '{}')
                            THEN d.tissus_mous_table
                            ELSE k.tissus_mous_table
                        END,
                        k.tissus_durs_table = CASE
                            WHEN k.tissus_durs_table IS NULL
                              OR CAST(k.tissus_durs_table AS CHAR) IN ('', 'null', '[]', '{}')
                            THEN d.tissus_durs_table
                            ELSE k.tissus_durs_table
                        END
                    WHERE k.id = v_keep_examen_id;

                    UPDATE fiche_examen_item SET examen_id = v_keep_examen_id WHERE examen_id = v_dup_examen_id;
                    UPDATE fiche_examen_labo SET examen_id = v_keep_examen_id WHERE examen_id = v_dup_examen_id;

                    DELETE FROM fiche_examen WHERE id = v_dup_examen_id;
                END IF;

                -- ========== BILAN (OneToOne) ==========
                SET v_keep_bilan_id = (SELECT id FROM fiche_bilan WHERE fiche_medicale_id = v_keep_id LIMIT 1);
                SET v_dup_bilan_id = (SELECT id FROM fiche_bilan WHERE fiche_medicale_id = v_dup_id LIMIT 1);

                IF v_dup_bilan_id IS NOT NULL AND v_keep_bilan_id IS NULL THEN
                    UPDATE fiche_bilan
                    SET fiche_medicale_id = v_keep_id
                    WHERE id = v_dup_bilan_id;
                    SET v_keep_bilan_id = v_dup_bilan_id;
                    SET v_dup_bilan_id = NULL;
                ELSEIF v_dup_bilan_id IS NOT NULL AND v_keep_bilan_id IS NOT NULL THEN
                    UPDATE fiche_bilan k
                    INNER JOIN fiche_bilan d ON d.id = v_dup_bilan_id
                    SET
                        k.formule_dentaire = CASE
                            WHEN k.formule_dentaire IS NULL
                              OR CAST(k.formule_dentaire AS CHAR) IN ('', 'null', '[]', '{}')
                            THEN d.formule_dentaire
                            ELSE k.formule_dentaire
                        END,
                        k.radiographie_extra_buccale_hypothese = CASE
                            WHEN NULLIF(TRIM(k.radiographie_extra_buccale_hypothese), '') IS NULL THEN NULLIF(TRIM(d.radiographie_extra_buccale_hypothese), '')
                            WHEN NULLIF(TRIM(d.radiographie_extra_buccale_hypothese), '') IS NULL OR TRIM(d.radiographie_extra_buccale_hypothese) = TRIM(k.radiographie_extra_buccale_hypothese) THEN k.radiographie_extra_buccale_hypothese
                            ELSE CONCAT(k.radiographie_extra_buccale_hypothese, '|', d.radiographie_extra_buccale_hypothese)
                        END,
                        k.radiographie_intra_buccale_hypothese = CASE
                            WHEN NULLIF(TRIM(k.radiographie_intra_buccale_hypothese), '') IS NULL THEN NULLIF(TRIM(d.radiographie_intra_buccale_hypothese), '')
                            WHEN NULLIF(TRIM(d.radiographie_intra_buccale_hypothese), '') IS NULL OR TRIM(d.radiographie_intra_buccale_hypothese) = TRIM(k.radiographie_intra_buccale_hypothese) THEN k.radiographie_intra_buccale_hypothese
                            ELSE CONCAT(k.radiographie_intra_buccale_hypothese, '|', d.radiographie_intra_buccale_hypothese)
                        END,
                        k.nfs_detaillee = CASE
                            WHEN NULLIF(TRIM(k.nfs_detaillee), '') IS NULL THEN NULLIF(TRIM(d.nfs_detaillee), '')
                            WHEN NULLIF(TRIM(d.nfs_detaillee), '') IS NULL OR TRIM(d.nfs_detaillee) = TRIM(k.nfs_detaillee) THEN k.nfs_detaillee
                            ELSE CONCAT(k.nfs_detaillee, '|', d.nfs_detaillee)
                        END,
                        k.tp_tca_inr = CASE
                            WHEN NULLIF(TRIM(k.tp_tca_inr), '') IS NULL THEN NULLIF(TRIM(d.tp_tca_inr), '')
                            WHEN NULLIF(TRIM(d.tp_tca_inr), '') IS NULL OR TRIM(d.tp_tca_inr) = TRIM(k.tp_tca_inr) THEN k.tp_tca_inr
                            ELSE CONCAT(k.tp_tca_inr, '|', d.tp_tca_inr)
                        END,
                        k.uree = CASE
                            WHEN NULLIF(TRIM(k.uree), '') IS NULL THEN NULLIF(TRIM(d.uree), '')
                            WHEN NULLIF(TRIM(d.uree), '') IS NULL OR TRIM(d.uree) = TRIM(k.uree) THEN k.uree
                            ELSE CONCAT(k.uree, '|', d.uree)
                        END,
                        k.creatininemie = CASE
                            WHEN NULLIF(TRIM(k.creatininemie), '') IS NULL THEN NULLIF(TRIM(d.creatininemie), '')
                            WHEN NULLIF(TRIM(d.creatininemie), '') IS NULL OR TRIM(d.creatininemie) = TRIM(k.creatininemie) THEN k.creatininemie
                            ELSE CONCAT(k.creatininemie, '|', d.creatininemie)
                        END,
                        k.glycemie = CASE
                            WHEN NULLIF(TRIM(k.glycemie), '') IS NULL THEN NULLIF(TRIM(d.glycemie), '')
                            WHEN NULLIF(TRIM(d.glycemie), '') IS NULL OR TRIM(d.glycemie) = TRIM(k.glycemie) THEN k.glycemie
                            ELSE CONCAT(k.glycemie, '|', d.glycemie)
                        END,
                        k.diagnostic_positif = CASE
                            WHEN NULLIF(TRIM(k.diagnostic_positif), '') IS NULL THEN NULLIF(TRIM(d.diagnostic_positif), '')
                            WHEN NULLIF(TRIM(d.diagnostic_positif), '') IS NULL OR TRIM(d.diagnostic_positif) = TRIM(k.diagnostic_positif) THEN k.diagnostic_positif
                            ELSE CONCAT(k.diagnostic_positif, '|', d.diagnostic_positif)
                        END,
                        k.avis_medicales = CASE
                            WHEN NULLIF(TRIM(k.avis_medicales), '') IS NULL THEN NULLIF(TRIM(d.avis_medicales), '')
                            WHEN NULLIF(TRIM(d.avis_medicales), '') IS NULL OR TRIM(d.avis_medicales) = TRIM(k.avis_medicales) THEN k.avis_medicales
                            ELSE CONCAT(k.avis_medicales, '|', d.avis_medicales)
                        END
                    WHERE k.id = v_keep_bilan_id;

                    DELETE FROM fiche_bilan WHERE id = v_dup_bilan_id;
                END IF;

                -- ========== Relations OneToMany / ManyToOne ==========
                UPDATE consultation SET fiche_medicale_id = v_keep_id WHERE fiche_medicale_id = v_dup_id;
                UPDATE devis SET fiche_medicale_id = v_keep_id WHERE fiche_medicale_id = v_dup_id;
                UPDATE fiche_document SET fiche_medicale_id = v_keep_id WHERE fiche_medicale_id = v_dup_id;
                UPDATE fiche_plan_traitement SET fiche_medicale_id = v_keep_id WHERE fiche_medicale_id = v_dup_id;

                -- Sécurité : plus aucune FK ne doit pointer vers le doublon
                IF EXISTS (SELECT 1 FROM consultation WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM devis WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM fiche_document WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM fiche_plan_traitement WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM fiche_entretien WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM fiche_examen WHERE fiche_medicale_id = v_dup_id)
                    OR EXISTS (SELECT 1 FROM fiche_bilan WHERE fiche_medicale_id = v_dup_id)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Merge interrompu: des relations pointent encore vers une fiche doublon.';
                END IF;

                DELETE FROM fiche_medicale WHERE id = v_dup_id;
            END LOOP;

            CLOSE cur_dup;
        END;

        -- Validation finale : plus aucun patient avec >= 2 fiches
        SELECT COUNT(*) INTO v_remaining
        FROM (
            SELECT patient_id
            FROM fiche_medicale
            GROUP BY patient_id
            HAVING COUNT(*) >= 2
        ) still_dup;

        IF v_remaining > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Merge interrompu: des patients ont encore plusieurs fiches apres fusion.';
        END IF;

        COMMIT;

        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_keep;
        DROP TEMPORARY TABLE IF EXISTS tmp_fiche_dup;

        SELECT CONCAT('Merge OK. Fiches doublons fusionnees: ', v_dup_count) AS message;
    END IF;
END //

DELIMITER ;

-- Exécution
CALL merge_duplicate_fiches_medicales();

-- Nettoyage
DROP PROCEDURE IF EXISTS merge_duplicate_fiches_medicales;

-- Vérification post-merge
SELECT
    fm.patient_id,
    COUNT(*) AS nb_fiches
FROM fiche_medicale fm
GROUP BY fm.patient_id
HAVING COUNT(*) >= 2;
