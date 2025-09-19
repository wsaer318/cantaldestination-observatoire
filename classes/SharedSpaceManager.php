<?php
/**
 * Gestionnaire des espaces partagés pour les infographies collaboratives
 * Gère la création, modification et suppression des espaces de travail
 * ACCÈS RÉSERVÉ AUX ADMINISTRATEURS UNIQUEMENT
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/UserDataManager.php';
require_once __DIR__ . '/EncryptionManager.php';
require_once __DIR__ . '/Auth.php';

class SharedSpaceManager {
    private $db;
    
    public function __construct() {
        $this->db = getCantalDestinationDatabase();
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
     * Créer un nouvel espace partagé (ADMIN UNIQUEMENT)
     */
    public function createSpace($name, $description, $createdBy, $initialMembers = []) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            
            // Créer l'espace
            $stmt = $connection->prepare("
                INSERT INTO shared_spaces (name, description, created_by) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $createdBy]);
            $spaceId = $connection->lastInsertId();
            
            // Ajouter le créateur comme admin
            $this->addMember($spaceId, $createdBy, 'admin');
            
            // Ajouter les membres initiaux
            foreach ($initialMembers as $member) {
                $this->addMember($spaceId, $member['user_id'], $member['role'] ?? 'reader');
            }
            
            // Logger l'activité
            $this->logActivity($spaceId, $createdBy, 'space.created', 'space', $spaceId, [
                'name' => $name,
                'member_count' => count($initialMembers) + 1
            ]);
            
            $connection->commit();
            return $spaceId;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur création espace: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Récupérer tous les espaces d'un utilisateur (ADMIN UNIQUEMENT)
     * @param int $userId ID de l'utilisateur
     * @param bool $includeInactive Inclure les espaces désactivés (admin seulement)
     */
    public function getUserSpaces($userId, $includeInactive = false) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            if ($includeInactive) {
                // Inclure tous les espaces (actifs et désactivés)
                $stmt = $connection->prepare("
                    SELECT s.*, sm.role as user_role, sm.joined_at
                    FROM shared_spaces s
                    JOIN space_memberships sm ON s.id = sm.space_id
                    WHERE sm.user_id = ?
                    ORDER BY s.is_active DESC, s.updated_at DESC
                ");
                $stmt->execute([$userId]);
            } else {
                // Espaces actifs seulement
                $stmt = $connection->prepare("
                    SELECT s.*, sm.role as user_role, sm.joined_at
                    FROM shared_spaces s
                    JOIN space_memberships sm ON s.id = sm.space_id
                    WHERE sm.user_id = ? AND s.is_active = 1
                    ORDER BY s.updated_at DESC
                ");
                $stmt->execute([$userId]);
            }
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erreur récupération espaces: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupérer un espace par ID avec vérification d'accès (ADMIN UNIQUEMENT)
     */
    public function getSpace($spaceId, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                SELECT s.*, sm.role as user_role
                FROM shared_spaces s
                JOIN space_memberships sm ON s.id = sm.space_id
                WHERE s.id = ? AND sm.user_id = ? AND s.is_active = 1
            ");
            $stmt->execute([$spaceId, $userId]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Erreur récupération espace: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ajouter un membre à un espace (ADMIN UNIQUEMENT)
     */
    public function addMember($spaceId, $userId, $role = 'reader') {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                INSERT INTO space_memberships (user_id, space_id, role) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role = ?
            ");
            $stmt->execute([$userId, $spaceId, $role, $role]);
            
            // Logger l'activité
            $this->logActivity($spaceId, $userId, 'member.added', 'space', $spaceId, [
                'user_id' => $userId,
                'role' => $role
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur ajout membre: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprimer un membre d'un espace (ADMIN UNIQUEMENT)
     */
    public function removeMember($spaceId, $userId, $removedBy) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                DELETE FROM space_memberships 
                WHERE space_id = ? AND user_id = ?
            ");
            $stmt->execute([$spaceId, $userId]);
            
            // Logger l'activité
            $this->logActivity($spaceId, $removedBy, 'member.removed', 'space', $spaceId, [
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur suppression membre: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupérer tous les membres d'un espace (ADMIN UNIQUEMENT)
     */
    public function getSpaceMembers($spaceId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                SELECT sm.*, u.username
                FROM space_memberships sm
                JOIN users u ON sm.user_id = u.id
                WHERE sm.space_id = ? AND u.active = 1
                ORDER BY sm.joined_at ASC
            ");
            $stmt->execute([$spaceId]);
            $members = $stmt->fetchAll();
            
            // Ne retourner que les données non sensibles
            foreach ($members as &$member) {
                // Garder seulement l'username (non chiffré) et les données de membership
                unset($member['name']);
                unset($member['email']);
            }
            
            return $members;
            
        } catch (Exception $e) {
            error_log("Erreur récupération membres: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Vérifier les permissions d'un utilisateur (ADMIN UNIQUEMENT)
     */
    public function checkPermission($spaceId, $userId, $requiredRole) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                SELECT role FROM space_memberships 
                WHERE space_id = ? AND user_id = ?
            ");
            $stmt->execute([$spaceId, $userId]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return false;
            }
            
            $roleHierarchy = [
                'reader' => 1,
                'editor' => 2,
                'validator' => 3,
                'admin' => 4
            ];
            
            $userRole = $result['role'];
            $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
            $userLevel = $roleHierarchy[$userRole] ?? 0;
            
            return $userLevel >= $requiredLevel;
            
        } catch (Exception $e) {
            error_log("Erreur vérification permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logger une activité (ADMIN UNIQUEMENT)
     */
    public function logActivity($spaceId, $actorId, $action, $targetType, $targetId = null, $metadata = []) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                INSERT INTO activity_log (actor_id, space_id, action, target_type, target_id, metadata)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $actorId, 
                $spaceId, 
                $action, 
                $targetType, 
                $targetId, 
                json_encode($metadata)
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur log activité: " . $e->getMessage());
        }
    }
    
    /**
     * Mettre à jour un espace (ADMIN UNIQUEMENT)
     */
    public function updateSpace($spaceId, $name, $description, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                UPDATE shared_spaces 
                SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $spaceId]);
            
            // Logger l'activité
            $this->logActivity($spaceId, $userId, 'space.updated', 'space', $spaceId, [
                'name' => $name,
                'description' => $description
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur modification espace: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Changer le rôle d'un membre (ADMIN UNIQUEMENT)
     */
    public function updateMemberRole($spaceId, $memberId, $newRole, $updatedBy) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $stmt = $connection->prepare("
                UPDATE space_memberships 
                SET role = ? 
                WHERE space_id = ? AND user_id = ?
            ");
            $stmt->execute([$newRole, $spaceId, $memberId]);
            
            // Logger l'activité
            $this->logActivity($spaceId, $updatedBy, 'member.role_updated', 'space', $spaceId, [
                'user_id' => $memberId,
                'new_role' => $newRole
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur modification rôle: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Récupérer les statistiques d'un espace (ADMIN UNIQUEMENT)
     */
    public function getSpaceStats($spaceId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            
            // Compter les membres
            $stmt = $connection->prepare("
                SELECT COUNT(*) as member_count 
                FROM space_memberships 
                WHERE space_id = ?
            ");
            $stmt->execute([$spaceId]);
            $memberCount = $stmt->fetch()['member_count'];
            
            // Compter les infographies
            $stmt = $connection->prepare("
                SELECT COUNT(*) as infographic_count 
                FROM shared_infographics 
                WHERE space_id = ? AND status != 'deleted'
            ");
            $stmt->execute([$spaceId]);
            $infographicCount = $stmt->fetch()['infographic_count'];
            
            // Compter les commentaires (à implémenter plus tard)
            $commentCount = 0;
            
            return [
                'member_count' => $memberCount,
                'infographic_count' => $infographicCount,
                'comment_count' => $commentCount
            ];
            
        } catch (Exception $e) {
            error_log("Erreur statistiques espace: " . $e->getMessage());
            return [
                'member_count' => 0,
                'infographic_count' => 0,
                'comment_count' => 0
            ];
        }
    }
    
    /**
     * Supprimer un espace (ADMIN UNIQUEMENT)
     * @param int $spaceId ID de l'espace
     * @param int $userId ID de l'utilisateur qui supprime
     * @param bool $permanent Si true, suppression définitive (admin seulement)
     */
    public function deleteSpace($spaceId, $userId, $permanent = false) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            
            if ($permanent) {
                // Suppression définitive (admin seulement)
                // Supprimer d'abord les membres
                $stmt = $connection->prepare("DELETE FROM space_memberships WHERE space_id = ?");
                $stmt->execute([$spaceId]);
                
                // Supprimer l'espace
                $stmt = $connection->prepare("DELETE FROM shared_spaces WHERE id = ?");
                $stmt->execute([$spaceId]);
                
                // Logger l'activité
                $this->logActivity($spaceId, $userId, 'space.permanently_deleted', 'space', $spaceId);
            } else {
                // Marquer comme inactif (soft delete)
                $stmt = $connection->prepare("
                    UPDATE shared_spaces SET is_active = 0 WHERE id = ?
                ");
                $stmt->execute([$spaceId]);
                
                // Logger l'activité
                $this->logActivity($spaceId, $userId, 'space.deleted', 'space', $spaceId);
            }
            
            $connection->commit();
            return true;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur suppression espace: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restaurer un espace supprimé (ADMIN UNIQUEMENT)
     */
    public function restoreSpace($spaceId, $userId) {
        try {
            // Vérifier que l'utilisateur est administrateur
            $this->requireAdmin();
            
            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            
            // Marquer comme actif
            $stmt = $connection->prepare("
                UPDATE shared_spaces SET is_active = 1 WHERE id = ?
            ");
            $stmt->execute([$spaceId]);
            
            // Logger l'activité
            $this->logActivity($spaceId, $userId, 'space.restored', 'space', $spaceId);
            
            $connection->commit();
            return true;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Erreur restauration espace: " . $e->getMessage());
            throw $e;
        }
    }
}
