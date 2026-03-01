<?php
/**
 * API de gestion des utilisateurs
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

/**
 * Classe pour gérer les utilisateurs
 */
class UsersAPI {
    private $db;
    private $currentUser;

    public function __construct() {
        $this->db = getDB();
        $this->currentUser = $this->authenticate();
    }

    /**
     * Authentifier l'utilisateur via token
     */
    private function authenticate() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        $token = str_replace('Bearer ', '', $token);

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
     * Vérifier les permissions
     */
    private function hasPermission($entity, $action) {
        if (!$this->currentUser) {
            return false;
        }

        // Super admin a toutes les permissions
        if ($this->currentUser['role_name'] === 'super_admin') {
            return true;
        }

        $permissions = $this->currentUser['permissions'];
        return isset($permissions[$entity]) && in_array($action, $permissions[$entity]);
    }

    /**
     * Lister tous les utilisateurs
     */
    public function list() {
        if (!$this->hasPermission('users', 'read')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            $stmt = $this->db->query("
                SELECT u.id, u.username, u.email, u.full_name, u.phone, 
                       u.is_active, u.last_login, u.created_at,
                       r.name as role_name, r.description as role_description
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll();

            return $this->response(true, 'Liste des utilisateurs', 200, ['users' => $users]);

        } catch (Exception $e) {
            error_log("Erreur list users: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function create($data) {
        if (!$this->hasPermission('users', 'create')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            // Validation
            $required = ['username', 'email', 'password', 'full_name', 'role_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->response(false, "Le champ $field est requis", 400);
                }
            }

            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return $this->response(false, 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères', 400);
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->response(false, 'Email invalide', 400);
            }

            // Vérifier si l'username existe déjà
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $data['username']]);
            if ($stmt->fetch()) {
                return $this->response(false, 'Ce nom d\'utilisateur existe déjà', 400);
            }

            // Vérifier si l'email existe déjà
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $data['email']]);
            if ($stmt->fetch()) {
                return $this->response(false, 'Cet email existe déjà', 400);
            }

            // Créer l'utilisateur
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, phone, role_id, created_by)
                VALUES (:username, :email, :password_hash, :full_name, :phone, :role_id, :created_by)
            ");
            
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'role_id' => $data['role_id'],
                'created_by' => $this->currentUser['id']
            ]);

            $userId = $this->db->lastInsertId();

            $this->logActivity('USER_CREATED', 'user', $userId, [
                'username' => $data['username'],
                'email' => $data['email'],
                'role_id' => $data['role_id']
            ]);

            return $this->response(true, 'Utilisateur créé avec succès', 201, ['user_id' => $userId]);

        } catch (Exception $e) {
            error_log("Erreur create user: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la création', 500);
        }
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update($userId, $data) {
        if (!$this->hasPermission('users', 'update')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            // Vérifier que l'utilisateur existe
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            if (!$stmt->fetch()) {
                return $this->response(false, 'Utilisateur introuvable', 404);
            }

            // Préparer les champs à mettre à jour
            $updates = [];
            $params = ['id' => $userId];

            if (!empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    return $this->response(false, 'Email invalide', 400);
                }
                $updates[] = "email = :email";
                $params['email'] = $data['email'];
            }

            if (!empty($data['full_name'])) {
                $updates[] = "full_name = :full_name";
                $params['full_name'] = $data['full_name'];
            }

            if (isset($data['phone'])) {
                $updates[] = "phone = :phone";
                $params['phone'] = $data['phone'];
            }

            if (!empty($data['role_id'])) {
                $updates[] = "role_id = :role_id";
                $params['role_id'] = $data['role_id'];
            }

            if (isset($data['is_active'])) {
                $updates[] = "is_active = :is_active";
                $params['is_active'] = $data['is_active'] ? 1 : 0;
            }

            if (!empty($data['password'])) {
                if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                    return $this->response(false, 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères', 400);
                }
                $updates[] = "password_hash = :password_hash";
                $params['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            if (empty($updates)) {
                return $this->response(false, 'Aucune modification à effectuer', 400);
            }

            // Effectuer la mise à jour
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logActivity('USER_UPDATED', 'user', $userId, $data);

            return $this->response(true, 'Utilisateur mis à jour avec succès');

        } catch (Exception $e) {
            error_log("Erreur update user: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la mise à jour', 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function delete($userId) {
        if (!$this->hasPermission('users', 'delete')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            // Ne pas permettre de supprimer son propre compte
            if ($userId == $this->currentUser['id']) {
                return $this->response(false, 'Vous ne pouvez pas supprimer votre propre compte', 400);
            }

            // Vérifier que l'utilisateur existe
            $stmt = $this->db->prepare("SELECT username FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return $this->response(false, 'Utilisateur introuvable', 404);
            }

            // Supprimer l'utilisateur
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);

            $this->logActivity('USER_DELETED', 'user', $userId, ['username' => $user['username']]);

            return $this->response(true, 'Utilisateur supprimé avec succès');

        } catch (Exception $e) {
            error_log("Erreur delete user: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la suppression', 500);
        }
    }

    /**
     * Obtenir les rôles disponibles
     */
    public function getRoles() {
        if (!$this->hasPermission('users', 'read')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            $stmt = $this->db->query("SELECT id, name, description FROM roles ORDER BY id");
            $roles = $stmt->fetchAll();

            return $this->response(true, 'Liste des rôles', 200, ['roles' => $roles]);

        } catch (Exception $e) {
            error_log("Erreur getRoles: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    /**
     * Logger une activité
     */
    private function logActivity($action, $entityType = null, $entityId = null, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :user_agent)
            ");
            $stmt->execute([
                'user_id' => $this->currentUser['id'],
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => json_encode($details),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
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
$users = new UsersAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'list':
            echo $users->list();
            break;

        case 'create':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $users->create($data);
            break;

        case 'update':
            if ($method !== 'PUT' && $method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $users->update($userId, $data);
            break;

        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                exit;
            }
            echo $users->delete($userId);
            break;

        case 'roles':
            echo $users->getRoles();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur API Users: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>