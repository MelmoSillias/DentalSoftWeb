#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import logging
import os
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, unquote, urlparse

try:
    import mysql.connector as mysql_connector
except ImportError:
    mysql_connector = None

try:
    import pymysql
    from pymysql.cursors import DictCursor as PyMySqlDictCursor
except ImportError:
    pymysql = None
    PyMySqlDictCursor = None


DEFAULT_FORM_CODE = 'fiche-medicale-standard'
FORM_CONFIGURATION = {
    'kind': 'medical-record',
    'systemSections': ['infos', 'seances', 'consult'],
    'transitionMode': 'double-read-double-write',
}
FORM_DEFINITION = [
    {
        'code': 'evaluation-clinique',
        'title': 'Evaluation clinique',
        'sortOrder': 10,
        'configuration': {'layout': 'standard'},
        'sections': [
            {
                'code': 'entretien',
                'title': 'Entretien verbal',
                'type': 'component',
                'componentKey': 'entretien-verbal',
                'sortOrder': 10,
                'fields': [
                    ('entretien__motif_consultation', 'Motif de consultation', 'text', 10),
                    ('entretien__anamnese', 'Anamnese', 'textarea', 20),
                    ('entretien__etat_gynecologique', 'Etat gynecologique', 'json', 30),
                    ('entretien__medicaments', 'Medicaments', 'json', 40),
                    ('entretien__affections', 'Affections', 'json', 50),
                    ('entretien__questions', 'Questions', 'json', 60),
                    ('entretien__habitudes', 'Habitudes', 'json', 70),
                ],
            },
            {
                'code': 'examens',
                'title': 'Examens',
                'type': 'component',
                'componentKey': 'examens-fiche',
                'sortOrder': 20,
                'fields': [
                    ('examens__exobuccal_inspection', 'Exobuccal inspection', 'json', 10),
                    ('examens__exobuccal_palpation', 'Exobuccal palpation', 'json', 20),
                    ('examens__chaines_ganglionnaires', 'Chaines ganglionnaires', 'json', 30),
                    ('examens__endobuccal_bouche_fermee', 'Endobuccal bouche fermee', 'json', 40),
                    ('examens__endobuccal_bouche_ouverte', 'Endobuccal bouche ouverte', 'json', 50),
                    ('examens__tissus_mous_table', 'Tissus mous', 'json', 60),
                    ('examens__tissus_durs_table', 'Tissus durs', 'json', 70),
                    ('examens__examen_canaux_excreteurs', 'Canaux excreteurs', 'textarea', 80),
                    ('examens__examens_bacteriologiques', 'Examens bacteriologiques', 'json', 90),
                    ('examens__examens_serologiques', 'Examens serologiques', 'json', 100),
                    ('examens__examens_histologiques', 'Examens histologiques', 'json', 110),
                ],
            },
            {
                'code': 'bilans',
                'title': 'Bilans',
                'type': 'component',
                'componentKey': 'fiche-bilans',
                'sortOrder': 30,
                'fields': [
                    ('bilans__bilan_dentaire', 'Bilan dentaire', 'json', 10),
                    ('bilans__bilan_radiographique', 'Bilan radiographique', 'json', 20),
                    ('bilans__bilan_sanguin', 'Bilan sanguin', 'json', 30),
                    ('bilans__diagnostic_positif', 'Diagnostic positif', 'textarea', 40),
                ],
            },
        ],
    },
    {
        'code': 'prise-en-charge',
        'title': 'Prise en charge',
        'sortOrder': 20,
        'configuration': {'layout': 'standard'},
        'sections': [
            {
                'code': 'documents',
                'title': 'Images et docs',
                'type': 'component',
                'componentKey': 'fiche-documents',
                'sortOrder': 10,
                'fields': [
                    ('documents__items', 'Documents', 'json', 10),
                ],
            },
            {
                'code': 'plan-traitement',
                'title': 'Plan de traitement',
                'type': 'component',
                'componentKey': 'plan-traitement',
                'sortOrder': 20,
                'fields': [
                    ('plan_traitement__items', 'Plan de traitement', 'json', 10),
                ],
            },
            {
                'code': 'devis',
                'title': 'Devis',
                'type': 'component',
                'componentKey': 'devis',
                'sortOrder': 30,
                'fields': [
                    ('devis__items', 'Devis', 'json', 10),
                ],
            },
        ],
    },
]


@dataclass
class MigrationStats:
    fixed_seen: int = 0
    fixed_migrated: int = 0
    legacy_seen: int = 0
    legacy_migrated: int = 0
    created_fiches: int = 0
    values_written: int = 0
    anomalies: list[str] = field(default_factory=list)


class Database:
    def __init__(self, connection: Any, engine: str):
        self.connection = connection
        self.engine = engine

    @classmethod
    def connect(cls, database_url: str) -> 'Database':
        parsed = parse_database_url(database_url)

        if mysql_connector is not None:
            connection = mysql_connector.connect(
                host=parsed['host'],
                port=parsed['port'],
                user=parsed['user'],
                password=parsed['password'],
                database=parsed['database'],
                charset=parsed['charset'],
            )
            return cls(connection, 'mysql-connector')

        if pymysql is not None:
            connection = pymysql.connect(
                host=parsed['host'],
                port=parsed['port'],
                user=parsed['user'],
                password=parsed['password'],
                database=parsed['database'],
                charset=parsed['charset'],
                cursorclass=PyMySqlDictCursor,
                autocommit=False,
            )
            return cls(connection, 'pymysql')

        raise RuntimeError('Aucun driver MySQL Python disponible. Installez mysql-connector-python ou pymysql.')

    def fetchall(self, sql: str, params: tuple[Any, ...] = ()) -> list[dict[str, Any]]:
        with self._cursor() as cursor:
            cursor.execute(sql, params)
            return list(cursor.fetchall())

    def fetchone(self, sql: str, params: tuple[Any, ...] = ()) -> dict[str, Any] | None:
        with self._cursor() as cursor:
            cursor.execute(sql, params)
            row = cursor.fetchone()
            return row if row else None

    def execute(self, sql: str, params: tuple[Any, ...] = ()) -> int | None:
        with self._cursor() as cursor:
            cursor.execute(sql, params)
            return getattr(cursor, 'lastrowid', None)

    def commit(self) -> None:
        self.connection.commit()

    def rollback(self) -> None:
        self.connection.rollback()

    def close(self) -> None:
        self.connection.close()

    def _cursor(self):
        if self.engine == 'mysql-connector':
            return self.connection.cursor(dictionary=True)
        return self.connection.cursor()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='Migre les fiches medicales legacy vers les valeurs dynamiques versionnees.')
    parser.add_argument('--apply', action='store_true', help='Applique les changements. Par defaut, le script tourne en dry-run.')
    parser.add_argument('--limit', type=int, default=0, help='Limite le nombre de fiches legacy a traiter.')
    parser.add_argument('--fiche-id', type=int, default=0, help='Traite uniquement une fiche_medicale existante.')
    parser.add_argument('--observation-id', type=int, default=0, help='Traite uniquement une fiche_observation legacy.')
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    base_dir = Path(__file__).resolve().parent
    setup_logging(base_dir)

    try:
        database_url = resolve_database_url(base_dir)
        db = Database.connect(database_url)
    except Exception as exc:
        logging.exception('Initialisation impossible: %s', exc)
        return 1

    stats = MigrationStats()

    try:
        form_id, champ_ids = ensure_default_form(db, apply=args.apply)
        migrate_fixed_fiches(db, form_id, champ_ids, stats, args)
        migrate_legacy_observations(db, form_id, champ_ids, stats, args)

        if args.apply:
            db.commit()
        else:
            db.rollback()

        logging.info('Resume migration: %s', json.dumps({
            'fixed_seen': stats.fixed_seen,
            'fixed_migrated': stats.fixed_migrated,
            'legacy_seen': stats.legacy_seen,
            'legacy_migrated': stats.legacy_migrated,
            'created_fiches': stats.created_fiches,
            'values_written': stats.values_written,
            'anomalies': len(stats.anomalies),
            'mode': 'apply' if args.apply else 'dry-run',
        }, ensure_ascii=True))

        for anomaly in stats.anomalies:
            logging.warning('ANOMALY %s', anomaly)
    except Exception as exc:
        db.rollback()
        logging.exception('Migration interrompue: %s', exc)
        return 1
    finally:
        db.close()

    return 0


def setup_logging(base_dir: Path) -> None:
    log_dir = base_dir / 'var' / 'log'
    log_dir.mkdir(parents=True, exist_ok=True)
    log_file = log_dir / 'fiche_data_migration.log'
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s %(levelname)s %(message)s',
        handlers=[
            logging.FileHandler(log_file, encoding='utf-8'),
            logging.StreamHandler(sys.stdout),
        ],
    )


def resolve_database_url(base_dir: Path) -> str:
    env = dict(os.environ)
    for candidate in ['.env', '.env.local', '.env.dev', '.env.dev.local']:
        path = base_dir / candidate
        if not path.exists():
            continue
        env.update(parse_env_file(path))

    database_url = env.get('DATABASE_URL')
    if not database_url:
        raise RuntimeError('DATABASE_URL introuvable dans les variables ou les fichiers .env.')

    return database_url.strip().strip('"').strip("'")


def parse_env_file(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding='utf-8').splitlines():
        line = raw_line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, value = line.split('=', 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def parse_database_url(database_url: str) -> dict[str, Any]:
    parsed = urlparse(database_url)
    query = parse_qs(parsed.query)
    return {
        'user': unquote(parsed.username or ''),
        'password': unquote(parsed.password or ''),
        'host': parsed.hostname or '127.0.0.1',
        'port': parsed.port or 3306,
        'database': (parsed.path or '/').lstrip('/'),
        'charset': query.get('charset', ['utf8mb4'])[0],
    }


def ensure_default_form(db: Database, apply: bool) -> tuple[int | None, dict[str, int | None]]:
    existing = db.fetchone(
        'SELECT id FROM formulaire WHERE code = %s AND status = %s ORDER BY version DESC, id DESC LIMIT 1',
        (DEFAULT_FORM_CODE, 'published'),
    )
    if existing:
        return existing['id'], load_champ_ids(db, existing['id'])

    logging.info('Formulaire publie %s absent, creation %s.', DEFAULT_FORM_CODE, 'reelle' if apply else 'simulee')
    if not apply:
        return None, {field_code: None for field_code in iter_field_codes()}

    form_id = db.execute(
        'INSERT INTO formulaire (source_formulaire_id, code, label, version, status, description, configuration, created_at, updated_at, published_at) VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())',
        (None, DEFAULT_FORM_CODE, 'Fiche medicale standard', 1, 'published', 'Version standard migree depuis la fiche medicale existante.', json.dumps(FORM_CONFIGURATION, ensure_ascii=True)),
    )

    for tab in FORM_DEFINITION:
        onglet_id = db.execute(
            'INSERT INTO onglet (formulaire_id, code, title, sort_order, configuration) VALUES (%s, %s, %s, %s, %s)',
            (form_id, tab['code'], tab['title'], tab['sortOrder'], json.dumps(tab.get('configuration', {}), ensure_ascii=True)),
        )
        for section in tab['sections']:
            section_id = db.execute(
                'INSERT INTO section (onglet_id, code, title, type, component_key, sort_order, configuration, conditions) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
                (
                    onglet_id,
                    section['code'],
                    section['title'],
                    section['type'],
                    section.get('componentKey'),
                    section['sortOrder'],
                    json.dumps(section.get('configuration', {}), ensure_ascii=True),
                    json.dumps(section.get('conditions', {}), ensure_ascii=True),
                ),
            )
            for field_code, field_label, field_type, sort_order in section['fields']:
                db.execute(
                    'INSERT INTO champ (section_id, code, label, field_type, renderer_key, sort_order, is_required, is_repeated, default_value, options, validation_rules, conditions) VALUES (%s, %s, %s, %s, %s, %s, 0, 0, %s, %s, %s, %s)',
                    (
                        section_id,
                        field_code,
                        field_label,
                        field_type,
                        None,
                        sort_order,
                        None,
                        json.dumps({}, ensure_ascii=True),
                        json.dumps({}, ensure_ascii=True),
                        json.dumps({}, ensure_ascii=True),
                    ),
                )

    return form_id, load_champ_ids(db, form_id)


def load_champ_ids(db: Database, form_id: int) -> dict[str, int | None]:
    rows = db.fetchall(
        'SELECT c.id, c.code FROM champ c INNER JOIN section s ON s.id = c.section_id INNER JOIN onglet o ON o.id = s.onglet_id WHERE o.formulaire_id = %s',
        (form_id,),
    )
    return {row['code']: row['id'] for row in rows}


def iter_field_codes() -> list[str]:
    codes: list[str] = []
    for tab in FORM_DEFINITION:
        for section in tab['sections']:
            codes.extend(field_code for field_code, _, _, _ in section['fields'])
    return codes


def migrate_fixed_fiches(db: Database, form_id: int | None, champ_ids: dict[str, int | None], stats: MigrationStats, args: argparse.Namespace) -> None:
    sql = 'SELECT id, patient_id, medecin_id, created_at, formulaire_version_id FROM fiche_medicale'
    params: list[Any] = []
    conditions: list[str] = []
    if args.fiche_id:
        conditions.append('id = %s')
        params.append(args.fiche_id)
    if conditions:
        sql += ' WHERE ' + ' AND '.join(conditions)
    sql += ' ORDER BY id ASC'
    if args.limit:
        sql += ' LIMIT %s'
        params.append(args.limit)

    for fiche in db.fetchall(sql, tuple(params)):
        stats.fixed_seen += 1
        field_payloads = build_fixed_payloads(db, fiche['id'])
        if not field_payloads:
            stats.anomalies.append(f'fixed fiche {fiche["id"]}: aucune valeur exploitable')
            continue

        if form_id is not None:
            db.execute(
                'UPDATE fiche_medicale SET formulaire_version_id = COALESCE(formulaire_version_id, %s), migration_state = %s WHERE id = %s',
                (form_id, 'migrated_dynamic', fiche['id']),
            )

        stats.values_written += upsert_field_payloads(db, fiche['id'], champ_ids, field_payloads, stats)
        stats.fixed_migrated += 1


def migrate_legacy_observations(db: Database, form_id: int | None, champ_ids: dict[str, int | None], stats: MigrationStats, args: argparse.Namespace) -> None:
    sql = 'SELECT id, patient_id, motif, histoire_maladie, soins_anterieurs, exo_inspection, exo_palpation, endo_inspection, endo_palpation, occlusion, examen_parodontal, diagnostic, traitement_urgence, traitement_dentaire, traitement_parodontal, traitement_orthodontique, autres, created_at, tooths_check FROM fiche_observation'
    params: list[Any] = []
    conditions: list[str] = []
    if args.observation_id:
        conditions.append('id = %s')
        params.append(args.observation_id)
    if conditions:
        sql += ' WHERE ' + ' AND '.join(conditions)
    sql += ' ORDER BY id ASC'
    if args.limit:
        sql += ' LIMIT %s'
        params.append(args.limit)

    for observation in db.fetchall(sql, tuple(params)):
        stats.legacy_seen += 1
        target_fiche_id = resolve_target_fiche_id(db, observation['id'])
        if target_fiche_id is None:
            if not observation['patient_id']:
                stats.anomalies.append(f'legacy observation {observation["id"]}: patient absent, creation impossible')
                continue
            target_fiche_id = create_target_fiche(db, observation, form_id)
            stats.created_fiches += 1

        field_payloads = build_legacy_payloads(db, observation)
        if not field_payloads:
            stats.anomalies.append(f'legacy observation {observation["id"]}: aucune valeur exploitable')
            continue

        stats.values_written += upsert_field_payloads(db, target_fiche_id, champ_ids, field_payloads, stats)
        db.execute(
            'UPDATE fiche_medicale SET formulaire_version_id = COALESCE(formulaire_version_id, %s), migration_state = %s WHERE id = %s',
            (form_id, 'migrated_from_observation', target_fiche_id),
        )
        stats.legacy_migrated += 1


def resolve_target_fiche_id(db: Database, observation_id: int) -> int | None:
    consultation = db.fetchone(
        'SELECT fiche_medicale_id FROM consultation WHERE fiche_id = %s AND fiche_medicale_id IS NOT NULL ORDER BY id ASC LIMIT 1',
        (observation_id,),
    )
    if consultation and consultation['fiche_medicale_id']:
        return consultation['fiche_medicale_id']

    devis = db.fetchone(
        'SELECT fiche_medicale_id FROM devis WHERE fiche_id = %s AND fiche_medicale_id IS NOT NULL ORDER BY id ASC LIMIT 1',
        (observation_id,),
    )
    if devis and devis['fiche_medicale_id']:
        return devis['fiche_medicale_id']

    return None


def create_target_fiche(db: Database, observation: dict[str, Any], form_id: int | None) -> int:
    medecin = db.fetchone(
        'SELECT medecin_id FROM consultation WHERE fiche_id = %s AND medecin_id IS NOT NULL ORDER BY id ASC LIMIT 1',
        (observation['id'],),
    )
    return db.execute(
        'INSERT INTO fiche_medicale (patient_id, medecin_id, created_at, formulaire_version_id, migration_state) VALUES (%s, %s, %s, %s, %s)',
        (observation['patient_id'], medecin['medecin_id'] if medecin else None, observation['created_at'], form_id, 'migrated_from_observation'),
    )


def build_fixed_payloads(db: Database, fiche_id: int) -> dict[str, Any]:
    payloads: dict[str, Any] = {}

    entretien = db.fetchone('SELECT motif_consultation, anamnese, allaitement, grossesse_en_cours, menstrues FROM fiche_entretien WHERE fiche_medicale_id = %s', (fiche_id,))
    if entretien:
        payloads['entretien__motif_consultation'] = entretien.get('motif_consultation')
        payloads['entretien__anamnese'] = entretien.get('anamnese')
        payloads['entretien__etat_gynecologique'] = {
            'allaitement': entretien.get('allaitement'),
            'grossesseEnCours': entretien.get('grossesse_en_cours'),
            'menstrues': entretien.get('menstrues'),
        }
        payloads['entretien__medicaments'] = db.fetchall('SELECT id, nom, est_utilise, details FROM fiche_entretien_medicament WHERE entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = %s)', (fiche_id,))
        payloads['entretien__affections'] = db.fetchall('SELECT id, nom, est_presente, details FROM fiche_entretien_affection WHERE entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = %s)', (fiche_id,))
        payloads['entretien__questions'] = db.fetchall('SELECT id, question, reponse, precision FROM fiche_entretien_question WHERE entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = %s)', (fiche_id,))
        payloads['entretien__habitudes'] = db.fetchall('SELECT id, type, est_presente, quantite FROM fiche_entretien_habitude WHERE entretien_id = (SELECT id FROM fiche_entretien WHERE fiche_medicale_id = %s)', (fiche_id,))

    examen = db.fetchone('SELECT occlusion, mediane, classes_angle, vestibules, hbd, brossage, soccu, cinematique_mandibulaire, ouverture_buccale, temperature_buccale, amplitude_ouverture, bruits_articulaires, tissus_mous_table, tissus_durs_table, examen_canaux_excreteurs FROM fiche_examen WHERE fiche_medicale_id = %s', (fiche_id,))
    if examen:
        payloads['examens__exobuccal_inspection'] = {}
        payloads['examens__exobuccal_palpation'] = {}
        payloads['examens__chaines_ganglionnaires'] = {}
        for item in db.fetchall('SELECT categorie, libelle, est_present, details FROM fiche_examen_item WHERE examen_id = (SELECT id FROM fiche_examen WHERE fiche_medicale_id = %s)', (fiche_id,)):
            if item['categorie'] == 'exobuccal_inspection':
                payloads['examens__exobuccal_inspection'][item['libelle']] = item['details']
            elif item['categorie'] == 'exobuccal_palpation':
                payloads['examens__exobuccal_palpation'][item['libelle']] = item['details']
            elif item['categorie'] == 'chaines_ganglionnaires':
                payloads['examens__chaines_ganglionnaires'][item['libelle']] = item['est_present']

        payloads['examens__endobuccal_bouche_fermee'] = {
            'occlusion': examen.get('occlusion'),
            'mediane': examen.get('mediane'),
            'classesAngle': examen.get('classes_angle'),
            'vestibules': examen.get('vestibules'),
        }
        payloads['examens__endobuccal_bouche_ouverte'] = {
            'hbd': examen.get('hbd'),
            'brossage': examen.get('brossage'),
            'soccu': examen.get('soccu'),
            'cinematiqueMandibulaire': examen.get('cinematique_mandibulaire'),
            'ouvertureBuccale': examen.get('ouverture_buccale'),
            'temperatureBuccale': examen.get('temperature_buccale'),
            'amplitudeOuverture': examen.get('amplitude_ouverture'),
            'bruitsArticulaires': examen.get('bruits_articulaires'),
        }
        payloads['examens__tissus_mous_table'] = safe_json(examen.get('tissus_mous_table')) or {}
        payloads['examens__tissus_durs_table'] = safe_json(examen.get('tissus_durs_table')) or {}
        payloads['examens__examen_canaux_excreteurs'] = examen.get('examen_canaux_excreteurs')

        labo_map = {
            'Examens bacteriologiques': 'examens__examens_bacteriologiques',
            'Examens serologiques': 'examens__examens_serologiques',
            'Examens histologiques': 'examens__examens_histologiques',
        }
        for labo in db.fetchall('SELECT type, observation, resultat FROM fiche_examen_labo WHERE examen_id = (SELECT id FROM fiche_examen WHERE fiche_medicale_id = %s)', (fiche_id,)):
            code = labo_map.get(labo['type'])
            if code:
                payloads[code] = {'observation': labo['observation'], 'resultat': labo['resultat']}

    bilan = db.fetchone('SELECT formule_dentaire, radiographie_extra_buccale_hypothese, radiographie_intra_buccale_hypothese, nfs_detaillee, tp_tca_inr, uree, creatininemie, glycemie, diagnostic_positif FROM fiche_bilan WHERE fiche_medicale_id = %s', (fiche_id,))
    if bilan:
        payloads['bilans__bilan_dentaire'] = {'formuleDentaire': safe_json(bilan.get('formule_dentaire')) or {}}
        payloads['bilans__bilan_radiographique'] = {
            'radiographieExtraBuccaleHypothese': bilan.get('radiographie_extra_buccale_hypothese'),
            'radiographieIntraBuccaleHypothese': bilan.get('radiographie_intra_buccale_hypothese'),
        }
        payloads['bilans__bilan_sanguin'] = {
            'nfsDetaillee': bilan.get('nfs_detaillee'),
            'tpTcaInr': bilan.get('tp_tca_inr'),
            'uree': bilan.get('uree'),
            'creatininemie': bilan.get('creatininemie'),
            'glycemie': bilan.get('glycemie'),
        }
        payloads['bilans__diagnostic_positif'] = bilan.get('diagnostic_positif')

    plans = db.fetchall('SELECT id, plan_index, type, date_supposed, description FROM fiche_plan_traitement WHERE fiche_medicale_id = %s ORDER BY plan_index ASC, id ASC', (fiche_id,))
    if plans:
        payloads['plan_traitement__items'] = plans

    documents = aggregate_documents(db.fetchall('SELECT id, type, libelle, url, group_key FROM fiche_document WHERE fiche_medicale_id = %s ORDER BY id ASC', (fiche_id,)))
    if documents:
        payloads['documents__items'] = documents

    devis = build_devis_payload(db, 'fiche_medicale_id', fiche_id)
    if devis:
        payloads['devis__items'] = devis[0] if len(devis) == 1 else devis

    return {key: value for key, value in payloads.items() if value not in (None, [], {}, '')}


def build_legacy_payloads(db: Database, observation: dict[str, Any]) -> dict[str, Any]:
    payloads: dict[str, Any] = {
        'entretien__motif_consultation': observation.get('motif'),
        'entretien__anamnese': '\n\n'.join(part for part in [observation.get('histoire_maladie'), f"Soins anterieurs: {observation.get('soins_anterieurs')}" if observation.get('soins_anterieurs') else None] if part),
        'entretien__etat_gynecologique': {'allaitement': None, 'grossesseEnCours': None, 'menstrues': None},
        'examens__endobuccal_bouche_fermee': {
            'occlusion': observation.get('occlusion'),
            'mediane': None,
            'classesAngle': None,
            'vestibules': None,
        },
        'examens__endobuccal_bouche_ouverte': {
            'hbd': None,
            'brossage': None,
            'soccu': None,
            'cinematiqueMandibulaire': None,
            'ouvertureBuccale': None,
            'temperatureBuccale': None,
            'amplitudeOuverture': None,
            'bruitsArticulaires': None,
        },
        'examens__exobuccal_inspection': {},
        'examens__exobuccal_palpation': {},
        'examens__chaines_ganglionnaires': {},
        'examens__tissus_mous_table': {},
        'examens__tissus_durs_table': {},
        'bilans__bilan_dentaire': {'formuleDentaire': safe_json(observation.get('tooths_check')) or {}},
        'bilans__bilan_radiographique': {'radiographieExtraBuccaleHypothese': None, 'radiographieIntraBuccaleHypothese': None},
        'bilans__bilan_sanguin': {'nfsDetaillee': None, 'tpTcaInr': None, 'uree': None, 'creatininemie': None, 'glycemie': None},
        'bilans__diagnostic_positif': observation.get('diagnostic'),
        'plan_traitement__items': build_legacy_plans(observation),
    }

    examens_text = [
        f"Exo inspection: {observation.get('exo_inspection')}" if observation.get('exo_inspection') else None,
        f"Exo palpation: {observation.get('exo_palpation')}" if observation.get('exo_palpation') else None,
        f"Endo inspection: {observation.get('endo_inspection')}" if observation.get('endo_inspection') else None,
        f"Endo palpation: {observation.get('endo_palpation')}" if observation.get('endo_palpation') else None,
        f"Examen parodontal: {observation.get('examen_parodontal')}" if observation.get('examen_parodontal') else None,
    ]
    payloads['examens__examen_canaux_excreteurs'] = '\n'.join(part for part in examens_text if part)

    documents = aggregate_documents_from_legacy(db.fetchall('SELECT id, libelle, fichier FROM document_medical WHERE fiche_id = %s ORDER BY id ASC', (observation['id'],)))
    if documents:
        payloads['documents__items'] = documents

    devis = build_devis_payload(db, 'fiche_id', observation['id'])
    if devis:
        payloads['devis__items'] = devis[0] if len(devis) == 1 else devis

    return {key: value for key, value in payloads.items() if value not in (None, [], {}, '')}


def build_legacy_plans(observation: dict[str, Any]) -> list[dict[str, Any]]:
    plan_map = [
        ('urgence', observation.get('traitement_urgence')),
        ('dentaire', observation.get('traitement_dentaire')),
        ('parodontal', observation.get('traitement_parodontal')),
        ('orthodontique', observation.get('traitement_orthodontique')),
        ('autres', observation.get('autres')),
    ]
    plans: list[dict[str, Any]] = []
    index = 1
    for plan_type, description in plan_map:
        if not description:
            continue
        plans.append({'id': None, 'planIndex': index, 'type': plan_type, 'dateSupposed': None, 'description': description})
        index += 1
    return plans


def build_devis_payload(db: Database, foreign_key: str, foreign_id: int) -> list[dict[str, Any]]:
    devis_rows = db.fetchall(
        f'SELECT id, date, type, statut, montant, reste FROM devis WHERE {foreign_key} = %s ORDER BY id ASC',
        (foreign_id,),
    )
    payloads: list[dict[str, Any]] = []
    for devis in devis_rows:
        contenus = db.fetchall('SELECT id, designation, qte, montant, montant_total FROM contenu_devis WHERE devis_id = %s ORDER BY id ASC', (devis['id'],))
        payloads.append({
            'id': devis['id'],
            'date': str(devis['date']) if devis['date'] is not None else None,
            'type': devis['type'],
            'statut': devis['statut'],
            'montant': float(devis['montant'] or 0),
            'reste': float(devis['reste'] or 0),
            'contenus': contenus,
        })
    return payloads


def aggregate_documents(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    documents: dict[str, dict[str, Any]] = {}
    for row in rows:
        group_key = row.get('group_key') or f'legacy-{row.get("id")}'
        entry = documents.setdefault(group_key, {
            'groupKey': group_key,
            'type': row.get('type'),
            'libelle': row.get('libelle'),
            'urls': [],
        })
        if row.get('url'):
            entry['urls'].append(row['url'])
    return list(documents.values())


def aggregate_documents_from_legacy(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    documents: list[dict[str, Any]] = []
    for row in rows:
        if not row.get('fichier'):
            continue
        documents.append({
            'groupKey': f'legacy-doc-{row.get("id")}',
            'type': 'legacy_document',
            'libelle': row.get('libelle') or 'Document migre',
            'urls': [row['fichier']],
        })
    return documents


def upsert_field_payloads(db: Database, fiche_id: int, champ_ids: dict[str, int | None], payloads: dict[str, Any], stats: MigrationStats) -> int:
    written = 0
    for field_code, value in payloads.items():
        champ_id = champ_ids.get(field_code)
        if champ_id is None:
            stats.anomalies.append(f'fiche {fiche_id}: champ dynamique absent pour {field_code}')
            continue

        db.execute(
            'INSERT INTO fiche_medicale_valeur (fiche_medicale_id, champ_id, value, created_at, updated_at) VALUES (%s, %s, %s, NOW(), NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
            (fiche_id, champ_id, json.dumps(value, ensure_ascii=True)),
        )
        written += 1
    return written


def safe_json(value: Any) -> Any:
    if value in (None, ''):
        return None
    if isinstance(value, (dict, list)):
        return value
    if isinstance(value, (bytes, bytearray)):
        value = value.decode('utf-8')
    try:
        return json.loads(value)
    except Exception:
        return None


if __name__ == '__main__':
    sys.exit(main())