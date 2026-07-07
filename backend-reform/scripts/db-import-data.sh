#!/bin/sh
# Importe un dump DONNÉES SEULES dans MariaDB (après migrations).
# L'import est atomique : en cas d'erreur, la transaction est annulée (ROLLBACK).
#
# Usage :
#   ./scripts/db-import-data.sh <host> <user> <database> <fichier.sql>
#
# Exemple (MariaDB Dokploy via docker) :
#   docker exec -i <mariadb_container> sh -c \
#     'mariadb -h 127.0.0.1 -u cdosuser -p"$DB_PASS" cdosdb' < /tmp/cdos-import-ready.sql
#
# Ou depuis l'hôte si le port est exposé :
#   ./scripts/db-import-data.sh 127.0.0.1 cdosuser cdosdb /tmp/cdos-data-only.sql
#
# Le script prépare automatiquement le dump (JSON, transaction atomique).
# Un export avec db-export-data-only.sh (--complete-insert) évite les décalages de colonnes.

set -e

HOST="${1:?host requis}"
USER="${2:?user requis}"
DATABASE="${3:?database requise}"
INPUT="${4:?fichier .sql requis}"

if [ ! -f "$INPUT" ]; then
    echo "Fichier introuvable : $INPUT" >&2
    exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
FIX_DUMP="$ROOT_DIR/cdos_prod/fix_dump.py"

if [ ! -f "$FIX_DUMP" ]; then
    echo "Script de préparation introuvable : $FIX_DUMP" >&2
    exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
    echo "python3 requis pour préparer le dump" >&2
    exit 1
fi

STAGING=$(mktemp /tmp/dentalsoft-import.XXXXXX.sql) || STAGING=$(mktemp dentalsoft-import.XXXXXX.sql)
cleanup() { rm -f "$STAGING"; }
trap cleanup EXIT INT TERM

echo "[import] Préparation du dump (corrections + transaction atomique)..."
python3 "$FIX_DUMP" "$INPUT" "$STAGING"

echo "[import] Import atomique en cours sur $DATABASE ($HOST)..."
if ! mariadb -h "$HOST" -u "$USER" -p "$DATABASE" --binary-mode=1 < "$STAGING"; then
    echo "[import] ÉCHEC — aucune modification conservée (ROLLBACK automatique)" >&2
    exit 1
fi

echo "[import] Terminé avec succès dans $DATABASE sur $HOST"
