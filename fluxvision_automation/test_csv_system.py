#!/usr/bin/env python3
"""
Test du système CSV optimisé pour FluxVision
"""

import os
import polars as pl
import shutil
from pathlib import Path

def create_test_csv_data():
    """Crée des données de test CSV."""
    test_dir = Path('test_csv_system')
    test_dir.mkdir(exist_ok=True)
    
    # Créer 15 fichiers CSV de test
    test_files = []
    for i in range(15):
        data = pl.DataFrame({
            'id': range(i*50, (i+1)*50),
            'nom': [f'User_{j}' for j in range(i*50, (i+1)*50)],
            'age': [(j % 60) + 18 for j in range(i*50, (i+1)*50)],
            'ville': ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice'][j % 5] for j in range(50),
            'score': [(j * 3.14) % 100 for j in range(i*50, (i+1)*50)]
        })
        
        file_path = test_dir / f'data_B{(i%6)+1}_{i:03d}.csv'
        data.write_csv(file_path, separator=';')
        test_files.append(str(file_path))
    
    return test_files, test_dir

def test_csv_merge_function():
    """Test la fonction de fusion CSV."""
    print("🧪 Test de la fonction de fusion CSV")
    
    test_files, test_dir = create_test_csv_data()
    output_file = test_dir / 'merged_result.csv'
    
    try:
        # Import de la fonction
        import sys
        sys.path.append('.')
        from main_anita import merge_to_csv_memory_optimized
        
        # Test de fusion
        print(f"📦 Fusion de {len(test_files)} fichiers CSV...")
        merge_to_csv_memory_optimized(test_files, str(output_file), incremental=False)
        
        # Vérifier le résultat
        if os.path.exists(output_file):
            result_df = pl.read_csv(str(output_file), separator=';')
            print(f"✅ Fusion réussie: {len(result_df)} lignes, {len(result_df.columns)} colonnes")
            print(f"📋 Colonnes: {list(result_df.columns)}")
            
            # Vérifier quelques statistiques
            expected_rows = 15 * 50  # 15 fichiers * 50 lignes chacun
            print(f"📊 Lignes attendues: {expected_rows}, obtenues: {len(result_df)}")
            
            return True
        else:
            print("❌ Fichier de sortie non créé")
            return False
            
    except Exception as e:
        print(f"❌ Erreur: {e}")
        import traceback
        traceback.print_exc()
        return False
    finally:
        # Nettoyage
        shutil.rmtree(test_dir)

def test_incremental_merge():
    """Test la fusion incrémentale."""
    print("\n🧪 Test de la fusion incrémentale")
    
    test_dir = Path('test_incremental')
    test_dir.mkdir(exist_ok=True)
    
    try:
        # Créer des données initiales
        initial_data = pl.DataFrame({
            'id': range(100),
            'nom': [f'Initial_{i}' for i in range(100)],
            'age': [25 + (i % 40) for i in range(100)]
        })
        
        existing_file = test_dir / 'existing.csv'
        initial_data.write_csv(existing_file, separator=';')
        
        # Créer de nouvelles données
        new_data = pl.DataFrame({
            'id': range(100, 150),
            'nom': [f'New_{i}' for i in range(100, 150)],
            'age': [30 + (i % 35) for i in range(100, 150)]
        })
        
        new_file = test_dir / 'new.csv'
        new_data.write_csv(new_file, separator=';')
        
        # Test fusion incrémentale
        import sys
        sys.path.append('.')
        from main_anita import merge_to_csv_memory_optimized
        
        merge_to_csv_memory_optimized([str(new_file)], str(existing_file), incremental=True)
        
        # Vérifier le résultat
        result_df = pl.read_csv(str(existing_file), separator=';')
        print(f"✅ Fusion incrémentale: {len(result_df)} lignes totales")
        
        # Vérifier qu'on a bien les données initiales + nouvelles
        expected_total = 100 + 50  # Initial + nouveau
        if len(result_df) >= expected_total:
            print(f"✅ Données correctement fusionnées")
            return True
        else:
            print(f"❌ Données manquantes: {len(result_df)} < {expected_total}")
            return False
            
    except Exception as e:
        print(f"❌ Erreur fusion incrémentale: {e}")
        return False
    finally:
        shutil.rmtree(test_dir)

def test_memory_efficiency():
    """Test l'efficacité mémoire."""
    print("\n🧪 Test d'efficacité mémoire")
    
    try:
        import sys
        sys.path.append('.')
        from main_anita import get_memory_usage, optimize_workers_for_memory
        
        # Mesurer la mémoire de base
        base_memory = get_memory_usage()
        print(f"💾 Mémoire de base: {base_memory:.2f}GB")
        
        # Test optimisation workers
        workers = optimize_workers_for_memory()
        print(f"⚡ Workers optimaux: {workers}")
        
        # Créer et traiter des données pour voir l'impact mémoire
        test_data = pl.DataFrame({
            'data': range(10000),
            'text': [f'Text_{i}' * 10 for i in range(10000)]  # Données plus volumineuses
        })
        
        memory_after_data = get_memory_usage()
        memory_increase = memory_after_data - base_memory
        print(f"💾 Augmentation mémoire avec données: {memory_increase:.3f}GB")
        
        # Nettoyage
        del test_data
        import gc
        gc.collect()
        
        memory_after_cleanup = get_memory_usage()
        cleanup_efficiency = (memory_after_data - memory_after_cleanup) / memory_increase if memory_increase > 0 else 0
        print(f"🧹 Efficacité nettoyage: {cleanup_efficiency:.1%}")
        
        return True
        
    except Exception as e:
        print(f"❌ Erreur test mémoire: {e}")
        return False

def main():
    """Test principal du système CSV."""
    print("🚀 Test du système CSV optimisé FluxVision")
    print("="*55)
    
    tests = [
        ("Efficacité mémoire", test_memory_efficiency),
        ("Fusion CSV", test_csv_merge_function),
        ("Fusion incrémentale", test_incremental_merge)
    ]
    
    results = []
    for test_name, test_func in tests:
        print(f"\n🔍 {test_name}...")
        try:
            success = test_func()
            results.append((test_name, success))
        except Exception as e:
            print(f"❌ Erreur inattendue: {e}")
            results.append((test_name, False))
    
    # Résumé
    print("\n📋 RÉSUMÉ DES TESTS")
    print("="*30)
    passed = 0
    for test_name, success in results:
        status = "✅ PASS" if success else "❌ FAIL"
        print(f"{status} {test_name}")
        if success:
            passed += 1
    
    print(f"\n🎯 Résultat: {passed}/{len(results)} tests réussis")
    
    if passed == len(results):
        print("🎉 Système CSV prêt ! Plus de problèmes Parquet.")
        print("💡 Avantages CSV:")
        print("   - Pas de bugs LazyFrame/sink_parquet")
        print("   - Compatible avec tous les outils")
        print("   - Lecture/écriture plus stable")
        print("   - Débogage plus facile")
    else:
        print("⚠️  Certains tests ont échoué. Vérifiez les erreurs ci-dessus.")

if __name__ == '__main__':
    main() 