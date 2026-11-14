-- =============================================================================
-- DIAGNOSTIC SEULEMENT — ne merge pas les donnees
-- =============================================================================
-- Pour executer le merge, utiliser :
--   merge_duplicate_fiches_medicales_RUN.sql
-- =============================================================================

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

SELECT COUNT(*) AS patients_avec_doublons
FROM (
    SELECT patient_id
    FROM fiche_medicale
    GROUP BY patient_id
    HAVING COUNT(*) >= 2
) t;
