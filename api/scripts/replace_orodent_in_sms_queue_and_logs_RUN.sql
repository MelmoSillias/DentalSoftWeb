-- =============================================================================
-- REMPLACEMENT PRODUCTION — procédure maintenance (verrous worker SMS)
-- =============================================================================
-- Symptôme : ERROR 1205 Lock wait timeout sur sms_queue / sms_log
-- Cause    : le worker PHP (app:sms:process-queue) tourne en arrière-plan
--            dans le conteneur Docker (entrypoint ENABLE_EMBEDDED_WORKER=1).
--
-- OBLIGATOIRE AVANT LES UPDATE :
--   1. Arrêter le conteneur API backend OU désactiver le worker :
--        docker stop <conteneur-backend>
--      ou redémarrer avec ENABLE_EMBEDDED_WORKER=0
--   2. Attendre 5 secondes, puis se reconnecter à MariaDB
--   3. Exécuter ce script
--
-- Si vous ne pouvez pas arrêter Docker, identifier et tuer le blocage :
--   SHOW FULL PROCESSLIST;
--   -- repérer une ligne avec State "Updating" / "Sending data" sur sms_queue
--   KILL <Id>;   -- remplacer par l'Id de la connexion bloquante (PAS votre session)
-- =============================================================================

SET @old_name = 'ORODENT';
SET @new_name = 'DENTAIRE OUSMANE SOW'; -- <-- MODIFIER ICI

ROLLBACK;
SET SESSION innodb_lock_wait_timeout = 30;

-- Diagnostic
SELECT COUNT(*) AS queue_hits FROM sms_queue WHERE message LIKE CONCAT('%', @old_name, '%');
SELECT COUNT(*) AS log_hits FROM sms_log WHERE message LIKE CONCAT('%', @old_name, '%');
SELECT status, COUNT(*) AS nb FROM sms_queue GROUP BY status;

-- ---------------------------------------------------------------------------
-- sms_queue : mise à jour par ID (46 lignes chez vous — évite le scan global)
-- ---------------------------------------------------------------------------
-- Étape A : lister les IDs (lecture seule)
SELECT id, status, LEFT(message, 80) AS preview
FROM sms_queue
WHERE message LIKE CONCAT('%', @old_name, '%')
ORDER BY id;

-- Étape B : exécuter UNE requête par lot d'IDs (adapter la liste depuis l'étape A)
-- Exemple si ids = 1,2,3 :
/*
UPDATE sms_queue
SET message = REPLACE(REPLACE(message, CONCAT('Cabinet ', @old_name), @new_name), @old_name, @new_name)
WHERE id IN (/* coller les ids ici */)
  AND message LIKE CONCAT('%', @old_name, '%');
*/

-- Variante automatique (MariaDB 10.2+) — une ligne à la fois, relancer jusqu'à 0 :
UPDATE sms_queue
SET message = REPLACE(REPLACE(message, CONCAT('Cabinet ', @old_name), @new_name), @old_name, @new_name)
WHERE id = (
    SELECT id FROM (
        SELECT id FROM sms_queue
        WHERE message LIKE CONCAT('%', @old_name, '%')
        ORDER BY id
        LIMIT 1
    ) AS t
);

-- Relancer la requête ci-dessus ~46 fois, ou utiliser la boucle shell :
--   while mysql ... -e "UPDATE ... LIMIT 1"; do :; done

UPDATE sms_queue
SET metadata = JSON_SET(metadata, '$.variables.cabinet_name', @new_name)
WHERE metadata IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.variables.cabinet_name')) = @old_name;

-- ---------------------------------------------------------------------------
-- sms_log : 15 lignes — même principe par ID
-- ---------------------------------------------------------------------------
SELECT id, status, LEFT(message, 80) AS preview
FROM sms_log
WHERE message LIKE CONCAT('%', @old_name, '%')
ORDER BY id;

UPDATE sms_log
SET message = REPLACE(REPLACE(message, CONCAT('Cabinet ', @old_name), @new_name), @old_name, @new_name)
WHERE id = (
    SELECT id FROM (
        SELECT id FROM sms_log
        WHERE message LIKE CONCAT('%', @old_name, '%')
        ORDER BY id
        LIMIT 1
    ) AS t
);

-- Relancer ~15 fois

-- ---------------------------------------------------------------------------
-- templates + paramètres (déjà OK chez vous, rejouer si besoin)
-- ---------------------------------------------------------------------------
UPDATE sms_template
SET content = REPLACE(content, 'Cabinet {cabinet_name}', '{cabinet_name}')
WHERE content LIKE '%Cabinet {cabinet_name}%';

UPDATE sms_template
SET content = REPLACE(content, 'au cabinet {cabinet_name}', 'chez {cabinet_name}')
WHERE content LIKE '%au cabinet {cabinet_name}%';

UPDATE app_setting
SET value = JSON_SET(COALESCE(value, JSON_OBJECT()), '$.smsCabinetName', @new_name)
WHERE key_name = 'general';

-- Contrôle
SELECT
    (SELECT COUNT(*) FROM sms_queue WHERE message LIKE CONCAT('%', @old_name, '%')) AS queue_remaining,
    (SELECT COUNT(*) FROM sms_log WHERE message LIKE CONCAT('%', @old_name, '%')) AS logs_remaining;
