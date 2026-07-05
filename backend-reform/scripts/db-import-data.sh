#!/bin/sh
# Importe un dump DONNÉES SEULES dans MariaDB (après migrations).
#
# Usage :
#   ./scripts/db-import-data.sh <host> <user> <database> <fichier.sql>
#
# Exemple (MariaDB Dokploy via docker) :
#   docker exec -i <mariadb_container> sh -c \
#     'mariadb -u dentalsoft -p"$DB_PASS" dentalsoft_cdos' < /tmp/cdos-data-only.sql
#
# Ou depuis l'hôte si le port est exposé :
#   ./scripts/db-import-data.sh mariadb-cdos-xxxxx dentalsoft dentalsoft_cdos /tmp/cdos-data-only.sql

set -e

HOST="${1:?host requis}"
USER="${2:?user requis}"
DATABASE="${3:?database requise}"
INPUT="${4:?fichier .sql requis}"

if [ ! -f "$INPUT" ]; then
    echo "Fichier introuvable : $INPUT" >&2
    exit 1
fi

mariadb -h "$HOST" -u "$USER" -p "$DATABASE" < "$INPUT"

echo "Import terminé dans $DATABASE sur $HOST"
