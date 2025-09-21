# Modularisation CantalDestination

## Contexte et objectif
Le projet vise à remplacer l’ancienne collection de scripts PHP isolés (`api/*.php`) par une architecture REST modulaire et extensible (`app/Core`, `App\Modules\…`). L’objectif général est d’obtenir un codebase plus maintenable, testable et sécurisé : chaque domaine expose des contrôleurs dédiés, des services réutilisables et une couche commune `/api/v2`, tandis que les anciens endpoints restent temporarisés via des proxys pour ne pas casser les intégrations existantes.

## Avancement
- Noyau applicatif PHP (`app/Core`) opérationnel : `Request`, `Response`, `Router`, `Controller`, gestion centralisée des erreurs et bootstrap commun (`app/bootstrap.php`, point d’entrée `api/v2/index.php`).
- Modules disponibles :
  - `SharedSpaces` (CRUD espaces, membres, infographies) – front et scripts consommateurs basculés vers `/api/v2/shared-spaces`, les anciens scripts (`api/shared-spaces/*.php`) servent de proxys.
  - `Infographie` : routes `/api/v2/infographie/departements-touristes`, `/api/v2/infographie/regions-touristes`, `/api/v2/infographie/departements-excursionnistes`, `/api/v2/infographie/regions-excursionnistes` avec cache unifié et agrégations SQL. Le front (`static/js/infographie.js`, bundle dist) et les outils de diagnostic utilisent ces nouvelles routes ; les scripts legacy redirigent vers la v2.
    `InfographieDataService` centralise désormais la connexion PDO, le cache et la résolution zones/périodes/provenances pour les contrôleurs.
- Documentation de la trajectoire et modules à jour (ce fichier).

## Travail en cours
- Poursuite de la migration des autres endpoints infographie (communes/pays excursionnistes, indicateurs clés, comparaisons) en factorisant davantage les services (dates, zones, cache) et en limitant les `require` directs, désormais via `InfographieDataService`.
- Maintien des proxys `/api/infographie/*.php` et `/api/shared-spaces/*.php` jusqu'à ce que tous les clients aient basculé sur `/api/v2`.

## Prochaines étapes
1. **Infographie (suite)** : porter les endpoints restants, enrichir `InfographieController` et mutualiser les services partagés.
2. **Middlewares de sécurité** : ajouter auth/CSRF/rate-limit au `Router` pour supprimer les `Auth::requireAdmin()` dispersés et uniformiser les réponses.
3. **Gestion de cache modulaire** : généraliser `InfographieDataService` (et équivalents) aux autres modules pour mutualiser `CantalDestinationCacheManager` et éviter les instanciations directes.
4. **Tests & Observabilité** : mettre en place des tests fonctionnels (PHPUnit/Behat) sur `/api/v2` et intégrer un logger structuré (Monolog) dans `Application`.
5. **Documentation & Front** : compléter le guide de migration (structure `app/`, conventions PSR-12), basculer progressivement les dashboards vers `/api/v2`, retirer les proxys legacy une fois inutiles.

_Note : suivre l’usage des endpoints historiques pour planifier leur suppression définitive dès que les consommateurs auront migré._
- **Infographie** : routage v2 stable depuis `/fluxvision_fin` (Request::fromGlobals normalise maintenant le chemin); le front reçoit les données et génère les graphiques. Nettoyage des diagnostics (plus de warning `$zoneIds`) et adaptation coté JS/bundle (`loadNuitees*` / `loadExcursionnistes*`) pour qu’ils consomment `data.data`.
- **Infographie** : suppression du code dupliqué après la classe `InfographieController` (lignes ~805) qui provoquait une erreur de parsing PHP. Les endpoints `/api/v2/infographie/regions-***` servent à nouveau des JSON valides et les sections Origines Géographiques sont rendues côté front.
- **Infographie** : ajout d'`AddDefaultCharset UTF-8` et des `AddType` sur les assets statiques dans `.htaccess` pour corriger l'affichage des accents (ex. « Présences ») sur l'infographie.
