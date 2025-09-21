## [2025-09-19 22:24] Infographie v2
- Corrections `app/Core/Request.php` pour supporter les chemins en sous-dossier (`/fluxvision_fin/api/v2/...`) ; les 404 front sont levés.
- Nettoyage diagnostic communes (plus de warning `$zoneIds`).
- Normalisation des réponses JSON côté front : `loadNuitees*` / `loadExcursionnistes*` extraient `data.data` avant de remplir les charts ? affichage revenus.
