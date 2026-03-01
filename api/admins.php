<?php
/**
 * API de gestion des comptes Admin
 * Station Service & Hôtel
 * Accès réservé au Super Admin uniquement
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

class AdminsAPI {
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

        // Fallback : lire depuis $_SERVER si getallheaders() ne renvoie rien
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
     * et aux variations d'écriture (espaces, tirets, majuscules)
     */
    private function isSuperAdmin() {
        if (!$this->currentUser) {
            error_log('isSuperAdmin: aucun utilisateur authentifié');
            return false;
        }

        $rawRole = $this->currentUser['role_name'] ?? '';
        // Normaliser : minuscule, espaces/tirets → underscore
        $role = strtolower(trim(str_replace([' ', '-'], '_', $rawRole)));

        error_log("isSuperAdmin: rôle brut = '$rawRole', normalisé = '$role'");

        return in_array($role, ['super_admin', 'superadmin', 'super admin']);
    }

    public function createAdmin($data) {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $required = ['username', 'email', 'password', 'full_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->response(false, "Le champ $field est requis", 400);
                }
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->response(false, 'Email invalide', 400);
            }

            if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 6);

            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return $this->response(false, 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères', 400);
            }

            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $data['username']]);
            if ($stmt->fetch()) {
                return $this->response(false, 'Ce nom d\'utilisateur existe déjà', 400);
            }

            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $data['email']]);
            if ($stmt->fetch()) {
                return $this->response(false, 'Cet email existe déjà', 400);
            }

            $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmt->execute();
            $adminRole = $stmt->fetch();

            if (!$adminRole) {
                return $this->response(false, 'Rôle Admin introuvable dans la base de données', 500);
            }

            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, phone, role_id, is_active, created_by)
                VALUES (:username, :email, :password_hash, :full_name, :phone, :role_id, TRUE, :created_by)
            ");

            $stmt->execute([
                'username'      => $data['username'],
                'email'         => $data['email'],
                'password_hash' => $passwordHash,
                'full_name'     => $data['full_name'],
                'phone'         => $data['phone'] ?? null,
                'role_id'       => $adminRole['id'],
                'created_by'    => $this->currentUser['id']
            ]);

            $adminId = $this->db->lastInsertId();

            $this->logActivity('ADMIN_CREATED', 'user', $adminId, [
                'username' => $data['username'],
                'email'    => $data['email']
            ]);

            return $this->response(true, 'Compte Admin créé avec succès', 201, ['admin_id' => $adminId]);

        } catch (Exception $e) {
            error_log("Erreur createAdmin: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la création', 500);
        }
    }

    public function listAdmins() {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->query("
                SELECT u.id, u.username, u.email, u.full_name, u.phone,
                       u.is_active, u.last_login, u.created_at,
                       creator.full_name as created_by_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN users creator ON u.created_by = creator.id
                WHERE r.name = 'admin'
                ORDER BY u.created_at DESC
            ");
            $admins = $stmt->fetchAll();

            return $this->response(true, 'Liste des admins', 200, ['admins' => $admins]);

        } catch (Exception $e) {
            error_log("Erreur listAdmins: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    public function toggleAdminStatus($adminId, $isActive) {
        if (!$this->isSuperAdmin()) {
            return $this->response(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.id FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id AND r.name = 'admin'
            ");
            $stmt->execute(['id' => $adminId]);

            if (!$stmt->fetch()) {
                return $this->response(false, 'Admin introuvable', 404);
            }

            $stmt = $this->db->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
            $stmt->execute([
                'is_active' => $isActive ? 1 : 0,
                'id'        => $adminId
            ]);

            $action = $isActive ? 'activé' : 'désactivé';
            $this->logActivity('ADMIN_STATUS_CHANGED', 'user', $adminId, ['is_active' => $isActive]);

            return $this->response(true, "Compte admin $action avec succès");

        } catch (Exception $e) {
            error_log("Erreur toggleAdminStatus: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la modification', 500);
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
$admins = new AdminsAPI();
$method  = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';
$adminId = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $admins->createAdmin($data);
            break;

        case 'list':
            echo $admins->listAdmins();
            break;

        case 'toggle-status':
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']); exit; }
            if (!$adminId) { echo json_encode(['success' => false, 'message' => 'ID admin requis']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $admins->toggleAdminStatus($adminId, $data['is_active'] ?? false);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur API Admins: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>