#!/bin/sh
# Exporte uniquement les DONNÉES (pas le schéma) pour import après doctrine:migrations:migrate.
#
# Usage :
#   ./scripts/db-export-data-only.sh <host> <user> <database> [output.sql]
#
# Exemple (ancienne prod Ubuntu) :
#   ./scripts/db-export-data-only.sh localhost root cdosdb /tmp/cdos-data-only.sql
#
# Le fichier produit est prêt à importer (post-traitement automatique via fix_dump.py).
#
# Options mysqldump clés :
#   --complete-insert   INSERT avec noms de colonnes → évite les décalages employe,
#                       sms_provider_config, user_device, transaction, etc.
#   --skip-lock-tables  pas de LOCK TABLES (compatible import transactionnel)
#   --column-statistics=0  compatibilité MySQL 8 → MariaDB
#   --ignore-table      exclut doctrine_migration_versions (schéma déjà migré sur cible)

set -e

HOST="${1:?host requis}"
USER="${2:?user requis}"
DATABASE="${3:?database requise}"
OUTPUT="${4:-./data-only-${DATABASE}-$(date +%Y%m%d).sql}"

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
FIX_DUMP="$ROOT_DIR/cdos_prod/fix_dump.py"

RAW=$(mktemp /tmp/dentalsoft-export.XXXXXX.sql) || RAW=$(mktemp dentalsoft-export.XXXXXX.sql)
cleanup() { rm -f "$RAW"; }
trap cleanup EXIT INT TERM

echo "[export] Dump données depuis $DATABASE ($HOST)..."

mysqldump \
  -h "$HOST" \
  -u "$USER" \
  -p \
  --no-create-info \
  --skip-triggers \
  --single-transaction \
  --skip-lock-tables \
  --complete-insert \
  --column-statistics=0 \
  --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 \
  --ignore-table="${DATABASE}.doctrine_migration_versions" \
  "$DATABASE" > "$RAW"

if [ ! -s "$RAW" ]; then
    echo "[export] Échec : dump vide" >&2
    exit 1
fi

if [ -f "$FIX_DUMP" ] && command -v python3 >/dev/null 2>&1; then
    echo "[export] Post-traitement (JSON, transaction atomique)..."
    python3 "$FIX_DUMP" "$RAW" "$OUTPUT"
else
    echo "[export] Attention : fix_dump.py ou python3 absent — dump brut sans post-traitement" >&2
    cp "$RAW" "$OUTPUT"
fi

echo "[export] Terminé : $OUTPUT"
echo "[export] Import : ./scripts/db-import-data.sh <host> <user> $DATABASE $OUTPUT"
