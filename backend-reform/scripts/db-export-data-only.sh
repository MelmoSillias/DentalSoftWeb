#!/bin/sh
# Exporte uniquement les DONNÉES (pas le schéma) pour import après doctrine:migrations:migrate.
#
# Usage :
#   ./scripts/db-export-data-only.sh <host> <user> <database> [output.sql]
#
# Exemple (ancienne MariaDB sur Ubuntu) :
#   ./scripts/db-export-data-only.sh localhost root Dentalsoft /tmp/cdos-data-only.sql

set -e

HOST="${1:?host requis}"
USER="${2:?user requis}"
DATABASE="${3:?database requise}"
OUTPUT="${4:-./data-only-${DATABASE}-$(date +%Y%m%d).sql}"

mysqldump \
  -h "$HOST" \
  -u "$USER" \
  -p \
  --no-create-info \
  --skip-triggers \
  --single-transaction \
  --set-gtid-purged=OFF \
  "$DATABASE" > "$OUTPUT"

echo "Export terminé : $OUTPUT"
