#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import logging, os, re, signal, sys, time
from datetime import date

try:
    import mysql.connector
    from mysql.connector import errorcode
except Exception:
    print("Installez : pip install mysql-connector-python", file=sys.stderr)
    raise

# --------- Paramètres optimisés pour 8 Go RAM ----------
YEAR = 2025
INITIAL_LIMIT = 20_000
MIN_LIMIT = 1_000
LOCK_TIMEOUT = 20
TX_ISOLATION = "READ COMMITTED"
BACKOFF_START = 1.5
BACKOFF_MAX = 30.0
LOG_FILE = "cleanup_year.log"

STOP_REQUESTED = False
def _graceful(signum, frame):
    global STOP_REQUESTED
    STOP_REQUESTED = True
    logging.warning("Arrêt demandé (signal %s). Fin du lot en cours puis arrêt propre.", signum)

signal.signal(signal.SIGINT, _graceful)
signal.signal(signal.SIGTERM, _graceful)

def setup_logging():
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
        handlers=[logging.FileHandler(LOG_FILE, encoding="utf-8"),
                  logging.StreamHandler(sys.stdout)],
    )

def quarter_ranges(year: int):
    return [
        (date(year, 1, 1),  date(year, 4, 1)),
        (date(year, 4, 1),  date(year, 7, 1)),
        (date(year, 7, 1),  date(year, 10, 1)),
        (date(year, 10, 1), date(year + 1, 1, 1)),
    ]

def try_load_defaults_from_php(base_dir: str):
    php_path = os.path.join(base_dir, 'database.php')
    if not os.path.exists(php_path):
        return None
    try:
        with open(php_path, 'r', encoding='utf-8') as f: txt = f.read()
        m = re.search(r"Configuration locale.*?return \[(.*?)\];", txt, re.S)
        block = m.group(1) if m else txt
        def grab(k,d=None):
            mm = re.search(r"'" + re.escape(k) + r"'\s*=>\s*'([^']*)'", block)
            return mm.group(1) if mm else d
        def grab_int(k,d=None):
            mm = re.search(r"'" + re.escape(k) + r"'\s*=>\s*(\d+)", block)
            return int(mm.group(1)) if mm else d
        return {
            'host': grab('host', os.getenv('DB_HOST','localhost')),
            'port': grab_int('port', int(os.getenv('DB_PORT','3306'))),
            'database': grab('database', os.getenv('DB_NAME','fluxvision')),
            'username': grab('username', os.getenv('DB_USER','root')),
            'password': grab('password', os.getenv('DB_PASSWORD','')),
        }
    except Exception:
        return None

def get_db_config():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    return try_load_defaults_from_php(base_dir) or {
        'host': os.getenv('DB_HOST','localhost'),
        'port': int(os.getenv('DB_PORT','3306')),
        'database': os.getenv('DB_NAME','fluxvision'),
        'username': os.getenv('DB_USER','root'),
        'password': os.getenv('DB_PASSWORD',''),
    }

def connect_mysql(cfg, lock_timeout: int, isolation: str):
    conn = mysql.connector.connect(
        host=cfg['host'], port=cfg['port'], user=cfg['username'],
        password=cfg['password'], database=cfg['database'],
        autocommit=True, connection_timeout=30,
    )
    cur = conn.cursor(buffered=False)
    cur.execute("SET SESSION innodb_lock_wait_timeout = %s", (lock_timeout,))
    cur.execute("SET SESSION sql_safe_updates = 0")
    cur.execute(f"SET SESSION TRANSACTION ISOLATION LEVEL {isolation}")
    cur.execute("SET SESSION net_write_timeout = 120")
    cur.execute("SET SESSION wait_timeout = 28800")
    return conn, cur

def discover_fact_tables_with_date(cur) -> list:
    cur.execute(
        "SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS "
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'fact%' "
        "AND COLUMN_NAME = 'date' ORDER BY TABLE_NAME"
    )
    return [r[0] for r in cur.fetchall()]

def has_date_index(cur, table: str) -> bool:
    cur.execute(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS "
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME='date' LIMIT 1",
        (table,)
    )
    return cur.fetchone() is not None

def is_partitioned_by_year(cur, table: str, year: int):
    cur.execute(
        "SELECT PARTITION_NAME FROM INFORMATION_SCHEMA.PARTITIONS "
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=%s AND PARTITION_NAME IS NOT NULL",
        (table,)
    )
    parts = [r[0] for r in cur.fetchall()]
    if not parts: return (False, None)
    y = str(year)
    for p in parts:
        if p and re.search(fr"(?:^|_)p?{y}(?:$|_)", p, re.I):
            return (True, p)
    return (True, None)

def count_rows(cur, table: str, start: date, end: date) -> int:
    cur.execute(f"SELECT COUNT(*) FROM `{table}` WHERE `date` >= %s AND `date` < %s", (start, end))
    return int(cur.fetchone()[0])

def delete_batch(cur, table: str, start: date, end: date, limit: int,
                 backoff_s: float, max_backoff: float, min_limit: int):
    # Hint MariaDB: limite le lock wait timeout de CE lot
    sql = (f"DELETE /*+ SET_VAR(innodb_lock_wait_timeout=10) */ "
           f"FROM `{table}` WHERE `date` >= %s AND `date` < %s ORDER BY `date` LIMIT %s")
    try:
        cur.execute(sql, (start, end, limit))
        return cur.rowcount or 0, limit, backoff_s
    except mysql.connector.Error as e:
        if e.errno in (errorcode.ER_LOCK_WAIT_TIMEOUT, errorcode.ER_LOCK_DEADLOCK):
            logging.warning("Conflit de verrou sur %s (errno %s). LIMIT=%s. Retry dans %.1fs",
                            table, e.errno, limit, backoff_s)
            if limit > min_limit:
                limit = max(min_limit, int(limit * 0.5))
            time.sleep(backoff_s)
            backoff_s = min(max_backoff, backoff_s * 1.8)
            return -1, limit, backoff_s
        raise

def drop_partition_if_possible(cur, table: str, year: int) -> bool:
    part_exists, part_name = is_partitioned_by_year(cur, table, year)
    if not part_exists: return False
    if part_name:
        logging.info("%s: partition '%s' détectée pour %s -> DROP PARTITION", table, part_name, year)
        cur.execute(f"ALTER TABLE `{table}` DROP PARTITION `{part_name}`")
        logging.info("%s: DROP PARTITION terminé.", table)
        return True
    logging.info("%s: table partitionnée mais partition %s non identifiée — fallback en lots.", table, year)
    return False

def process_table(cur, table: str, year: int):
    logging.info("---- %s ----", table)
    if not has_date_index(cur, table):
        logging.warning("%s: pas d'index sur `date` -> opérations plus lentes et davantage de verrous.", table)

    if drop_partition_if_possible(cur, table, year):
        return

    total = 0
    for start, end in quarter_ranges(year):
        try:
            n = count_rows(cur, table, start, end)
            logging.info("%s %s..%s: %s lignes à supprimer", table, start, end, n)
        except Exception as e:
            logging.warning("%s %s..%s: comptage impossible (%s) — on supprime quand même.", table, start, end, e)

        local_limit = INITIAL_LIMIT
        local_backoff = BACKOFF_START

        while not STOP_REQUESTED:
            affected, local_limit, local_backoff = delete_batch(
                cur, table, start, end, local_limit, local_backoff, BACKOFF_MAX, MIN_LIMIT
            )
            if affected == 0:
                break
            if affected > 0:
                total += affected
                logging.info("%s %s..%s: -%s (total %s) [limit=%s]",
                             table, start, end, affected, total, local_limit)
        if STOP_REQUESTED:
            logging.info("%s: arrêt demandé — on stoppe proprement après ce trimestre.", table)
            break

    if not STOP_REQUESTED:
        logging.info("%s: suppression totale effectuée: %s lignes", table, total)

def main():
    setup_logging()
    cfg = get_db_config()
    conn = cur = None
    try:
        conn, cur = connect_mysql(cfg, LOCK_TIMEOUT, TX_ISOLATION)
        logging.info("Connexion OK à %s@%s:%s/%s. Suppression directe année %s.",
                     cfg['username'], cfg['host'], cfg['port'], cfg['database'], YEAR)

        tables = discover_fact_tables_with_date(cur)
        if not tables:
            logging.warning("Aucune table de faits avec colonne `date` trouvée.")
            return
        for table in tables:
            if STOP_REQUESTED: break
            process_table(cur, table, YEAR)
        logging.info("Terminé." if not STOP_REQUESTED else "Arrêt demandé : fin du programme.")
    except Exception as e:
        logging.exception("Erreur: %s", e); sys.exit(1)
    finally:
        try:
            if cur: cur.close()
            if conn: conn.close()
        except Exception:
            pass

if __name__ == "__main__":
    main()
