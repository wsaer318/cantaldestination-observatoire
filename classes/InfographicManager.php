<?php
/**
 * Gestionnaire des infographies partagées pour FluxVision
 * Gère la création, modification, versioning et validation des infographies
 * ACCÈS RÉSERVÉ AUX ADMINISTRATEURS UNIQUEMENT
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/SharedSpaceManager.php';
require_once __DIR__ . '/Auth.php';

class InfographicManager {
    private $db;
    private $spaceManager;
    
    public function __construct() {
        $this->db = getCantalDestinationDatabase();
        $this->spaceManager = new SharedSpaceManager();
    }
    
    /**
     * Vérifier que l'utilisateur est administrateur
     */
    private function requireAdmin() {
        if (!Auth::isAdmin()) {
            throw new Exception('Accès réservé aux administrateurs uniquement');
        }
    }
    
    /**
     * Créer une nouvelle infographie partagée (ADMIN UNIQUEMENT)
     */
    public function createInfographic($spaceId, $title, $description, $ownerId, $infographicData, $tags = []) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            // Vérifier l'accès à l'espace
            if (!$this->spaceManager->getSpace($spaceId, $ownerId)) {
                throw new Exception('Permissions insuffisantes pour créer une infographie');
            }
            
            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            
            // Créer l'infographie
            $stmt = $connection->prepare("
                INSERT INTO shared_infographics (
                    space_id, title, description, owner_id, last_modified_by, 
                    infographic_config, search_tags, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
            ");
            
            $stmt->execute([
                $spaceId,
                $title,
                $description,
                $ownerId,
                $ownerId,
                json_encode($infographicData),
                json_encode($tags)
            ]);
            
            $infographicId = $connection->lastInsertId();
            
            // Créer la première version
            $this->createVersion($infographicId, $ownerId, $infographicData, 1);
            
            // Mettre à jour la version courante
            $stmt = $connection->prepare("
                UPDATE shared_infographics 
                SET current_version_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$connection->lastInsertId(), $infographicId]);
            
            // Logger l'activité
            $this->spaceManager->logActivity($spaceId, $ownerId, 'infographic.created', 'infographic', $infographicId, [
                'title' => $title,
                'version' => 1
            ]);
            
            $connection->commit();
            return $infographicId;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur création infographie: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Récupérer les infographies d'un espace (ADMIN UNIQUEMENT)
     */
    public function getSpaceInfographics($spaceId, $userId, $filters = []) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            // Vérifier l'accès à l'espace
            if (!$this->spaceManager->getSpace($spaceId, $userId)) {
                throw new Exception('Accès refusé à l\'espace');
            }
            
            $connection = $this->db->getConnection();
            
            $whereConditions = ['si.space_id = ?'];
            $params = [$spaceId];
            
            // Filtres
            if (!empty($filters['status'])) {
                $whereConditions[] = 'si.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['owner_id'])) {
                $whereConditions[] = 'si.owner_id = ?';
                $params[] = $filters['owner_id'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = '(si.title LIKE ? OR si.description LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $stmt = $connection->prepare("
                SELECT 
                    si.*,
                    u.username as owner_name,
                    iv.version_number as current_version,
                    iv.created_at as last_modified
                FROM shared_infographics si
                LEFT JOIN users u ON si.owner_id = u.id
                LEFT JOIN infographic_versions iv ON si.current_version_id = iv.id
                WHERE $whereClause
                ORDER BY si.last_modified_at DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erreur récupération infographies: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupérer une infographie spécifique (ADMIN UNIQUEMENT)
     */
    public function getInfographic($infographicId, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT 
                    si.*,
                    u.username as owner_name,
                    s.name as space_name,
                    iv.version_number as current_version,
                    iv.storage_data as current_data
                FROM shared_infographics si
                LEFT JOIN users u ON si.owner_id = u.id
                LEFT JOIN shared_spaces s ON si.space_id = s.id
                LEFT JOIN infographic_versions iv ON si.current_version_id = iv.id
                WHERE si.id = ?
            ");
            
            $stmt->execute([$infographicId]);
            $infographic = $stmt->fetch();
            
            if (!$infographic) {
                return null;
            }
            
            // Vérifier l'accès à l'espace
            if (!$this->spaceManager->getSpace($infographic['space_id'], $userId)) {
                throw new Exception('Accès refusé à l\'infographie');
            }
            
            return $infographic;
            
        } catch (Exception $e) {
            error_log("Erreur récupération infographie: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Créer une nouvelle version d'une infographie (ADMIN UNIQUEMENT)
     */
    public function createVersion($infographicId, $userId, $infographicData, $versionNumber = null) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            // Si pas de numéro de version spécifié, calculer le suivant
            if ($versionNumber === null) {
                $stmt = $connection->prepare("
                    SELECT MAX(version_number) as max_version 
                    FROM infographic_versions 
                    WHERE infographic_id = ?
                ");
                $stmt->execute([$infographicId]);
                $result = $stmt->fetch();
                $versionNumber = ($result['max_version'] ?? 0) + 1;
            }
            
            $stmt = $connection->prepare("
                INSERT INTO infographic_versions (
                    infographic_id, version_number, storage_data, created_by
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $infographicId,
                $versionNumber,
                json_encode($infographicData),
                $userId
            ]);
            
            $versionId = $connection->lastInsertId();
            
            // Mettre à jour la version courante de l'infographie
            $stmt = $connection->prepare("
                UPDATE shared_infographics 
                SET current_version_id = ?, last_modified_by = ?, last_modified_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$versionId, $userId, $infographicId]);
            
            return $versionId;
            
        } catch (Exception $e) {
            error_log("Erreur création version: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Changer le statut d'une infographie (ADMIN UNIQUEMENT)
     */
    public function updateStatus($infographicId, $userId, $newStatus, $note = null) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            // Récupérer l'infographie
            $infographic = $this->getInfographic($infographicId, $userId);
            if (!$infographic) {
                throw new Exception('Infographie non trouvée');
            }
            
            $connection->beginTransaction();
            
            // Mettre à jour le statut
            $stmt = $connection->prepare("
                UPDATE shared_infographics 
                SET status = ?, last_modified_by = ?, last_modified_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $userId, $infographicId]);
            
            // Si validation, marquer la version comme validée
            if ($newStatus === 'validated') {
                $stmt = $connection->prepare("
                    UPDATE infographic_versions 
                    SET is_validated = 1, approved_by = ?, approved_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $infographic['current_version_id']]);
            }
            
            // Logger l'activité
            $this->spaceManager->logActivity($infographic['space_id'], $userId, 'infographic.status_changed', 'infographic', $infographicId, [
                'old_status' => $infographic['status'],
                'new_status' => $newStatus,
                'note' => $note
            ]);
            
            $connection->commit();
            return true;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur changement statut: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ajouter un commentaire à une infographie (ADMIN UNIQUEMENT)
     */
    public function addComment($infographicId, $userId, $anchor, $body, $versionId = null) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            // Vérifier l'accès à l'infographie
            $infographic = $this->getInfographic($infographicId, $userId);
            if (!$infographic) {
                throw new Exception('Accès refusé à l\'infographie');
            }
            
            $connection = $this->db->getConnection();
            
            $stmt = $connection->prepare("
                INSERT INTO infographic_comments (
                    infographic_id, version_id, anchor, body, author_id
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $infographicId,
                $versionId,
                json_encode($anchor),
                $body,
                $userId
            ]);
            
            $commentId = $connection->lastInsertId();
            
            // Logger l'activité
            $this->spaceManager->logActivity($infographic['space_id'], $userId, 'comment.created', 'comment', $commentId, [
                'infographic_id' => $infographicId,
                'anchor_type' => $anchor['type'] ?? 'unknown'
            ]);
            
            return $commentId;
            
        } catch (Exception $e) {
            error_log("Erreur ajout commentaire: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Récupérer les commentaires d'une infographie (ADMIN UNIQUEMENT)
     */
    public function getComments($infographicId, $userId, $filters = []) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            // Vérifier l'accès à l'infographie
            $infographic = $this->getInfographic($infographicId, $userId);
            if (!$infographic) {
                throw new Exception('Accès refusé à l\'infographie');
            }
            
            $connection = $this->db->getConnection();
            
            $whereConditions = ['ic.infographic_id = ?'];
            $params = [$infographicId];
            
            // Filtres
            if (isset($filters['resolved'])) {
                $whereConditions[] = 'ic.resolved = ?';
                $params[] = $filters['resolved'] ? 1 : 0;
            }
            
            if (!empty($filters['version_id'])) {
                $whereConditions[] = 'ic.version_id = ?';
                $params[] = $filters['version_id'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $stmt = $connection->prepare("
                SELECT 
                    ic.*,
                    u.username as author_name
                FROM infographic_comments ic
                LEFT JOIN users u ON ic.author_id = u.id
                WHERE $whereClause
                ORDER BY ic.created_at ASC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erreur récupération commentaires: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marquer un commentaire comme résolu (ADMIN UNIQUEMENT)
     */
    public function resolveComment($commentId, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            // Récupérer le commentaire
            $stmt = $connection->prepare("
                SELECT ic.*, si.space_id 
                FROM infographic_comments ic
                JOIN shared_infographics si ON ic.infographic_id = si.id
                WHERE ic.id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                throw new Exception('Commentaire non trouvé');
            }
            
            // Vérifier l'accès à l'infographie
            if (!$this->spaceManager->getSpace($comment['space_id'], $userId)) {
                throw new Exception('Accès refusé au commentaire');
            }
            
            // Marquer comme résolu
            $stmt = $connection->prepare("
                UPDATE infographic_comments 
                SET resolved = 1, resolved_by = ?, resolved_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$userId, $commentId]);
            
            // Logger l'activité
            $this->spaceManager->logActivity($comment['space_id'], $userId, 'comment.resolved', 'comment', $commentId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur résolution commentaire: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Supprimer une infographie (ADMIN UNIQUEMENT)
     */
    public function deleteInfographic($infographicId, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $infographic = $this->getInfographic($infographicId, $userId);
            if (!$infographic) {
                throw new Exception('Infographie non trouvée');
            }
            
            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            
            // Supprimer les commentaires
            $stmt = $connection->prepare("DELETE FROM infographic_comments WHERE infographic_id = ?");
            $stmt->execute([$infographicId]);
            
            // Supprimer les versions
            $stmt = $connection->prepare("DELETE FROM infographic_versions WHERE infographic_id = ?");
            $stmt->execute([$infographicId]);
            
            // Supprimer l'infographie
            $stmt = $connection->prepare("DELETE FROM shared_infographics WHERE id = ?");
            $stmt->execute([$infographicId]);
            
            // Logger l'activité
            $this->spaceManager->logActivity($infographic['space_id'], $userId, 'infographic.deleted', 'infographic', $infographicId, [
                'title' => $infographic['title']
            ]);
            
            $connection->commit();
            return true;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur suppression infographie: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtenir le rôle requis pour un changement de statut
     */
    private function getRequiredRoleForStatus($status) {
        $roleMap = [
            'draft' => 'editor',
            'in_progress' => 'editor',
            'to_validate' => 'editor',
            'validated' => 'validator',
            'archived' => 'validator'
        ];
        
        return $roleMap[$status] ?? 'admin';
    }
    
    /**
     * Récupérer les statistiques d'infographies d'un espace (ADMIN UNIQUEMENT)
     */
    public function getSpaceInfographicStats($spaceId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            // Compter par statut
            $stmt = $connection->prepare("
                SELECT status, COUNT(*) as count
                FROM shared_infographics
                WHERE space_id = ?
                GROUP BY status
            ");
            $stmt->execute([$spaceId]);
            $statusCounts = $stmt->fetchAll();
            
            // Compter les commentaires
            $stmt = $connection->prepare("
                SELECT COUNT(*) as total_comments,
                       SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) as resolved_comments
                FROM infographic_comments ic
                JOIN shared_infographics si ON ic.infographic_id = si.id
                WHERE si.space_id = ?
            ");
            $stmt->execute([$spaceId]);
            $commentStats = $stmt->fetch();
            
            return [
                'status_counts' => $statusCounts,
                'total_comments' => $commentStats['total_comments'] ?? 0,
                'resolved_comments' => $commentStats['resolved_comments'] ?? 0
            ];
            
        } catch (Exception $e) {
            error_log("Erreur statistiques infographies: " . $e->getMessage());
            return [
                'status_counts' => [],
                'total_comments' => 0,
                'resolved_comments' => 0
            ];
        }
    }
}
?>
