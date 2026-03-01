-- ============================================
-- Base de données pour Station Service & Hôtel
-- Version: 1.0.0
-- ============================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS station_service_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE station_service_db;

-- ============================================
-- TABLE: roles
-- Gestion des rôles utilisateurs
-- ============================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: users
-- Gestion des utilisateurs (admins)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: menu_categories
-- Catégories du menu restaurant
-- ============================================
CREATE TABLE IF NOT EXISTS menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    name_ar VARCHAR(100),
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: menu_items
-- Éléments du menu restaurant
-- ============================================
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    description TEXT,
    description_ar TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500),
    is_available BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_available (is_available),
    INDEX idx_featured (is_featured),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: announcements
-- Gestion des annonces
-- ============================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255),
    content TEXT NOT NULL,
    content_ar TEXT,
    type ENUM('info', 'warning', 'success', 'promotion') DEFAULT 'info',
    image_url VARCHAR(500),
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_on_homepage BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_homepage (display_on_homepage),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: reservations
-- Gestion des réservations (restaurant & hôtel)
-- ============================================
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('restaurant', 'hotel') NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(255),
    person_count INT NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME,
    check_in_date DATE,
    check_out_date DATE,
    room_type VARCHAR(100),
    details TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    handled_by INT,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_date (reservation_date),
    INDEX idx_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: settings
-- Paramètres du site
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: activity_logs
-- Journal des activités des administrateurs
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: sessions
-- Gestion des sessions utilisateurs
-- ============================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTION DES RÔLES PAR DÉFAUT
-- ============================================
INSERT INTO roles (name, description, permissions) VALUES
('super_admin', 'Super Administrateur - Accès complet au système', 
 '{"users": ["create", "read", "update", "delete"], "roles": ["manage"], "menu": ["manage"], "announcements": ["manage"], "reservations": ["manage"], "settings": ["manage"], "logs": ["read"]}'),
 
('admin', 'Administrateur - Gestion du contenu et des réservations', 
 '{"users": ["read"], "menu": ["manage"], "announcements": ["manage"], "reservations": ["manage"], "settings": ["read"]}'),
 
('editor', 'Éditeur - Gestion du menu et des annonces uniquement', 
 '{"menu": ["manage"], "announcements": ["create", "read", "update"]}');

-- ============================================
-- CRÉATION DU SUPER ADMIN PAR DÉFAUT
-- Mot de passe: SuperAdmin2026!
-- ============================================
INSERT INTO users (username, email, password_hash, full_name, phone, role_id, is_active)
VALUES 
('superadmin', 'superadmin@stationservice.dz', 
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Super Administrateur', '0796254287', 1, TRUE);

-- ============================================
-- INSERTION DES CATÉGORIES DE MENU PAR DÉFAUT
-- ============================================
INSERT INTO menu_categories (name, name_ar, icon, display_order) VALUES
('Plats Principaux', 'الأطباق الرئيسية', 'fa-plate-wheat', 1),
('Boissons', 'المشروبات', 'fa-glass-water', 2),
('Desserts', 'الحلويات', 'fa-ice-cream', 3);

-- ============================================
-- INSERTION DES ÉLÉMENTS DE MENU PAR DÉFAUT
-- ============================================
INSERT INTO menu_items (category_id, name, name_ar, price, is_available, display_order) VALUES
-- Plats principaux
(1, 'Couscous royal', 'الكسكس الملكي', 1200.00, TRUE, 1),
(1, 'Tajine poulet', 'طاجين الدجاج', 900.00, TRUE, 2),
(1, 'Pizza margherita', 'بيتزا مارغريتا', 800.00, TRUE, 3),
(1, 'Poulet grillé', 'دجاج مشوي', 850.00, TRUE, 4),

-- Boissons
(2, 'Jus d\'orange frais', 'عصير البرتقال الطازج', 200.00, TRUE, 1),
(2, 'Coca-Cola', 'كوكا كولا', 150.00, TRUE, 2),
(2, 'Eau minérale', 'ماء معدني', 80.00, TRUE, 3),
(2, 'Café', 'قهوة', 120.00, TRUE, 4),

-- Desserts
(3, 'Baklava', 'بقلاوة', 250.00, TRUE, 1),
(3, 'Crème caramel', 'كريم كراميل', 200.00, TRUE, 2),
(3, 'Glace vanille', 'آيس كريم فانيليا', 180.00, TRUE, 3),
(3, 'Fruits frais', 'فواكه طازجة', 300.00, TRUE, 4);

-- ============================================
-- INSERTION DES PARAMÈTRES PAR DÉFAUT
-- ============================================
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'Station Service & Hôtel', 'text', 'Nom du site', TRUE),
('restaurant_phone', '0796254287', 'text', 'Téléphone du restaurant', TRUE),
('hotel_phone', '0659860108', 'text', 'Téléphone de l\'hôtel', TRUE),
('email_contact', 'contact@stationservice.dz', 'text', 'Email de contact', TRUE),
('address', 'Route Nationale, Algérie', 'text', 'Adresse', TRUE),
('opening_hours', '{"restaurant": "7h - 23h", "cafeteria": "6h - 23h", "pompe": "24h/24", "hotel": "24h/24"}', 'json', 'Horaires d\'ouverture', TRUE),
('whatsapp_number', '213659860108', 'text', 'Numéro WhatsApp', TRUE),
('maintenance_mode', 'false', 'boolean', 'Mode maintenance', FALSE);

-- ============================================
-- VUES UTILES
-- ============================================

-- Vue pour les utilisateurs avec leurs rôles
CREATE OR REPLACE VIEW v_users_with_roles AS
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
    r.description as role_description,
    r.permissions as role_permissions
FROM users u
INNER JOIN roles r ON u.role_id = r.id;

-- Vue pour le menu complet
CREATE OR REPLACE VIEW v_menu_complete AS
SELECT 
    mi.id,
    mi.name,
    mi.name_ar,
    mi.description,
    mi.description_ar,
    mi.price,
    mi.image_url,
    mi.is_available,
    mi.is_featured,
    mi.display_order,
    mc.id as category_id,
    mc.name as category_name,
    mc.name_ar as category_name_ar,
    mc.icon as category_icon
FROM menu_items mi
INNER JOIN menu_categories mc ON mi.category_id = mc.id
WHERE mc.is_active = TRUE
ORDER BY mc.display_order, mi.display_order;

-- ============================================
-- PROCÉDURES STOCKÉES
-- ============================================

-- Procédure pour nettoyer les sessions expirées
DELIMITER //
CREATE PROCEDURE clean_expired_sessions()
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- Procédure pour obtenir les statistiques
DELIMITER //
CREATE PROCEDURE get_dashboard_stats()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_active = TRUE) as active_users,
        (SELECT COUNT(*) FROM menu_items WHERE is_available = TRUE) as available_items,
        (SELECT COUNT(*) FROM announcements WHERE is_active = TRUE 
         AND (end_date IS NULL OR end_date > NOW())) as active_announcements,
        (SELECT COUNT(*) FROM reservations WHERE status = 'pending') as pending_reservations,
        (SELECT COUNT(*) FROM reservations WHERE DATE(reservation_date) = CURDATE()) as today_reservations;
END //
DELIMITER ;

-- ============================================
-- ÉVÉNEMENTS PLANIFIÉS
-- ============================================

-- Activer le planificateur d'événements
SET GLOBAL event_scheduler = ON;

-- Nettoyage automatique des sessions expirées (toutes les heures)
CREATE EVENT IF NOT EXISTS clean_sessions_event
ON SCHEDULE EVERY 1 HOUR
DO
    CALL clean_expired_sessions();

-- Désactiver automatiquement les annonces expirées (tous les jours à minuit)
CREATE EVENT IF NOT EXISTS deactivate_expired_announcements
ON SCHEDULE EVERY 1 DAY
STARTS (CURDATE() + INTERVAL 1 DAY)
DO
    UPDATE announcements 
    SET is_active = FALSE 
    WHERE end_date IS NOT NULL AND end_date < NOW();

-- ============================================
-- INDEX SUPPLÉMENTAIRES POUR PERFORMANCE
-- ============================================

-- Index composites pour les requêtes fréquentes
CREATE INDEX idx_menu_category_available ON menu_items(category_id, is_available);
CREATE INDEX idx_announcement_active_dates ON announcements(is_active, start_date, end_date);
CREATE INDEX idx_reservation_type_status ON reservations(type, status);

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger pour logger la création d'utilisateur
DELIMITER //
CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
    VALUES (NEW.created_by, 'USER_CREATED', 'user', NEW.id, 
            JSON_OBJECT('username', NEW.username, 'email', NEW.email, 'role_id', NEW.role_id));
END //
DELIMITER ;

-- Trigger pour logger la modification du menu
DELIMITER //
CREATE TRIGGER after_menu_update
AFTER UPDATE ON menu_items
FOR EACH ROW
BEGIN
    IF NEW.updated_by IS NOT NULL THEN
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
        VALUES (NEW.updated_by, 'MENU_UPDATED', 'menu_item', NEW.id,
                JSON_OBJECT('name', NEW.name, 'price', NEW.price, 'is_available', NEW.is_available));
    END IF;
END //
DELIMITER ;

-- ============================================
-- FIN DU SCRIPT
-- ============================================

-- Afficher un message de succès
SELECT 'Base de données initialisée avec succès!' as message;
SELECT 'Super Admin créé - Username: superadmin | Password: SuperAdmin2026!' as credentials;