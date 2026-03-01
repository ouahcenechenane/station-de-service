<?php
/**
 * API d'authentification
 * Station Service & Hôtel
 */

// ============================================
// EN-TÊTES CORS - DOIVENT ÊTRE EN PREMIER !
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json; charset=utf-8');

// Gérer les requêtes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Définir la durée de session si non définie
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 heure
}

// Définir la longueur minimale du mot de passe si non définie
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 6);
}

/**
 * Classe pour gérer l'authentification
 */
class AuthAPI {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Connexion utilisateur
     */
    public function login($data) {
        try {
            if (empty($data['username']) || empty($data['password'])) {
                return $this->response(false, 'Identifiant et mot de passe requis', 400);
            }

            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name, r.permissions 
                FROM users u 
                INNER JOIN roles r ON u.role_id = r.id 
                WHERE u.username = :username AND u.is_active = TRUE
            ");
            $stmt->execute(['username' => $data['username']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                $this->logActivity($user['id'] ?? null, 'LOGIN_FAILED', 'user', $user['id'] ?? null, [
                    'username' => $data['username'],
                    'ip' => $this->getClientIP()
                ]);
                return $this->response(false, 'Identifiant ou mot de passe incorrect', 401);
            }

            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

            $stmt = $this->db->prepare("
                INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
                VALUES (:user_id, :token, :ip, :user_agent, :expires_at)
            ");
            $stmt->execute([
                'user_id' => $user['id'],
                'token' => $token,
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'expires_at' => $expiresAt
            ]);

            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);

            $this->logActivity($user['id'], 'LOGIN_SUCCESS', 'user', $user['id'], [
                'ip' => $this->getClientIP()
            ]);

            $responseData = [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role_name'],
                    'permissions' => json_decode($user['permissions'] ?? '[]', true)
                ]
            ];

            return $this->response(true, 'Connexion réussie', 200, $responseData);

        } catch (Exception $e) {
            error_log("Erreur login: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la connexion', 500);
        }
    }

    /**
     * Déconnexion utilisateur
     */
    public function logout($token) {
        try {
            if (empty($token)) {
                return $this->response(false, 'Token requis', 400);
            }

            $user = $this->getUserFromToken($token);

            $stmt = $this->db->prepare("DELETE FROM sessions WHERE token = :token");
            $stmt->execute(['token' => $token]);

            if ($user) {
                $this->logActivity($user['id'], 'LOGOUT', 'user', $user['id']);
            }

            return $this->response(true, 'Déconnexion réussie');

        } catch (Exception $e) {
            error_log("Erreur logout: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la déconnexion', 500);
        }
    }

    /**
     * Vérifier la validité du token
     */
    public function verify($token) {
        try {
            if (empty($token)) {
                return $this->response(false, 'Token requis', 400);
            }

            $user = $this->getUserFromToken($token);

            if (!$user) {
                return $this->response(false, 'Session invalide ou expirée', 401);
            }

            return $this->response(true, 'Token valide', 200, [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role_name'],
                    'permissions' => json_decode($user['permissions'] ?? '[]', true)
                ]
            ]);

        } catch (Exception $e) {
            error_log("Erreur verify: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la vérification', 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword($token, $data) {
        try {
            $user = $this->getUserFromToken($token);
            if (!$user) {
                return $this->response(false, 'Session invalide', 401);
            }

            if (empty($data['current_password']) || empty($data['new_password'])) {
                return $this->response(false, 'Ancien et nouveau mot de passe requis', 400);
            }

            if (strlen($data['new_password']) < PASSWORD_MIN_LENGTH) {
                return $this->response(false, 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères', 400);
            }

            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);
            $currentUser = $stmt->fetch();

            if (!password_verify($data['current_password'], $currentUser['password_hash'])) {
                return $this->response(false, 'Mot de passe actuel incorrect', 401);
            }

            $newHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute(['hash' => $newHash, 'id' => $user['id']]);

            $this->logActivity($user['id'], 'PASSWORD_CHANGED', 'user', $user['id']);

            return $this->response(true, 'Mot de passe modifié avec succès');

        } catch (Exception $e) {
            error_log("Erreur changePassword: " . $e->getMessage());
            return $this->response(false, 'Erreur lors du changement de mot de passe', 500);
        }
    }

    /**
     * Obtenir l'utilisateur depuis le token
     */
    private function getUserFromToken($token) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.permissions, s.expires_at
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE s.token = :token AND s.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Générer un token unique
     */
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Obtenir l'IP du client
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    /**
     * Récupérer le token Authorization de manière robuste
     */
    public function getAuthorizationToken() {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }

        return null;
    }

    /**
     * Logger une activité
     */
    private function logActivity($userId, $action, $entityType = null, $entityId = null, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :user_agent)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => json_encode($details),
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Erreur logActivity: " . $e->getMessage());
        }
    }

    /**
     * Formater la réponse
     */
    private function response($success, $message, $code = 200, $data = null) {
        http_response_code($code);
        $response = [
            'success' => $success,
            'message' => $message
        ];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}

// Traiter la requête
$auth = new AuthAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $auth->login($data);
            break;

        case 'logout':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $token = $auth->getAuthorizationToken();
            echo $auth->logout($token);
            break;

        case 'verify':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $token = $auth->getAuthorizationToken();
            echo $auth->verify($token);
            break;

        case 'change-password':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $token = $auth->getAuthorizationToken();
            $data = json_decode(file_get_contents('php://input'), true);
            echo $auth->changePassword($token, $data);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur API Auth: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>