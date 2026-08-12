-- =============================================================================
-- FIN DE MIGRATION — corriger la colonne message (copier-coller tel quel)
-- =============================================================================
-- Prérequis : worker SMS arrêté si Lock wait timeout
-- @new_name : utiliser le même libellé que dans Paramètres > Identité & SMS
-- =============================================================================

SET @old_name = 'ORODENT';
SET @new_name = 'CABINET DENTAIRE OUSMANE SOW';

-- sms_queue : 46 lignes (ids 1 à 46)
UPDATE sms_queue
SET message = REPLACE(REPLACE(message, CONCAT('Cabinet ', @old_name), @new_name), @old_name, @new_name)
WHERE id BETWEEN 1 AND 46
  AND message LIKE CONCAT('%', @old_name, '%');

-- sms_log : 15 lignes (ids 3 à 17)
UPDATE sms_log
SET message = REPLACE(REPLACE(message, CONCAT('Cabinet ', @old_name), @new_name), @old_name, @new_name)
WHERE id BETWEEN 3 AND 17
  AND message LIKE CONCAT('%', @old_name, '%');

-- Paramètre application (rejouer si besoin)
UPDATE app_setting
SET value = JSON_SET(COALESCE(value, JSON_OBJECT()), '$.smsCabinetName', @new_name)
WHERE key_name = 'general';

-- Contrôle (doit afficher 0 / 0)
SELECT
    (SELECT COUNT(*) FROM sms_queue WHERE message LIKE CONCAT('%', @old_name, '%')) AS queue_remaining,
    (SELECT COUNT(*) FROM sms_log WHERE message LIKE CONCAT('%', @old_name, '%')) AS logs_remaining;

-- Aperçu pending (messages qui partent encore)
SELECT id, send_at, LEFT(message, 100) AS message
FROM sms_queue
WHERE status = 'pending'
ORDER BY send_at
LIMIT 10;
