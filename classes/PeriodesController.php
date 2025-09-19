<?php

class PeriodesController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Récupère toutes les périodes avec tri intelligent
     */
    public function getAllPeriodes() {
        try {
            $query = "SELECT * FROM dim_periodes ORDER BY annee DESC, date_debut ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des périodes : " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les périodes groupées par code_periode
     */
    public function getPeriodesGroupees() {
        try {
            $query = "SELECT 
                        code_periode,
                        nom_periode,
                        COUNT(*) as nb_annees,
                        MIN(annee) as premiere_annee,
                        MAX(annee) as derniere_annee,
                        GROUP_CONCAT(DISTINCT annee ORDER BY annee DESC) as annees_disponibles,
                        MIN(date_debut) as plus_ancienne_date,
                        MAX(date_fin) as plus_recente_date
                      FROM dim_periodes 
                      GROUP BY code_periode, nom_periode 
                      ORDER BY code_periode";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convertir les années disponibles en tableau
            foreach ($groupes as &$groupe) {
                $groupe['annees_disponibles'] = explode(',', $groupe['annees_disponibles']);
            }
            
            return $groupes;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des périodes groupées : " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère toutes les instances d'une période par son code
     */
    public function getPeriodesByCode($codePeriode) {
        try {
            $query = "SELECT * FROM dim_periodes WHERE code_periode = ? ORDER BY annee DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$codePeriode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des périodes par code : " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère une période par son ID
     */
    public function getPeriodeById($id) {
        try {
            $query = "SELECT * FROM dim_periodes WHERE id_periode = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de la période : " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crée une nouvelle période
     */
    public function createPeriode($data) {
        try {
            // Validation des données
            $validation = $this->validatePeriodeData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['message']];
            }
            
            // Vérification de l'unicité
            if ($this->periodeExists($data['code_periode'], $data['annee'])) {
                return ['success' => false, 'error' => 'Une période avec ce code existe déjà pour cette année'];
            }
            
            // Vérification des chevauchements de dates - DÉSACTIVÉE pour permettre les périodes englobantes
            // if ($this->hasDateOverlap($data['date_debut'], $data['date_fin'], $data['annee'])) {
            //     return ['success' => false, 'error' => 'Cette période chevauche avec une période existante'];
            // }
            
            $query = "INSERT INTO dim_periodes (code_periode, nom_periode, annee, date_debut, date_fin) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['code_periode'],
                $data['nom_periode'],
                $data['annee'],
                $data['date_debut'],
                $data['date_fin']
            ]);
            
            return ['success' => true, 'message' => 'Période créée avec succès'];
            
        } catch (Exception $e) {
            error_log("Erreur lors de la création de la période : " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création de la période'];
        }
    }
    
    /**
     * Met à jour une période existante
     */
    public function updatePeriode($id, $data) {
        try {
            // Validation des données
            $validation = $this->validatePeriodeData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['message']];
            }
            
            // Vérification que la période existe
            $existingPeriode = $this->getPeriodeById($id);
            if (!$existingPeriode) {
                return ['success' => false, 'error' => 'Période non trouvée'];
            }
            
            // Vérification de l'unicité (exclure la période actuelle)
            if ($this->periodeExists($data['code_periode'], $data['annee'], $id)) {
                return ['success' => false, 'error' => 'Une autre période avec ce code existe déjà pour cette année'];
            }
            
            // Vérification des chevauchements de dates - DÉSACTIVÉE pour permettre les périodes englobantes
            // if ($this->hasDateOverlap($data['date_debut'], $data['date_fin'], $data['annee'], $id)) {
            //     return ['success' => false, 'error' => 'Cette période chevauche avec une période existante'];
            // }
            
            $query = "UPDATE dim_periodes 
                     SET code_periode = ?, nom_periode = ?, annee = ?, date_debut = ?, date_fin = ?
                     WHERE id_periode = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['code_periode'],
                $data['nom_periode'],
                $data['annee'],
                $data['date_debut'],
                $data['date_fin'],
                $id
            ]);
            
            return ['success' => true, 'message' => 'Période mise à jour avec succès'];
            
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour de la période : " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la mise à jour de la période'];
        }
    }
    
    /**
     * Supprime une période
     */
    public function deletePeriode($id) {
        try {
            // Vérifier si la période est utilisée dans d'autres tables
            if ($this->isPeriodeInUse($id)) {
                return ['success' => false, 'error' => 'Cette période ne peut pas être supprimée car elle est utilisée dans d\'autres données'];
            }
            
            $query = "DELETE FROM dim_periodes WHERE id_periode = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Période supprimée avec succès'];
            } else {
                return ['success' => false, 'error' => 'Période non trouvée'];
            }
            
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression de la période : " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la suppression de la période'];
        }
    }

    /**
     * Supprime un groupe entier de périodes (toutes les années d'un code_periode)
     */
    public function deleteGroupe($codePeriode) {
        try {
            // Vérifier si le groupe existe
            $periodes = $this->getPeriodesByCode($codePeriode);
            if (empty($periodes)) {
                return ['success' => false, 'error' => 'Groupe de périodes non trouvé'];
            }
            
            // Vérifier si une des périodes du groupe est utilisée
            foreach ($periodes as $periode) {
                if ($this->isPeriodeInUse($periode['id_periode'])) {
                    return ['success' => false, 'error' => 'Ce groupe ne peut pas être supprimé car certaines périodes sont utilisées dans d\'autres données'];
                }
            }
            
            // Supprimer toutes les périodes du groupe
            $query = "DELETE FROM dim_periodes WHERE code_periode = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$codePeriode]);
            
            $nbSupprimees = $stmt->rowCount();
            if ($nbSupprimees > 0) {
                return ['success' => true, 'message' => "Groupe supprimé avec succès ($nbSupprimees période(s) supprimée(s))"];
            } else {
                return ['success' => false, 'error' => 'Aucune période trouvée pour ce groupe'];
            }
            
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du groupe : " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la suppression du groupe'];
        }
    }
    
    /**
     * Valide les données d'une période
     */
    private function validatePeriodeData($data) {
        if (empty($data['code_periode'])) {
            return ['valid' => false, 'message' => 'Le code de la période est requis'];
        }
        
        if (empty($data['nom_periode'])) {
            return ['valid' => false, 'message' => 'Le nom de la période est requis'];
        }
        
        if (empty($data['annee']) || !is_numeric($data['annee'])) {
            return ['valid' => false, 'message' => 'L\'année doit être un nombre valide'];
        }
        
        if (empty($data['date_debut']) || empty($data['date_fin'])) {
            return ['valid' => false, 'message' => 'Les dates de début et de fin sont requises'];
        }
        
        // Validation des dates
        $dateDebut = DateTime::createFromFormat('Y-m-d', $data['date_debut']);
        $dateFin = DateTime::createFromFormat('Y-m-d', $data['date_fin']);
        
        if (!$dateDebut || !$dateFin) {
            return ['valid' => false, 'message' => 'Format de date invalide'];
        }
        
        if ($dateDebut >= $dateFin) {
            return ['valid' => false, 'message' => 'La date de début doit être antérieure à la date de fin'];
        }
        
        // Validation que les dates correspondent à l'année
        if ($dateDebut->format('Y') != $data['annee'] && $dateFin->format('Y') != $data['annee']) {
            return ['valid' => false, 'message' => 'Les dates doivent correspondre à l\'année spécifiée ou la chevaucher'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Vérifie si une période avec le même code existe pour une année donnée
     */
    private function periodeExists($codePeriode, $annee, $excludeId = null) {
        try {
            $query = "SELECT COUNT(*) FROM dim_periodes WHERE code_periode = ? AND annee = ?";
            $params = [$codePeriode, $annee];
            
            if ($excludeId) {
                $query .= " AND id_periode != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Vérifie s'il y a chevauchement de dates avec d'autres périodes
     */
    private function hasDateOverlap($dateDebut, $dateFin, $annee, $excludeId = null) {
        try {
            $query = "SELECT COUNT(*) FROM dim_periodes 
                     WHERE annee = ? 
                     AND (
                         (date_debut <= ? AND date_fin >= ?) OR
                         (date_debut <= ? AND date_fin >= ?) OR
                         (date_debut >= ? AND date_debut <= ?)
                     )";
            $params = [$annee, $dateDebut, $dateDebut, $dateFin, $dateFin, $dateDebut, $dateFin];
            
            if ($excludeId) {
                $query .= " AND id_periode != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Vérifie si une période est utilisée dans d'autres tables
     */
    private function isPeriodeInUse($id) {
        try {
            // Vous pouvez ajouter ici les vérifications pour d'autres tables
            // qui référencent les périodes (comme fact_frequentation, etc.)
            
            // Exemple de vérification (à adapter selon votre schéma de base de données)
            $tables = [
                'fact_frequentation' => 'id_periode',
                // Ajouter d'autres tables si nécessaire
            ];
            
            foreach ($tables as $table => $column) {
                $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            // En cas d'erreur, on considère que la période est utilisée pour la sécurité
            return true;
        }
    }
    
    /**
     * Récupère les statistiques des périodes
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Nombre total de périodes
            $query = "SELECT COUNT(*) as total FROM dim_periodes";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['total'] = $stmt->fetchColumn();
            
            // Périodes par année
            $query = "SELECT annee, COUNT(*) as count FROM dim_periodes GROUP BY annee ORDER BY annee DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['par_annee'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Codes de périodes les plus utilisés
            $query = "SELECT code_periode, COUNT(*) as count FROM dim_periodes GROUP BY code_periode ORDER BY count DESC LIMIT 5";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['codes_populaires'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des statistiques : " . $e->getMessage());
            return [
                'total' => 0,
                'par_annee' => [],
                'codes_populaires' => []
            ];
        }
    }
    
    /**
     * Duplique une période vers une nouvelle année
     */
    public function duplicatePeriode($id, $nouvelleAnnee) {
        try {
            $periode = $this->getPeriodeById($id);
            if (!$periode) {
                return ['success' => false, 'error' => 'Période non trouvée'];
            }
            
            // Vérifier que la période n'existe pas déjà pour cette année
            if ($this->periodeExists($periode['code_periode'], $nouvelleAnnee)) {
                return ['success' => false, 'error' => 'Une période avec ce code existe déjà pour l\'année ' . $nouvelleAnnee];
            }
            
            // Calculer les nouvelles dates (même période relative dans la nouvelle année)
            $ancienneAnnee = $periode['annee'];
            $dateDebut = new DateTime($periode['date_debut']);
            $dateFin = new DateTime($periode['date_fin']);
            
            // Calculer le décalage d'années
            $decalage = $nouvelleAnnee - $ancienneAnnee;
            $dateDebut->modify("+{$decalage} years");
            $dateFin->modify("+{$decalage} years");
            
            $nouvellePeriode = [
                'code_periode' => $periode['code_periode'],
                'nom_periode' => $periode['nom_periode'],
                'annee' => $nouvelleAnnee,
                'date_debut' => $dateDebut->format('Y-m-d'),
                'date_fin' => $dateFin->format('Y-m-d')
            ];
            
            return $this->createPeriode($nouvellePeriode);
            
        } catch (Exception $e) {
            error_log("Erreur lors de la duplication de la période : " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la duplication de la période'];
        }
    }
} 