<?php

class PeriodManager {
    
    private $periodesData;
    
    public function __construct() {
        $this->loadPeriodesData();
    }
    
    /**
     * Charge les données de périodes depuis le fichier JSON
     */
    private function loadPeriodesData() {
        $periodePath = STATIC_PATH . '/data/periode.json';
        
        if (!file_exists($periodePath)) {
            throw new Exception("Fichier periode.json non trouvé : " . $periodePath);
        }
        
        $jsonContent = file_get_contents($periodePath);
        $this->periodesData = json_decode($jsonContent, true);
        
        if ($this->periodesData === null) {
            throw new Exception("Erreur lors du parsing du fichier periode.json");
        }
    }
    
    /**
     * Récupère la période de vacances pour l'année et la période données
     * Équivalent de la fonction get_periode Python
     * 
     * @param string $annee
     * @param string $periodeNom
     * @return array ['debut' => 'YYYY-MM-DD', 'fin' => 'YYYY-MM-DD']
     */
    public function getPeriode($annee, $periodeNom) {
        if (!isset($this->periodesData['periode'][$periodeNom][$annee])) {
            throw new Exception("Période non trouvée : {$periodeNom} {$annee}");
        }
        
        $periode = $this->periodesData['periode'][$periodeNom][$annee];
        
        // Conversion des dates du format DD/MM/YYYY vers YYYY-MM-DD
        $debut = DateTime::createFromFormat('d/m/Y', $periode['debut']);
        $fin = DateTime::createFromFormat('d/m/Y', $periode['fin']);
        
        if (!$debut || !$fin) {
            throw new Exception("Format de date invalide pour la période {$periodeNom} {$annee}");
        }
        
        return [
            'debut' => $debut->format('Y-m-d'),
            'fin' => $fin->format('Y-m-d')
        ];
    }
    
    /**
     * Récupère toutes les zones d'étude disponibles
     * 
     * @return array
     */
    public function getZones() {
        return $this->periodesData['zonne_etude'] ?? [];
    }
    
    /**
     * Récupère toutes les périodes disponibles
     * 
     * @return array
     */
    public function getPeriodes() {
        return array_keys($this->periodesData['periode'] ?? []);
    }
    
    /**
     * Récupère toutes les années disponibles pour une période donnée
     * 
     * @param string $periode
     * @return array
     */
    public function getAnnees($periode = null) {
        $periodes = $this->getPeriodes();
        
        if (empty($periodes)) {
            return [];
        }
        
        $periode = $periode ?? $periodes[0];
        
        if (!isset($this->periodesData['periode'][$periode])) {
            return [];
        }
        
        $annees = array_keys($this->periodesData['periode'][$periode]);
        
        // Tri décroissant (plus récent en premier)
        rsort($annees);
        
        return $annees;
    }
    
    /**
     * Retourne toutes les informations de filtres pour l'API
     * 
     * @return array
     */
    public function getFiltersData() {
        return [
            'zones' => $this->getZones(),
            'periodes' => $this->getPeriodes(),
            'annees' => $this->getAnnees()
        ];
    }
} 