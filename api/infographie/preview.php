<?php
/**
 * API de prÃ©visualisation d'infographie
 * GÃ¨re le stockage temporaire des images de prÃ©visualisation
 */

require_once dirname(dirname(__DIR__)) . '/config/session_config.php';
require_once dirname(dirname(__DIR__)) . '/classes/Security.php';
require_once dirname(dirname(__DIR__)) . '/classes/SecureUrl.php';

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// VÃ©rification CSRF pour les requÃªtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit;
    }
}

// RÃ©pertoire de stockage temporaire
$previewDir = dirname(dirname(__DIR__)) . '/temp/previews';
if (!is_dir($previewDir)) {
    mkdir($previewDir, 0755, true);
}

// Nettoyer les anciennes prÃ©visualisations (plus de 1 heure)
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
        echo json_encode(['error' => 'MÃ©thode non autorisÃ©e']);
        break;
}

/**
 * CrÃ©er une nouvelle prÃ©visualisation
 */
function handleCreatePreview() {
    global $previewDir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['preview_data']) || !isset($input['unique_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ParamÃ¨tres manquants: preview_data et unique_id requis']);
        return;
    }
    
    $uniqueId = $input['unique_id'];
    $previewData = $input['preview_data'];
    
    // VÃ©rifier que c'est bien une image base64
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $previewData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Format d\'image invalide']);
        return;
    }
    
    // Extraire les donnÃ©es base64
    $base64Data = substr($previewData, strpos($previewData, ',') + 1);
    $imageData = base64_decode($base64Data);
    
    if ($imageData === false) {
        http_response_code(400);
        echo json_encode(['error' => 'DonnÃ©es base64 invalides']);
        return;
    }
    
    // GÃ©nÃ©rer un ID de prÃ©visualisation
    $previewId = 'preview_' . $uniqueId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    $filename = $previewDir . '/' . $previewId . '.png';
    
    // Sauvegarder l'image
    if (file_put_contents($filename, $imageData) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        return;
    }
    
    // Sauvegarder les mÃ©tadonnÃ©es
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
        'message' => 'PrÃ©visualisation crÃ©Ã©e avec succÃ¨s'
    ]);
}

/**
 * RÃ©cupÃ©rer une prÃ©visualisation
 */
function handleGetPreview() {
    global $previewDir;
    
    $previewId = resolveSignedPreviewId();
    
    if (!$previewId) {
        http_response_code(400);
        echo json_encode(['error' => 'Token invalide ou identifiant manquant']);
        return;
    }
    
    $filename = $previewDir . '/' . $previewId . '.png';
    $metadataFile = $previewDir . '/' . $previewId . '.json';
    
    if (!file_exists($filename) || !file_exists($metadataFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'PrÃ©visualisation non trouvÃ©e']);
        return;
    }
    
    // Lire les mÃ©tadonnÃ©es
    $metadata = json_decode(file_get_contents($metadataFile), true);
    
    // VÃ©rifier si la prÃ©visualisation n'est pas trop ancienne (1 heure)
    if (time() - $metadata['created_at'] > 3600) {
        // Supprimer les fichiers expirÃ©s
        unlink($filename);
        unlink($metadataFile);
        http_response_code(404);
        echo json_encode(['error' => 'PrÃ©visualisation expirÃ©e']);
        return;
    }
    
    // Retourner l'image
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=300'); // Cache 5 minutes
    header('X-Preview-ID: ' . $previewId);
    readfile($filename);
}

/**
 * Supprimer une prÃ©visualisation
 */
function handleDeletePreview() {
    global $previewDir;
    
    $previewId = resolveSignedPreviewId();
    
    if (!$previewId) {
        http_response_code(400);
        echo json_encode(['error' => 'Token invalide ou identifiant manquant']);
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
        'message' => 'PrÃ©visualisation supprimÃ©e'
    ]);
}

/**
 * Nettoyer les anciennes prÃ©visualisations
 */
/**
 * Resolve preview identifier by validating the signed token.
 */
function resolveSignedPreviewId(): ?string {
    $path = '/api/infographie/preview.php';
    if (!empty($_GET['token'])) {
        $params = SecureUrl::validateToken($path, $_GET['token']);
        if (is_array($params) && isset($params['id'])) {
            $sanitized = sanitizePreviewId($params['id']);
            if ($sanitized !== null) {
                return $sanitized;
            }
        }
        Security::logSecurityEvent('PREVIEW_TOKEN_INVALID', ['path' => $path], 'HIGH');
        return null;
    }

    if (isset($_GET['id'])) {
        $legacy = sanitizePreviewId($_GET['id']);
        if ($legacy !== null) {
            Security::logSecurityEvent('PREVIEW_LEGACY_ID_FALLBACK', ['id' => $legacy], 'LOW');
            return $legacy;
        }
    }

    return null;
}

function sanitizePreviewId($value): ?string {
    if (!is_string($value)) {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $value)) {
        return null;
    }
    return $value;
}

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





