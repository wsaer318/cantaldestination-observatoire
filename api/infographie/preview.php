<?php
/**
 * API de prévisualisation d'infographie
 * Gère le stockage temporaire des images de prévisualisation
 */

require_once dirname(dirname(__DIR__)) . '/config/session_config.php';
require_once dirname(dirname(__DIR__)) . '/classes/Security.php';

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Vérification CSRF pour les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit;
    }
}

// Répertoire de stockage temporaire
$previewDir = dirname(dirname(__DIR__)) . '/temp/previews';
if (!is_dir($previewDir)) {
    mkdir($previewDir, 0755, true);
}

// Nettoyer les anciennes prévisualisations (plus de 1 heure)
cleanupOldPreviews($previewDir);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        handleCreatePreview();
        break;
    case 'GET':
        handleGetPreview();
        break;
    case 'DELETE':
        handleDeletePreview();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        break;
}

/**
 * Créer une nouvelle prévisualisation
 */
function handleCreatePreview() {
    global $previewDir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['preview_data']) || !isset($input['unique_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants: preview_data et unique_id requis']);
        return;
    }
    
    $uniqueId = $input['unique_id'];
    $previewData = $input['preview_data'];
    
    // Vérifier que c'est bien une image base64
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $previewData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Format d\'image invalide']);
        return;
    }
    
    // Extraire les données base64
    $base64Data = substr($previewData, strpos($previewData, ',') + 1);
    $imageData = base64_decode($base64Data);
    
    if ($imageData === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Données base64 invalides']);
        return;
    }
    
    // Générer un ID de prévisualisation
    $previewId = 'preview_' . $uniqueId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    $filename = $previewDir . '/' . $previewId . '.png';
    
    // Sauvegarder l'image
    if (file_put_contents($filename, $imageData) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        return;
    }
    
    // Sauvegarder les métadonnées
    $metadata = [
        'preview_id' => $previewId,
        'unique_id' => $uniqueId,
        'created_at' => time(),
        'file_size' => strlen($imageData),
        'filename' => $filename
    ];
    
    $metadataFile = $previewDir . '/' . $previewId . '.json';
    file_put_contents($metadataFile, json_encode($metadata));
    
    echo json_encode([
        'success' => true,
        'preview_id' => $previewId,
        'message' => 'Prévisualisation créée avec succès'
    ]);
}

/**
 * Récupérer une prévisualisation
 */
function handleGetPreview() {
    global $previewDir;
    
    $previewId = $_GET['id'] ?? null;
    
    if (!$previewId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de prévisualisation requis']);
        return;
    }
    
    $filename = $previewDir . '/' . $previewId . '.png';
    $metadataFile = $previewDir . '/' . $previewId . '.json';
    
    if (!file_exists($filename) || !file_exists($metadataFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Prévisualisation non trouvée']);
        return;
    }
    
    // Lire les métadonnées
    $metadata = json_decode(file_get_contents($metadataFile), true);
    
    // Vérifier si la prévisualisation n'est pas trop ancienne (1 heure)
    if (time() - $metadata['created_at'] > 3600) {
        // Supprimer les fichiers expirés
        unlink($filename);
        unlink($metadataFile);
        http_response_code(404);
        echo json_encode(['error' => 'Prévisualisation expirée']);
        return;
    }
    
    // Retourner l'image
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=300'); // Cache 5 minutes
    header('X-Preview-ID: ' . $previewId);
    readfile($filename);
}

/**
 * Supprimer une prévisualisation
 */
function handleDeletePreview() {
    global $previewDir;
    
    $previewId = $_GET['id'] ?? null;
    
    if (!$previewId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de prévisualisation requis']);
        return;
    }
    
    $filename = $previewDir . '/' . $previewId . '.png';
    $metadataFile = $previewDir . '/' . $previewId . '.json';
    
    $deleted = 0;
    if (file_exists($filename)) {
        unlink($filename);
        $deleted++;
    }
    if (file_exists($metadataFile)) {
        unlink($metadataFile);
        $deleted++;
    }
    
    echo json_encode([
        'success' => true,
        'deleted_files' => $deleted,
        'message' => 'Prévisualisation supprimée'
    ]);
}

/**
 * Nettoyer les anciennes prévisualisations
 */
function cleanupOldPreviews($dir) {
    $files = glob($dir . '/*.json');
    $currentTime = time();
    
    foreach ($files as $file) {
        $metadata = json_decode(file_get_contents($file), true);
        if ($metadata && isset($metadata['created_at'])) {
            // Supprimer si plus d'1 heure
            if ($currentTime - $metadata['created_at'] > 3600) {
                $previewId = $metadata['preview_id'];
                $pngFile = $dir . '/' . $previewId . '.png';
                
                if (file_exists($pngFile)) {
                    unlink($pngFile);
                }
                unlink($file);
            }
        }
    }
}
