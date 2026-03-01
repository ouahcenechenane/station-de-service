<?php
/**
 * API de gestion des approbations
 * Station Service & Hôtel
 * VERSION CORRIGÉE
 */

// ============================================
// EN-TÊTES CORS - DOIVENT ÊTRE EN PREMIER !
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

class ApprovalsAPI {
    private $db;
    private $currentUser;

    public function __construct() {
        $this->db = getDB();
        $this->currentUser = $this->authenticate();
    }

    private function authenticate() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $token);

        // Fallback : lire depuis $_SERVER
        if (empty($token) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }

        if (empty($token)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.permissions
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE s.token = :token AND s.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
        }

        return $user;
    }

    /**
     * ✅ FIX BUG 1 : Vérification du rôle tolérante à la casse
     */
    private function isSuperAdmin() {
        if (!$this->currentUser) {
            error_log('isSuperAdmin: aucun utilisateur authentifié');
            return false;
        }

        $rawRole = $this->currentUser['role_name'] ?? '';
        $role = strtolower(trim(str_replace([' ', '-'], '_', $rawRole)));

        error_log("isSuperAdmin: rôle brut = '$rawRole', normalisé = '$role'");

        return in_array($role, ['super_admin', 'superadmin', 'super admin']);
    }

    public function listPending() {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->query("
                SELECT * FROM v_menu_complete
                WHERE status = 'en_attente'
                ORDER BY id DESC
            ");
            $items = $stmt->fetchAll();

            return $this->response(true, 'Publications en attente', 200, ['items' => $items]);

        } catch (Exception $e) {
            error_log("Erreur listPending: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    public function approve($itemId) {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name FROM menu_items
                WHERE id = :id AND status = 'en_attente'
            ");
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                return $this->response(false, 'Publication introuvable ou déjà traitée', 404);
            }

            $stmt = $this->db->prepare("
                UPDATE menu_items
                SET status = 'approuve', approved_by = :approved_by, approved_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['approved_by' => $this->currentUser['id'], 'id' => $itemId]);

            $this->logActivity('PUBLICATION_APPROVED', 'menu_item', $itemId, ['name' => $item['name']]);

            return $this->response(true, 'Publication approuvée avec succès');

        } catch (Exception $e) {
            error_log("Erreur approve: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de l\'approbation', 500);
        }
    }

    public function reject($itemId) {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name FROM menu_items
                WHERE id = :id AND status = 'en_attente'
            ");
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                return $this->response(false, 'Publication introuvable ou déjà traitée', 404);
            }

            $stmt = $this->db->prepare("
                UPDATE menu_items
                SET status = 'refuse', approved_by = :approved_by, approved_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['approved_by' => $this->currentUser['id'], 'id' => $itemId]);

            $this->logActivity('PUBLICATION_REJECTED', 'menu_item', $itemId, ['name' => $item['name']]);

            return $this->response(true, 'Publication refusée');

        } catch (Exception $e) {
            error_log("Erreur reject: " . $e->getMessage());
            return $this->response(false, 'Erreur lors du refus', 500);
        }
    }

    public function getStats() {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN status = 'approuve'   THEN 1 ELSE 0 END) as approuve,
                    SUM(CASE WHEN status = 'refuse'     THEN 1 ELSE 0 END) as refuse
                FROM menu_items
            ");
            $stats = $stmt->fetch();

            return $this->response(true, 'Statistiques', 200, ['stats' => $stats]);

        } catch (Exception $e) {
            error_log("Erreur getStats: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    private function logActivity($action, $entityType = null, $entityId = null, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :user_agent)
            ");
            $stmt->execute([
                'user_id'     => $this->currentUser['id'],
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'details'     => json_encode($details),
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Erreur logActivity: " . $e->getMessage());
        }
    }

    private function response($success, $message, $code = 200, $data = null) {
        http_response_code($code);
        $response = ['success' => $success, 'message' => $message];
        if ($data !== null) $response['data'] = $data;
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}

// Traiter la requête
$approvals = new ApprovalsAPI();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action'] ?? '';
$itemId    = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'list-pending':
            echo $approvals->listPending();
            break;

        case 'approve':
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']); exit; }
            if (!$itemId) { echo json_encode(['success' => false, 'message' => 'ID publication requis']); exit; }
            echo $approvals->approve($itemId);
            break;

        case 'reject':
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']); exit; }
            if (!$itemId) { echo json_encode(['success' => false, 'message' => 'ID publication requis']); exit; }
            echo $approvals->reject($itemId);
            break;

        case 'stats':
            echo $approvals->getStats();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur API Approvals: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>