<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';

// ============================================
// ✅ FIX BUG 2 : Fonction globale avec exit()
// Évite toute double sortie ou output corrompu
// ============================================
function sendResponse($success, $message, $code = 200, $data = null) {
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// ============================================
// ✅ FIX BUG 3 : Lecture du token robuste
// Compatible toutes configs XAMPP/Apache
// ============================================
function getBearerToken() {
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === 'authorization') {
                if (preg_match('/Bearer\s+(.+)/i', $value, $m)) return trim($m[1]);
            }
        }
    }
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (!empty($_SERVER[$key])) {
            if (preg_match('/Bearer\s+(.+)/i', $_SERVER[$key], $m)) return trim($m[1]);
        }
    }
    return null;
}

// ============================================
// CLASSE API
// ============================================
class AdminsAPI {
    private $db;
    private $currentUser;

    // ✅ FIX BUG 1 : Le constructeur reçoit $db
    // en paramètre → getDB() est appelé dans le
    // try/catch global, donc toute exception est attrapée
    public function __construct($db) {
        $this->db = $db;
        $this->currentUser = $this->authenticate();
    }

    private function authenticate() {
        $token = getBearerToken();
        if (empty($token)) return null;

        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name, r.permissions
                FROM sessions s
                INNER JOIN users u ON s.user_id = u.id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE s.token = :token
                  AND s.expires_at > NOW()
                  AND u.is_active = TRUE
            ");
            $stmt->execute(['token' => $token]);
            $user = $stmt->fetch();

            if ($user) {
                $user['permissions'] = json_decode($user['permissions'] ?? '{}', true);
            }
            return $user ?: null;

        } catch (Exception $e) {
            error_log("AdminsAPI::authenticate error: " . $e->getMessage());
            return null;
        }
    }

    private function isSuperAdmin() {
        if (!$this->currentUser) return false;

        $rawRole = $this->currentUser['role_name'] ?? '';
        $role = strtolower(trim(str_replace([' ', '-'], '_', $rawRole)));
        return in_array($role, ['super_admin', 'superadmin']);
    }

    // ============================================
    // CREATE : Créer un compte admin
    // ============================================
    public function createAdmin($data) {
        if (!$this->isSuperAdmin()) {
            sendResponse(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        if (!$data || !is_array($data)) {
            sendResponse(false, 'Données JSON invalides ou manquantes', 400);
        }

        $required = ['username', 'email', 'password', 'full_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendResponse(false, "Le champ '$field' est requis", 400);
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, 'Adresse email invalide', 400);
        }

        $minLen = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 6;
        if (strlen($data['password']) < $minLen) {
            sendResponse(false, "Le mot de passe doit contenir au moins $minLen caractères", 400);
        }

        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => trim($data['username'])]);
            if ($stmt->fetch()) {
                sendResponse(false, "Ce nom d'utilisateur est déjà utilisé", 409);
            }

            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => trim($data['email'])]);
            if ($stmt->fetch()) {
                sendResponse(false, 'Cet email est déjà utilisé', 409);
            }

            $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmt->execute();
            $adminRole = $stmt->fetch();

            if (!$adminRole) {
                sendResponse(false, "Rôle 'admin' introuvable en base de données", 500);
            }

            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, phone, role_id, is_active, created_by)
                VALUES (:username, :email, :password_hash, :full_name, :phone, :role_id, TRUE, :created_by)
            ");
            $stmt->execute([
                'username'      => trim($data['username']),
                'email'         => trim($data['email']),
                'password_hash' => $passwordHash,
                'full_name'     => trim($data['full_name']),
                'phone'         => !empty($data['phone']) ? trim($data['phone']) : null,
                'role_id'       => $adminRole['id'],
                'created_by'    => $this->currentUser['id']
            ]);

            $adminId = $this->db->lastInsertId();
            $this->logActivity('ADMIN_CREATED', 'user', $adminId, [
                'username' => $data['username'],
                'email'    => $data['email']
            ]);

            sendResponse(true, "Compte admin '{$data['username']}' créé avec succès", 201, [
                'admin' => [
                    'id'        => (int)$adminId,
                    'username'  => trim($data['username']),
                    'email'     => trim($data['email']),
                    'full_name' => trim($data['full_name'])
                ]
            ]);

        } catch (PDOException $e) {
            error_log("AdminsAPI::createAdmin PDO error: " . $e->getMessage());
            // Message d'erreur détaillé pour faciliter le debug
            sendResponse(false, 'Erreur base de données : ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            error_log("AdminsAPI::createAdmin error: " . $e->getMessage());
            sendResponse(false, 'Erreur serveur : ' . $e->getMessage(), 500);
        }
    }

    // ============================================
    // LIST : Lister les admins
    // ============================================
    public function listAdmins() {
        if (!$this->isSuperAdmin()) {
            sendResponse(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.full_name,
                    u.phone,
                    u.is_active,
                    u.last_login,
                    u.created_at,
                    r.name as role_name,
                    creator.full_name as created_by_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN users creator ON u.created_by = creator.id
                WHERE r.name = 'admin'
                ORDER BY u.created_at DESC
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll();

            foreach ($admins as &$admin) {
                $admin['is_active'] = (bool)$admin['is_active'];
            }

            sendResponse(true, 'Liste des admins récupérée', 200, ['admins' => $admins]);

        } catch (Exception $e) {
            error_log("AdminsAPI::listAdmins error: " . $e->getMessage());
            sendResponse(false, 'Erreur lors de la récupération : ' . $e->getMessage(), 500);
        }
    }

    // ============================================
    // TOGGLE STATUS : Activer / Désactiver
    // ============================================
    public function toggleAdminStatus($adminId, $isActive) {
        if (!$this->isSuperAdmin()) {
            sendResponse(false, 'Accès refusé - Super Admin uniquement', 403);
        }

        $adminId = (int)$adminId;
        if (!$adminId) {
            sendResponse(false, 'ID admin invalide', 400);
        }

        if ($adminId === (int)$this->currentUser['id'] && !$isActive) {
            sendResponse(false, 'Vous ne pouvez pas désactiver votre propre compte', 403);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.username, r.name as role_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $adminId]);
            $admin = $stmt->fetch();

            if (!$admin) {
                sendResponse(false, 'Administrateur introuvable', 404);
            }

            if ($admin['role_name'] === 'super_admin') {
                sendResponse(false, 'Impossible de modifier un compte super_admin', 403);
            }

            $stmt = $this->db->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
            $stmt->execute(['is_active' => $isActive ? 1 : 0, 'id' => $adminId]);

            $label = $isActive ? 'activé' : 'désactivé';
            $this->logActivity('ADMIN_STATUS_CHANGED', 'user', $adminId, ['is_active' => $isActive]);
            sendResponse(true, "Compte '{$admin['username']}' $label avec succès");

        } catch (Exception $e) {
            error_log("AdminsAPI::toggleAdminStatus error: " . $e->getMessage());
            sendResponse(false, 'Erreur lors de la modification : ' . $e->getMessage(), 500);
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
                'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Le log ne doit jamais faire crasher l'API
            error_log("AdminsAPI::logActivity error: " . $e->getMessage());
        }
    }
}

// ============================================
// ✅ FIX BUG 1 : TOUT dans le try/catch global
// y compris getDB() et new AdminsAPI()
// ============================================
try {
    $db      = getDB();
    $api     = new AdminsAPI($db);
    $method  = $_SERVER['REQUEST_METHOD'];
    $action  = $_GET['action'] ?? '';
    $adminId = $_GET['id'] ?? null;

    switch ($action) {
        case 'create':
            if ($method !== 'POST') sendResponse(false, 'Méthode non autorisée', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $api->createAdmin($data);
            break;

        case 'list':
            $api->listAdmins();
            break;

        case 'toggle-status':
            if ($method !== 'POST') sendResponse(false, 'Méthode non autorisée', 405);
            if (!$adminId) sendResponse(false, 'ID admin requis', 400);
            $data     = json_decode(file_get_contents('php://input'), true);
            $isActive = $data['is_active'] ?? false;
            $api->toggleAdminStatus($adminId, $isActive);
            break;

        default:
            sendResponse(false, "Action '$action' non reconnue", 404);
    }

} catch (Exception $e) {
    error_log("admins.php global error: " . $e->getMessage());
    sendResponse(false, 'Erreur serveur : ' . $e->getMessage(), 500);
}
?>