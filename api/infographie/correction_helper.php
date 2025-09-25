<?php
/**
 * Helper pour la correction des données d'infographie
 * Fournit des fonctions utilitaires pour nettoyer et valider les données
 */

function correctInfographieData($data) {
    if (!is_array($data)) {
        return [];
    }

    $corrected = [];

    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }

        $cleaned = [];
        foreach ($item as $key => $value) {
            if (is_numeric($value)) {
                $cleaned[$key] = (float)$value;
            } elseif (is_string($value)) {
                $cleaned[$key] = trim($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        if (isset($cleaned['nom']) || isset($cleaned['name']) || isset($cleaned['departement']) || isset($cleaned['region']) || isset($cleaned['pays'])) {
            $corrected[] = $cleaned;
        }
    }

    return $corrected;
}

function validateInfographieParams($params) {
    $validated = [];

    $annee = (int)($params['annee'] ?? 2024);
    if ($annee < 2020 || $annee > 2030) {
        $annee = 2024;
    }
    $validated['annee'] = $annee;

    $periode = trim($params['periode'] ?? 'hiver');
    $validPeriods = ['hiver', 'ete', 'printemps', 'automne', 'annee_complete', 'vacances_hiver', 'vacances_ete'];
    if (!in_array($periode, $validPeriods)) {
        $periode = 'hiver';
    }
    $validated['periode'] = $periode;

    $zone = trim($params['zone'] ?? 'CANTAL');
    $validated['zone'] = strtoupper($zone);

    $limit = (int)($params['limit'] ?? 15);
    if ($limit < 1 || $limit > 100) {
        $limit = 15;
    }
    $validated['limit'] = $limit;

    return $validated;
}

function formatInfographieData($data, $type = 'generic') {
    if (!is_array($data)) {
        return [];
    }

    $formatted = [];

    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }

        $formattedItem = [];
        switch ($type) {
            case 'departements':
                $formattedItem['nom'] = $item['nom'] ?? $item['departement'] ?? $item['name'] ?? 'Inconnu';
                $formattedItem['code'] = $item['code'] ?? $item['departement_code'] ?? '';
                break;
            case 'regions':
                $formattedItem['nom'] = $item['nom'] ?? $item['region'] ?? $item['name'] ?? 'Inconnu';
                $formattedItem['code'] = $item['code'] ?? $item['region_code'] ?? '';
                break;
            case 'pays':
                $formattedItem['nom'] = $item['nom'] ?? $item['pays'] ?? $item['name'] ?? 'Inconnu';
                $formattedItem['code'] = $item['code'] ?? $item['pays_code'] ?? '';
                break;
            default:
                $formattedItem['nom'] = $item['nom'] ?? $item['name'] ?? 'Inconnu';
                $formattedItem['code'] = $item['code'] ?? '';
        }

        $numericFields = ['visiteurs', 'touristes', 'excursionnistes', 'duree_sejour', 'revenus'];
        foreach ($numericFields as $field) {
            if (isset($item[$field])) {
                $formattedItem[$field] = (float)$item[$field];
            }
        }

        if (isset($item['annee'])) {
            $formattedItem['annee'] = (int)$item['annee'];
        }
        if (isset($item['periode'])) {
            $formattedItem['periode'] = $item['periode'];
        }

        $formatted[] = $formattedItem;
    }

    return $formatted;
}

function sendInfographieError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');

    $response = [
        'error' => true,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => []
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

function sendInfographieSuccess($data, $metadata = []) {
    header('Content-Type: application/json');

    $response = [
        'error' => false,
        'message' => 'Données récupérées avec succès',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data,
        'count' => count($data)
    ];

    if (!empty($metadata)) {
        $response['metadata'] = $metadata;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
