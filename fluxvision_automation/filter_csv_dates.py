#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de filtrage des données CSV par date
Supprime les lignes avec des dates <= cutoff_date
Utilise Polars pour la performance
"""

import polars as pl
import logging
from pathlib import Path
from datetime import datetime, date
import sys

def setup_logging(log_file: Path):
    """Configuration du logging."""
    log_file.parent.mkdir(parents=True, exist_ok=True)
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
        handlers=[
            logging.FileHandler(log_file, encoding="utf-8"),
            logging.StreamHandler(sys.stdout)
        ]
    )
    return logging.getLogger(__name__)

def read_database_files_list(list_file_path: Path):
    """Lit la liste des fichiers requis pour la base de données."""
    if not list_file_path.exists():
        return []
    with list_file_path.open("r", encoding="utf-8") as f:
        return [
            line.strip()
            for line in f
            if line.strip() and not line.lstrip().startswith("#")
        ]

def filter_csv_by_date(
    file_path: Path,
    cutoff: date,
    logger: logging.Logger
) -> tuple[int, int, bool]:
    """
    Filtre un fichier CSV en supprimant les lignes avec des dates <= cutoff.
    Retourne (nb_supprimees, nb_conservees, success_flag).
    """
    try:
        logger.info(f"Traitement de {file_path.name}...")
        # Lire le CSV
        df = pl.read_csv(file_path)
        
        # Nettoyer un éventuel BOM sur la colonne Date
        cols_clean = {
            col: col.lstrip("\ufeff") for col in df.columns
            if col.startswith("\ufeff")
        }
        if cols_clean:
            df = df.rename(cols_clean)

        if "Date" not in df.columns:
            logger.warning(f"⚠ Colonne 'Date' non trouvée dans {file_path.name}")
            return 0, df.height, False

        # Forcer ou convertir 'Date' en type Date si nécessaire
        if df.schema["Date"] != pl.Date:
            df = df.with_columns(
                pl.col("Date").str.strptime(
                    pl.Date, "%Y-%m-%d", strict=False
                )
            )

        total_before = df.height
        df_filtered = df.filter(pl.col("Date") > cutoff)
        total_after = df_filtered.height
        removed = total_before - total_after

        if removed > 0:
            # Modification en place : écraser le fichier original
            df_filtered.write_csv(file_path)
            logger.info(f"✅ {file_path.name} : {removed} lignes supprimées, {total_after} conservées")
        else:
            logger.info(f"✅ {file_path.name} : aucune ligne à supprimer")

        return removed, total_after, True

    except Exception as e:
        logger.error(f"❌ Erreur lors du traitement de {file_path.name} : {e}")
        return 0, 0, False

def main():
    """Fonction principale"""
    logger = setup_logging(Path("filter_dates.log"))
    logger.info("=== FILTRAGE DES DONNÉES CSV PAR DATE ===")

    cutoff_date_str = "2025-02-28"
    cutoff_date = datetime.strptime(cutoff_date_str, "%Y-%m-%d").date()
    logger.info(f"Suppression des lignes avec Date <= {cutoff_date_str}")

    script_dir = Path(__file__).parent
    csv_dir = script_dir / "data" / "data_clean" / "data_merged_csv"
    list_file = script_dir / "database_source_files.txt"

    if not csv_dir.exists():
        logger.error(f"Le répertoire {csv_dir} n'existe pas")
        sys.exit(1)

    required = read_database_files_list(list_file)
    if required:
        logger.info(f"Traitement des {len(required)} fichiers listés dans database_source_files.txt")
        csv_files = [csv_dir / name for name in required if (csv_dir / name).exists()]
    else:
        logger.warning("Aucun fichier listé, traitement de tous les CSV du répertoire")
        csv_files = list(csv_dir.glob("*.csv"))

    if not csv_files:
        logger.warning("Aucun fichier CSV trouvé à traiter")
        sys.exit(0)

    total_files = len(csv_files)
    files_ok = files_err = 0
    total_removed = total_kept = 0

    logger.info(f"Début du traitement de {total_files} fichiers...")
    logger.info("-" * 50)

    for fp in csv_files:
        removed, kept, success = filter_csv_by_date(fp, cutoff_date, logger)
        if success:
            files_ok += 1
            total_removed += removed
            total_kept += kept
        else:
            files_err += 1

    # Résumé final
    logger.info("-" * 50)
    logger.info("=== RÉSUMÉ FINAL ===")
    logger.info(f"Fichiers traités avec succès : {files_ok}")
    logger.info(f"Fichiers en erreur           : {files_err}")
    logger.info(f"Total lignes supprimées      : {total_removed:,}")
    logger.info(f"Total lignes conservées      : {total_kept:,}")
    logger.info("=== FILTRAGE TERMINÉ ===")

if __name__ == "__main__":
    main()
