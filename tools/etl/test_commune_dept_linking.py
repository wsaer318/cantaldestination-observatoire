#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Test script pour vérifier la liaison commune-département
"""

import os
import sys
import polars as pl
from pathlib import Path
from populate_facts_optimized import FactTablePopulator

def test_commune_dept_linking():
    """Test de la liaison commune-département sur un fichier Lieu*"""

    # Chemin vers un fichier Lieu* de test
    test_file = Path("fluxvision_automation/data/data_extracted/B1 2025_1461554/CABA/LieuNuitee_Soir_2025B1_CABA0.csv")

    if not test_file.exists():
        print(f"Fichier de test introuvable: {test_file}")
        return

    print(f"Test avec le fichier: {test_file.name}")

    # Initialiser le populateur en mode test
    pop = FactTablePopulator(test_mode=True)

    try:
        # Se connecter à la base
        if not pop.connect():
            print("Erreur de connexion à la base")
            return

        # Créer les tables si nécessaire
        pop._ensure_dims()
        pop.load_dimension_cache()

        # Vérifier l'état initial
        pop.cursor.execute("SELECT COUNT(*) FROM dim_communes WHERE id_departement IS NOT NULL")
        communes_with_dept_before = pop.cursor.fetchone()[0]
        print(f"Communes avec id_departement avant traitement: {communes_with_dept_before}")

        pop.cursor.execute("SELECT COUNT(*) FROM dim_communes")
        total_communes_before = pop.cursor.fetchone()[0]
        print(f"Total communes avant traitement: {total_communes_before}")

        # Traiter le fichier Lieu*
        inserted = pop.process_lieu_csv_file(test_file, "LieuNuitee_Soir")
        print(f"Fichier traité, {inserted} lignes insérées")

        # Vérifier l'état après
        pop.cursor.execute("SELECT COUNT(*) FROM dim_communes WHERE id_departement IS NOT NULL")
        communes_with_dept_after = pop.cursor.fetchone()[0]
        print(f"Communes avec id_departement après traitement: {communes_with_dept_after}")

        pop.cursor.execute("SELECT COUNT(*) FROM dim_communes")
        total_communes_after = pop.cursor.fetchone()[0]
        print(f"Total communes après traitement: {total_communes_after}")

        # Afficher quelques exemples
        pop.cursor.execute("""
            SELECT c.code_insee, c.nom_commune, d.nom_departement
            FROM dim_communes c
            LEFT JOIN dim_departements d ON c.id_departement = d.id_departement
            WHERE c.id_departement IS NOT NULL
            LIMIT 5
        """)
        examples = pop.cursor.fetchall()
        print("\nExemples de communes liées aux départements:")
        for code_insee, nom_commune, nom_dept in examples:
            print(f"  {code_insee}: {nom_commune} -> {nom_dept}")

        print("\n✅ Test terminé avec succès!")

    except Exception as e:
        print(f"❌ Erreur lors du test: {e}")
        import traceback
        traceback.print_exc()
    finally:
        pop.close()

if __name__ == "__main__":
    test_commune_dept_linking()
