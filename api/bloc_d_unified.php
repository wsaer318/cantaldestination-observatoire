<?php
/**
 * API unifiée pour tous les blocs D - Redirection vers l'API avancée
 * Gère: bloc_d1, bloc_d2, bloc_d3, bloc_d5, bloc_d6, bloc_d7, bloc_d1_exc, bloc_d2_exc, etc.
 */

require_once __DIR__ . '/security_middleware.php';

// Extraire les paramètres
$zone = $_GET['zone'] ?? '';
$annee = $_GET['annee'] ?? '';
$periode = $_GET['periode'] ?? '';

if (empty($zone) || empty($annee) || empty($periode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants: zone, annee et periode sont requis']);
    exit;
}

try {
    // Déterminer quel bloc est demandé à partir de l'URL
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $urlParts = parse_url($requestUri);
    $path = $urlParts['path'] ?? '';
    
    // Extraire le nom du bloc depuis l'URL (ex: /api/bloc_d1 -> bloc_d1)
    $blocName = '';
    if (preg_match('/\/api\/(bloc_d\w+)/', $path, $matches)) {
        $blocName = $matches[1];
    }
    
    // Mapper le nom du bloc vers le paramètre attendu par l'API avancée
    $blocParam = '';
    $isExc = false;
    
    switch ($blocName) {
        case 'bloc_d1':
            $blocParam = 'd1';
            break;
        case 'bloc_d1_exc':
            $blocParam = 'd1_exc';
            $isExc = true;
            break;
        case 'bloc_d2':
            $blocParam = 'd2';
            break;
        case 'bloc_d2_exc':
            $blocParam = 'd2_exc';
            $isExc = true;
            break;
        case 'bloc_d3':
            $blocParam = 'd3';
            break;
        case 'bloc_d3_exc':
            $blocParam = 'd3_exc';
            $isExc = true;
            break;
        case 'bloc_d5':
            $blocParam = 'd5';
            break;
        case 'bloc_d5_exc':
            $blocParam = 'd5_exc';
            $isExc = true;
            break;
        case 'bloc_d6':
            $blocParam = 'd6';
            break;
        case 'bloc_d6_exc':
            $blocParam = 'd6_exc';
            $isExc = true;
            break;
        case 'bloc_d7':
            $blocParam = 'd7';
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => "Bloc '$blocName' non supporté"]);
            exit;
    }
    
    // Rediriger vers les APIs simples qui sont plus fiables
    $targetFile = '';
    $formatResult = true;
    
    switch ($blocName) {
        case 'bloc_d1':
            $targetFile = 'bloc_d1_simple.php';
            break;
        case 'bloc_d2':
            $targetFile = 'bloc_d2_simple.php';
            break;
        case 'bloc_d3':
            $targetFile = 'bloc_d3_simple.php';
            break;
        case 'bloc_d5':
            $targetFile = 'bloc_d5_simple.php';
            break;
        case 'bloc_d6':
            $targetFile = 'bloc_d6_simple.php';
            break;
        case 'bloc_d7':
            $targetFile = 'bloc_d7_simple.php';
            break;
        // Pour les excursionnistes, utiliser l'API avancée si les fichiers simples n'existent pas
        default:
            // Fallback vers l'API avancée pour les excursionnistes
            $_GET['zone'] = $zone;
            $_GET['annee'] = $annee; 
            $_GET['periode'] = $periode;
            $_GET['bloc'] = $blocParam;
            
            ob_start();
            include_once 'bloc_d_advanced_mysql.php';
            $fullOutput = ob_get_clean();
            
            $fullData = json_decode($fullOutput, true);
            
            if (!$fullData) {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur récupération données avancées']);
                exit;
            }
            
            header('Content-Type: application/json');
            echo json_encode($fullData);
            return;
    }
    
    // Appeler l'API simple
    if ($targetFile && file_exists(__DIR__ . '/' . $targetFile)) {
        $_GET['zone'] = $zone;
        $_GET['annee'] = $annee; 
        $_GET['periode'] = $periode;
        
        ob_start();
        include_once $targetFile;
        $simpleOutput = ob_get_clean();
        
        $simpleData = json_decode($simpleOutput, true);
        
        if ($simpleData && !isset($simpleData['error'])) {
            // Formater dans le même format que l'API avancée
            $response = [
                'zone_observation' => $zone,
                'annee' => $annee,
                'periode' => $periode,
                $blocName => $simpleData
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            // Si l'API simple échoue, passer au fallback avancé
            $_GET['bloc'] = $blocParam;
            
            ob_start();
            include_once 'bloc_d_advanced_mysql.php';
            $fullOutput = ob_get_clean();
            
            $fullData = json_decode($fullOutput, true);
            
            header('Content-Type: application/json');
            echo json_encode($fullData ?: ['error' => 'Toutes les APIs ont échoué']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => "API '$targetFile' non trouvée"]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?> 