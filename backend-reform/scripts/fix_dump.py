#!/usr/bin/env python3
"""
Prepare cdos-data-only SQL dump for safe import into backend-reform (MariaDB).

Problèmes évités à l'export (db-export-data-only.sh) :
  --complete-insert     → INSERT nommés (plus de décalage de colonnes)
  --skip-lock-tables    → compatible transaction
  --ignore-table ...doctrine_migration_versions

Post-traitement restant (données ou import) :
  - JSON invalides ('null' string → '[]')
  - colonnes explicites legacy (dumps sans --complete-insert)
  - transaction atomique START TRANSACTION / COMMIT
  - employe déplacé après user (ordre FK)
"""
from __future__ import annotations

import argparse
import re
import sys

EMPLOYE_COLUMNS = (
    "id, user_id, nom, prenom, telephone, fonction, date_embauche, "
    "coming_days_in_week, is_on_days_off, type_salaire, valeur_salaire, "
    "email, matricule, type_contrat, duree_contrat, administrative_files, "
    "type, frequence_paiement, type_prime, valeur_prime"
)

TRANSACTION_COLUMNS = (
    "id, mode_de_paiement_id, facture_id, consultation_id, paiement_id, montant, "
    "type, description, motif, date_transaction, validated, validation_status, "
    "validation_comment, validated_at, rejected_at, role_paiement, "
    "taux_prise_en_charge, lot_facture_assurance_id"
)

# Ordre des colonnes dans le dump prod (peut différer du schéma Doctrine)
TABLE_DUMP_COLUMNS: dict[str, str] = {
    "employe": EMPLOYE_COLUMNS,
    "transaction": TRANSACTION_COLUMNS,
    "sms_provider_config": (
        "id, provider, enabled, client_id, client_secret_encrypted, "
        "sender_address, sender_name, approved_sender_names, "
        "base_url, oauth_url, created_at, updated_at, patient_preference_bypass"
    ),
    "user_device": (
        "id, requested_by_id, device_identifier, device_name, device_type, "
        "user_agent, ip_address, status, requested_at, validated_at, "
        "rejection_reason, last_seen_at, validated_by_id, custom_name"
    ),
}

TABLE_BLOCK_RE = re.compile(
    r"--\n-- Dumping data for table `(?P<table>\w+)`\n--\n.*?"
    r"(?=--\n-- Dumping data for table `|\Z)",
    re.DOTALL,
)

LOCK_TABLES_RE = re.compile(r"^LOCK TABLES .+?;\n", re.MULTILINE)
UNLOCK_TABLES_RE = re.compile(r"^UNLOCK TABLES;\n", re.MULTILINE)
ALTER_KEYS_RE = re.compile(r"/\*!40000 ALTER TABLE .+? KEYS \*/;\n")


def fix_table_inserts(content: str) -> str:
    """Ajoute colonnes explicites pour dumps legacy (sans --complete-insert)."""
    for table, columns in TABLE_DUMP_COLUMNS.items():
        plain = f"INSERT INTO `{table}` VALUES "
        if plain not in content:
            continue
        explicit = f"INSERT INTO `{table}` ({columns}) VALUES "
        content = content.replace(plain, explicit, 1)
    return content


def fix_json_null_literals(content: str) -> str:
    """Corrige 'null' (string) invalide pour colonnes JSON MariaDB."""
    content = re.sub(
        r"(INSERT INTO `sms_provider_config`[^;]+),'null'\)",
        r"\1,'[]')",
        content,
    )
    return content


def remove_table_block(content: str, table: str) -> str:
    return TABLE_BLOCK_RE.sub(
        lambda m: "" if m.group("table") == table else m.group(0),
        content,
    )


def move_block_after(content: str, block_name: str, after_name: str) -> str:
    blocks = {m.group("table"): m.group(0) for m in TABLE_BLOCK_RE.finditer(content)}
    if block_name not in blocks or after_name not in blocks:
        return content

    order = [m.group("table") for m in TABLE_BLOCK_RE.finditer(content)]
    if order.index(block_name) > order.index(after_name):
        return content

    block = blocks[block_name]
    content = content.replace(block, "", 1)
    anchor = blocks[after_name]
    return content.replace(anchor, anchor + block, 1)


def list_dump_tables(content: str) -> list[str]:
    return re.findall(r"Dumping data for table `(\w+)`", content)


def strip_mysqldump_locks(content: str) -> str:
    content = LOCK_TABLES_RE.sub("", content)
    content = UNLOCK_TABLES_RE.sub("", content)
    content = ALTER_KEYS_RE.sub("", content)
    return content


def wrap_in_transaction(content: str) -> str:
    """Wrap data import in a single transaction (all-or-nothing)."""
    if "START TRANSACTION;" in content and "DELETE FROM `" in content:
        return content
    marker = "--\n-- Dumping data for table"
    idx = content.find(marker)
    if idx == -1:
        raise SystemExit("No dump data found for transaction wrapper")

    header = content[:idx]
    body = content[idx:]
    tables = list_dump_tables(body)

    if not tables:
        raise SystemExit("No tables found in dump body")

    # Reverse order: child tables first when FK checks are off (safer habit).
    delete_sql = "\n".join(f"DELETE FROM `{table}`;" for table in reversed(tables))

    preamble = (
        "-- Transaction atomique : en cas d'erreur, ROLLBACK automatique à la déconnexion\n"
        "START TRANSACTION;\n\n"
        "SET SESSION FOREIGN_KEY_CHECKS=0;\n"
        "SET SESSION UNIQUE_CHECKS=0;\n"
        "SET SESSION SQL_NOTES=0;\n\n"
        "-- Purge données existantes (y compris import partiel précédent)\n"
        f"{delete_sql}\n\n"
    )

    postamble = (
        "\nSET SESSION FOREIGN_KEY_CHECKS=1;\n"
        "SET SESSION UNIQUE_CHECKS=1;\n\n"
        "COMMIT;\n"
    )

    return header + preamble + body + postamble


def prepare(content: str, *, transactional: bool) -> str:
    content = fix_table_inserts(content)
    content = fix_json_null_literals(content)
    content = remove_table_block(content, "doctrine_migration_versions")
    content = move_block_after(content, "employe", "user")

    if transactional:
        content = strip_mysqldump_locks(content)
        content = wrap_in_transaction(content)

    return content


def main() -> None:
    parser = argparse.ArgumentParser(description="Prepare cdos data-only SQL dump for import")
    parser.add_argument("input", nargs="?", default="cdos-data-onlysql")
    parser.add_argument("output", nargs="?", default="cdos-data-only-fixed.sql")
    parser.add_argument(
        "--no-transaction",
        action="store_true",
        help="Disable atomic transaction wrapper (not recommended)",
    )
    args = parser.parse_args()

    with open(args.input, "r", encoding="utf-8", errors="replace") as f:
        raw = f.read()

    legacy = [t for t in TABLE_DUMP_COLUMNS if f"INSERT INTO `{t}` VALUES " in raw]
    content = prepare(raw, transactional=not args.no_transaction)

    with open(args.output, "w", encoding="utf-8", newline="\n") as f:
        f.write(content)

    print(f"Prepared dump: {args.output}")

    if legacy:
        print("- colonnes explicites legacy:", ", ".join(legacy))
    else:
        print("- colonnes: INSERT nommés (--complete-insert OK)")

    if "doctrine_migration_versions" in content and "INSERT INTO `doctrine_migration_versions`" in content:
        print("- doctrine_migration_versions: supprimé")
    else:
        print("- doctrine_migration_versions: absent (OK)")
    print("- employe: moved after user (if needed)")
    if not args.no_transaction:
        tables = list_dump_tables(content)
        print(f"- transaction atomique: DELETE + INSERT sur {len(tables)} tables")
        print("- LOCK TABLES supprimés (incompatibles avec transactions)")


if __name__ == "__main__":
    main()
