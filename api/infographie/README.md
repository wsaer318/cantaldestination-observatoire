# API Infographie

Ce dossier contient les API spécifiquement utilisées par le module d'infographie de FluxVision.

## APIs incluses :

### 1. `infographie_indicateurs_cles.php`
- **Fonction** : Récupère les indicateurs clés généraux et les dates de période
- **Utilisé pour** : Génération des cartes d'indicateurs dans les sections Touristes et Excursionnistes + mise à jour du header
- **Paramètres** : `annee`, `periode`, `zone`

### 2. `infographie_departements_touristes.php`
- **Fonction** : Récupère les données d'origines géographiques par départements des nuitées (touristes)
- **Utilisé pour** : Génération des graphiques de départements pour les touristes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 3. `infographie_departements_excursionnistes.php`
- **Fonction** : Récupère les données d'origines géographiques par départements des excursionnistes
- **Utilisé pour** : Génération des graphiques de départements pour les excursionnistes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 4. `infographie_regions_touristes.php`
- **Fonction** : Récupère les données d'origines géographiques par régions des nuitées (touristes)
- **Utilisé pour** : Génération des graphiques de régions pour les touristes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 5. `infographie_regions_excursionnistes.php`
- **Fonction** : Récupère les données d'origines géographiques par régions des excursionnistes
- **Utilisé pour** : Génération des graphiques de régions pour les excursionnistes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 6. `infographie_pays_touristes.php`
- **Fonction** : Récupère les données d'origines géographiques par pays des nuitées (touristes)
- **Utilisé pour** : Génération des graphiques de pays pour les touristes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 7. `infographie_pays_excursionnistes.php`
- **Fonction** : Récupère les données d'origines géographiques par pays des excursionnistes
- **Utilisé pour** : Génération des graphiques de pays pour les excursionnistes
- **Paramètres** : `annee`, `periode`, `zone`, `limit`

### 8. `infographie_communes_excursion.php`
- **Fonction** : Récupère les données des destinations d'excursion par communes (top 10)
- **Utilisé pour** : Génération du graphique de mobilité interne dans l'infographie
- **Paramètres** : `annee`, `periode`, `zone`, `limit` (défaut: 10)
- **Optimisations** : Requête unique optimisée, LIMIT en SQL, cache unifié, index automatiques

### 9. `infographie_periodes.php`
- **Fonction** : Récupère la liste des périodes disponibles pour les filtres
- **Utilisé pour** : Chargement dynamique des options de période dans les filtres de l'infographie
- **Paramètres** : `action=all`

## Utilisation :

Ces API sont spécifiquement référencées dans :
- `static/js/infographie.js` : Appels directs aux API de données
- `static/js/filters_loader.js` : Chargement des filtres

## Dépendances incluses :

### 1. `periodes_manager_db.php`
- **Fonction** : Gestionnaire de périodes et connexion base de données
- **Requis par** : Toutes les API infographie pour la gestion des périodes

### 2. `bloc_a_working.php`
- **Fonction** : Logique métier pour les indicateurs clés
- **Requis par** : `infographie_indicateurs_cles.php`

**Note** : Le fichier `database.php` est référencé depuis le dossier parent via `dirname(__DIR__) . '/database.php'`

## Organisation :

Ce dossier permet de :
1. **Isoler** les API utilisées par l'infographie
2. **Faciliter la maintenance** en centralisant les dépendances
3. **Améliorer la sécurité** en permettant des contrôles d'accès spécifiques
4. **Optimiser les performances** avec des versions potentiellement dédiées

## Note technique :

Les fichiers sont des copies des API principales situées dans `/api/`. Toute modification des API principales doit être répercutée dans ce dossier si nécessaire. 