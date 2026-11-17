-- =============================================================================
-- DIAGNOSTIC SEULEMENT — ne modifie pas les données
-- =============================================================================
-- Pour exécuter le remplacement, utiliser :
--   replace_orodent_in_sms_queue_and_logs_RUN.sql
-- =============================================================================
-- Remplace les occurrences de l'ancien nom (ORODENT) dans les messages SMS
-- en attente (file) et déjà envoyés (logs).
-- =============================================================================

SET @old_name = 'ORODENT';
SET @new_name = 'CENTRE DENTAIRE OUSMANE SOW'; -- <-- MODIFIER avec smsCabinetName des paramètres

SELECT 'sms_queue' AS source, id, status, send_at, LEFT(message, 160) AS message_preview
FROM sms_queue
WHERE message LIKE CONCAT('%', @old_name, '%')
ORDER BY id;

SELECT 'sms_log' AS source, id, status, created_at, LEFT(message, 160) AS message_preview
FROM sms_log
WHERE message LIKE CONCAT('%', @old_name, '%')
ORDER BY id DESC
LIMIT 200;

SELECT
    'sms_queue' AS source,
    COUNT(*) AS rows_to_update
FROM sms_queue
WHERE message LIKE CONCAT('%', @old_name, '%')
UNION ALL
SELECT
    'sms_log' AS source,
    COUNT(*) AS rows_to_update
FROM sms_log
WHERE message LIKE CONCAT('%', @old_name, '%');
