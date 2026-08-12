-- Replace static cabinet name in queued/sent SMS content.
-- Target replacement: ORODENT -> DENTAIRE OUSMANE SOW
--
-- Usage:
-- 1) Run the PREVIEW queries first.
-- 2) If results are correct, run the TRANSACTION block.

-- =========================
-- PREVIEW (read-only)
-- =========================
SELECT COUNT(*) AS queue_rows_to_update
FROM sms_queue
WHERE message LIKE '%ORODENT%';

SELECT COUNT(*) AS log_rows_to_update
FROM sms_log
WHERE message LIKE '%ORODENT%';

SELECT id, message
FROM sms_queue
WHERE message LIKE '%ORODENT%'
ORDER BY id DESC
LIMIT 20;

SELECT id, created_at, message
FROM sms_log
WHERE message LIKE '%ORODENT%'
ORDER BY id DESC
LIMIT 20;

-- =========================
-- UPDATE (transactional)
-- =========================
START TRANSACTION;

UPDATE sms_queue
SET message = REPLACE(message, 'ORODENT', 'DENTAIRE OUSMANE SOW')
WHERE message LIKE '%ORODENT%';

UPDATE sms_log
SET message = REPLACE(message, 'ORODENT', 'DENTAIRE OUSMANE SOW')
WHERE message LIKE '%ORODENT%';

-- Post-update verification before COMMIT
SELECT COUNT(*) AS queue_rows_remaining
FROM sms_queue
WHERE message LIKE '%ORODENT%';

SELECT COUNT(*) AS log_rows_remaining
FROM sms_log
WHERE message LIKE '%ORODENT%';

COMMIT;
