<?php
/**
 * API de gestion du menu restaurant
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

class MenuAPI {
    private $db;
    private $currentUser;

    public function __construct() {
        $this->db = getDB();
        $this->currentUser = $this->authenticate();
    }

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

    private function hasPermission($entity, $action) {
        if (!$this->currentUser) {
            return false;
        }

        if ($this->currentUser['role_name'] === 'super_admin') {
            return true;
        }

        $permissions = $this->currentUser['permissions'];
        return isset($permissions[$entity]) && 
               (in_array($action, $permissions[$entity]) || in_array('manage', $permissions[$entity]));
    }

    /**
     * Obtenir le menu complet (public) - UNIQUEMENT les publications approuvées
     */
    public function getMenu() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM v_menu_complete 
                WHERE is_available = TRUE AND status = 'approuve'
                ORDER BY category_id, display_order
            ");
            $items = $stmt->fetchAll();

            // Grouper par catégorie
            $menu = [];
            foreach ($items as $item) {
                $categoryKey = strtolower(str_replace(' ', '_', $item['category_name']));
                if (!isset($menu[$categoryKey])) {
                    $menu[$categoryKey] = [
                        'id' => $item['category_id'],
                        'name' => $item['category_name'],
                        'name_ar' => $item['category_name_ar'],
                        'icon' => $item['category_icon'],
                        'items' => []
                    ];
                }
                $menu[$categoryKey]['items'][] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'name_ar' => $item['name_ar'],
                    'description' => $item['description'],
                    'description_ar' => $item['description_ar'],
                    'price' => (float)$item['price'],
                    'image_url' => $item['image_url'],
                    'is_featured' => (bool)$item['is_featured']
                ];
            }

            return $this->response(true, 'Menu récupéré', 200, ['menu' => array_values($menu)]);

        } catch (Exception $e) {
            error_log("Erreur getMenu: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    /**
     * Lister tous les éléments (admin)
     */
    public function listItems() {
        if (!$this->hasPermission('menu', 'read')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            $stmt = $this->db->query("
                SELECT * FROM v_menu_complete
                ORDER BY category_id, display_order
            ");
            $items = $stmt->fetchAll();

            return $this->response(true, 'Liste des éléments', 200, ['items' => $items]);

        } catch (Exception $e) {
            error_log("Erreur listItems: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la récupération', 500);
        }
    }

    /**
     * Créer un élément de menu
     * MODIFICATION : Les Admins créent en "en_attente", Super Admin en "approuve"
     */
    public function createItem($data) {
        if (!$this->hasPermission('menu', 'create')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            // Validation
            if (empty($data['category_id']) || empty($data['name']) || empty($data['price'])) {
                return $this->response(false, 'Catégorie, nom et prix requis', 400);
            }

            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return $this->response(false, 'Prix invalide', 400);
            }

            // Déterminer le statut selon le rôle
            $status = 'approuve'; // Par défaut pour Super Admin
            if ($this->currentUser['role_name'] === 'admin') {
                $status = 'en_attente'; // Les admins créent en attente
            }

            $stmt = $this->db->prepare("
                INSERT INTO menu_items (category_id, name, name_ar, description, description_ar, 
                                       price, image_url, is_available, status, is_featured, display_order, updated_by)
                VALUES (:category_id, :name, :name_ar, :description, :description_ar, 
                        :price, :image_url, :is_available, :status, :is_featured, :display_order, :updated_by)
            ");

            $stmt->execute([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'name_ar' => $data['name_ar'] ?? null,
                'description' => $data['description'] ?? null,
                'description_ar' => $data['description_ar'] ?? null,
                'price' => $data['price'],
                'image_url' => $data['image_url'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'status' => $status,
                'is_featured' => $data['is_featured'] ?? false,
                'display_order' => $data['display_order'] ?? 0,
                'updated_by' => $this->currentUser['id']
            ]);

            $itemId = $this->db->lastInsertId();

            $this->logActivity('MENU_ITEM_CREATED', 'menu_item', $itemId, [
                'name' => $data['name'],
                'price' => $data['price'],
                'status' => $status
            ]);

            $message = ($status === 'en_attente') 
                ? 'Élément créé et en attente d\'approbation' 
                : 'Élément créé avec succès';

            return $this->response(true, $message, 201, ['item_id' => $itemId, 'status' => $status]);

        } catch (Exception $e) {
            error_log("Erreur createItem: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la création', 500);
        }
    }

    /**
     * Mettre à jour un élément de menu
     */
    public function updateItem($itemId, $data) {
        if (!$this->hasPermission('menu', 'update')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            // Vérifier que l'élément existe
            $stmt = $this->db->prepare("SELECT id FROM menu_items WHERE id = :id");
            $stmt->execute(['id' => $itemId]);
            if (!$stmt->fetch()) {
                return $this->response(false, 'Élément introuvable', 404);
            }

            // Préparer les champs à mettre à jour
            $updates = [];
            $params = ['id' => $itemId, 'updated_by' => $this->currentUser['id']];

            $allowedFields = ['category_id', 'name', 'name_ar', 'description', 'description_ar', 
                             'price', 'image_url', 'is_available', 'is_featured', 'display_order'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }

            if (empty($updates)) {
                return $this->response(false, 'Aucune modification à effectuer', 400);
            }

            $updates[] = "updated_by = :updated_by";

            $sql = "UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logActivity('MENU_ITEM_UPDATED', 'menu_item', $itemId, $data);

            return $this->response(true, 'Élément mis à jour avec succès');

        } catch (Exception $e) {
            error_log("Erreur updateItem: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la mise à jour', 500);
        }
    }

    /**
     * Supprimer un élément de menu
     */
    public function deleteItem($itemId) {
        if (!$this->hasPermission('menu', 'delete')) {
            return $this->response(false, 'Permission refusée', 403);
        }

        try {
            $stmt = $this->db->prepare("SELECT name FROM menu_items WHERE id = :id");
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                return $this->response(false, 'Élément introuvable', 404);
            }

            $stmt = $this->db->prepare("DELETE FROM menu_items WHERE id = :id");
            $stmt->execute(['id' => $itemId]);

            $this->logActivity('MENU_ITEM_DELETED', 'menu_item', $itemId, ['name' => $item['name']]);

            return $this->response(true, 'Élément supprimé avec succès');

        } catch (Exception $e) {
            error_log("Erreur deleteItem: " . $e->getMessage());
            return $this->response(false, 'Erreur lors de la suppression', 500);
        }
    }

    /**
     * Obtenir les catégories
     */
    public function getCategories() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM menu_categories 
                WHERE is_active = TRUE 
                ORDER BY display_order
            ");
            $categories = $stmt->fetchAll();

            return $this->response(true, 'Liste des catégories', 200, ['categories' => $categories]);

        } catch (Exception $e) {
            error_log("Erreur getCategories: " . $e->getMessage());
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
$menu = new MenuAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$itemId = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'get':
            echo $menu->getMenu();
            break;

        case 'list':
            echo $menu->listItems();
            break;

        case 'create':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $menu->createItem($data);
            break;

        case 'update':
            if ($method !== 'PUT' && $method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            if (!$itemId) {
                echo json_encode(['success' => false, 'message' => 'ID élément requis']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo $menu->updateItem($itemId, $data);
            break;

        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            if (!$itemId) {
                echo json_encode(['success' => false, 'message' => 'ID élément requis']);
                exit;
            }
            echo $menu->deleteItem($itemId);
            break;

        case 'categories':
            echo $menu->getCategories();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    error_log("Erreur API Menu: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>