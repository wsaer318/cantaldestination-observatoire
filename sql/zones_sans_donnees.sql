-- Zones sans données pour la période 05 juillet 2025 - 31 août 2025

SET @periode_debut = '2025-07-05';
SET @periode_fin = '2025-08-31';

-- Identifier les zones d'observation nouvelles qui n'ont AUCUNE donnée dans la période
SELECT 
    dz.nom_zone as zone_bdd,
    CASE 
        WHEN dz.nom_zone = 'HTC' THEN 'HAUTES TERRES'
        WHEN dz.nom_zone = 'GENTIANE' THEN 'HAUT CANTAL'
        WHEN dz.nom_zone = 'CABA' THEN 'PAYS D\'AURILLAC'
        WHEN dz.nom_zone = 'STATION' THEN 'LIORAN'
    END as zone_utilisateur,
    'AUCUNE DONNÉE' as statut
FROM dim_zones_observation dz
WHERE dz.nom_zone IN ('HTC', 'GENTIANE', 'CABA', 'STATION')
AND NOT EXISTS (
    SELECT 1 FROM fact_nuitees fn 
    WHERE fn.id_zone = dz.id_zone 
    AND fn.date BETWEEN @periode_debut AND @periode_fin
)
AND NOT EXISTS (
    SELECT 1 FROM fact_diurnes fd 
    WHERE fd.id_zone = dz.id_zone 
    AND fd.date BETWEEN @periode_debut AND @periode_fin
)
AND NOT EXISTS (
    SELECT 1 FROM fact_nuitees_departements fnd 
    WHERE fnd.id_zone = dz.id_zone 
    AND fnd.date BETWEEN @periode_debut AND @periode_fin
)
AND NOT EXISTS (
    SELECT 1 FROM fact_diurnes_departements fdd 
    WHERE fdd.id_zone = dz.id_zone 
    AND fdd.date BETWEEN @periode_debut AND @periode_fin
)
AND NOT EXISTS (
    SELECT 1 FROM fact_nuitees_pays fnp 
    WHERE fnp.id_zone = dz.id_zone 
    AND fnp.date BETWEEN @periode_debut AND @periode_fin
)
AND NOT EXISTS (
    SELECT 1 FROM fact_diurnes_pays fdp 
    WHERE fdp.id_zone = dz.id_zone 
    AND fdp.date BETWEEN @periode_debut AND @periode_fin
)
ORDER BY zone_utilisateur;
