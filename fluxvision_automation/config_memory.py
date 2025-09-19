# Configuration pour l'optimisation de la mémoire
# Vous pouvez ajuster ces paramètres selon votre système

# Limites mémoire
MAX_MEMORY_USAGE_GB = 2.0  # Limite mémoire en GB avant optimisation
MEMORY_WARNING_GB = 4.0    # Seuil d'alerte mémoire
MEMORY_CRITICAL_GB = 6.0   # Seuil critique mémoire

# Traitement par batches
BATCH_SIZE = 20            # Nombre de fichiers par batch
LARGE_FILE_THRESHOLD = 100 # Fichiers considérés comme "lourds" (en MB)

# Parallélisme
MIN_WORKERS = 2            # Minimum de workers parallèles
MAX_WORKERS = 8            # Maximum de workers parallèles
REDUCE_WORKERS_MEMORY_GB = 3.0  # Réduire les workers si mémoire > ce seuil

# Optimisations Polars
POLARS_STREAMING = True    # Utiliser le mode streaming de Polars
POLARS_COMPRESSION = 'zstd'  # Compression Parquet (zstd, lz4, snappy)
POLARS_CHUNK_SIZE = 1000   # Taille des chunks pour le streaming

# Debugging
ENABLE_MEMORY_LOGGING = True  # Activer les logs mémoire détaillés
MEMORY_LOG_INTERVAL = 10     # Intervalle de log mémoire (en batches)

def get_memory_config():
    """Retourne la configuration mémoire actuelle."""
    return {
        'max_memory_gb': MAX_MEMORY_USAGE_GB,
        'batch_size': BATCH_SIZE,
        'min_workers': MIN_WORKERS,
        'max_workers': MAX_WORKERS,
        'streaming': POLARS_STREAMING,
        'compression': POLARS_COMPRESSION
    }

def adjust_config_for_system():
    """Ajuste automatiquement la config selon le système."""
    import psutil
    
    # Mémoire système disponible
    total_memory_gb = psutil.virtual_memory().total / 1024 / 1024 / 1024
    available_memory_gb = psutil.virtual_memory().available / 1024 / 1024 / 1024
    
    # Ajustements automatiques
    global MAX_MEMORY_USAGE_GB, BATCH_SIZE, MAX_WORKERS
    
    if total_memory_gb < 4:
        # Système avec peu de RAM
        MAX_MEMORY_USAGE_GB = 1.0
        BATCH_SIZE = 10
        MAX_WORKERS = 2
    elif total_memory_gb < 8:
        # Système moyen
        MAX_MEMORY_USAGE_GB = 2.0
        BATCH_SIZE = 20
        MAX_WORKERS = 4
    else:
        # Système avec beaucoup de RAM
        MAX_MEMORY_USAGE_GB = min(4.0, available_memory_gb * 0.3)
        BATCH_SIZE = 30
        MAX_WORKERS = 8
    
    return {
        'total_memory_gb': total_memory_gb,
        'available_memory_gb': available_memory_gb,
        'adjusted_max_memory_gb': MAX_MEMORY_USAGE_GB,
        'adjusted_batch_size': BATCH_SIZE,
        'adjusted_max_workers': MAX_WORKERS
    } 