# SPÉCIFICATIONS TECHNIQUES
## Système de Gestion pour Station Service & Hôtel

**Version:** 1.0.0  
**Date:** 28 janvier 2026  
**Statut:** Document de référence

---

## 1. VUE D'ENSEMBLE

### 1.1 Objectif du Système
Développer un système web complet pour la gestion d'une station-service avec restaurant, cafétéria et hôtel, incluant :
- Interface publique pour les clients
- Panneau d'administration multi-niveaux
- Gestion des utilisateurs avec système de rôles
- Gestion dynamique du menu restaurant
- Système de réservations
- Gestion des annonces

### 1.2 Architecture Globale
```
┌─────────────────────────────────────────────────────────────┐
│                    COUCHE PRÉSENTATION                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Site Public  │  │ Admin Panel  │  │ Super Admin  │      │
│  │  (HTML/CSS/  │  │   (HTML/CSS/ │  │   Dashboard  │      │
│  │     JS)      │  │      JS)     │  │              │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     COUCHE API (PHP)                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │   Auth   │ │  Users   │ │   Menu   │ │ Settings │       │
│  │   API    │ │   API    │ │   API    │ │   API    │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│               BASE DE DONNÉES (MySQL)                        │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Users │ Roles │ Menu │ Reservations │ Settings │   │   │
│  │  Sessions │ Activity Logs │ Announcements         │   │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. SPÉCIFICATIONS FONCTIONNELLES

### 2.1 Système de Gestion des Utilisateurs

#### 2.1.1 Rôles et Permissions

**Super Administrateur**
- Permissions complètes sur tous les modules
- Gestion des utilisateurs (création, modification, suppression)
- Attribution des rôles
- Accès aux logs d'activité
- Gestion des paramètres système

**Administrateur**
- Gestion du contenu (menu, annonces)
- Gestion des réservations
- Visualisation des utilisateurs (lecture seule)
- Consultation des paramètres

**Éditeur**
- Gestion du menu restaurant uniquement
- Création et modification des annonces
- Pas d'accès aux utilisateurs ni aux paramètres

#### 2.1.2 Authentification et Sécurité

**Mécanisme d'Authentification:**
- Système basé sur token (session token)
- Durée de session configurable (défaut: 1 heure)
- Vérification à chaque requête API
- Déconnexion automatique après expiration

**Sécurité des Mots de Passe:**
- Algorithme: bcrypt (PASSWORD_BCRYPT)
- Longueur minimale: 8 caractères
- Hachage avec salt automatique
- Pas de stockage en clair

**Protection des Sessions:**
- Token unique de 64 caractères (hexadécimal)
- Stockage de l'IP et User-Agent
- Nettoyage automatique des sessions expirées
- Événement planifié (toutes les heures)

### 2.2 Gestion du Menu Restaurant

#### 2.2.1 Structure du Menu

**Catégories:**
- ID unique
- Nom (français et arabe)
- Icône FontAwesome
- Ordre d'affichage
- Statut actif/inactif

**Éléments du Menu:**
- ID unique
- Catégorie associée
- Nom (français et arabe)
- Description (optionnelle, français et arabe)
- Prix (DECIMAL 10,2)
- URL de l'image (optionnelle)
- Disponibilité (booléen)
- Statut "à la une" (booléen)
- Ordre d'affichage
- Horodatage de création/modification
- ID de l'utilisateur ayant effectué la modification

#### 2.2.2 Opérations CRUD

**Création:**
- Validation des champs obligatoires
- Attribution automatique de l'ordre
- Enregistrement de l'auteur

**Lecture:**
- API publique pour le site
- API admin pour gestion complète
- Filtrage par catégorie/disponibilité

**Mise à jour:**
- Modification partielle autorisée
- Traçabilité (updated_by)
- Validation des données

**Suppression:**
- Suppression en cascade des dépendances
- Log de l'action
- Confirmation requise

### 2.3 Système de Réservations

#### 2.3.1 Types de Réservations

**Restaurant:**
- Nom du client
- Téléphone
- Email (optionnel)
- Nombre de personnes
- Date et heure
- Détails spéciaux

**Hôtel:**
- Nom du client
- Téléphone
- Email (optionnel)
- Nombre de personnes
- Date d'arrivée (check-in)
- Date de départ (check-out)
- Type de chambre
- Détails spéciaux

#### 2.3.2 Statuts de Réservation
- **Pending** (En attente): Nouvelle réservation
- **Confirmed** (Confirmée): Validée par un admin
- **Cancelled** (Annulée): Annulation client ou admin
- **Completed** (Terminée): Service effectué

### 2.4 Gestion des Annonces

#### 2.4.1 Structure des Annonces

**Champs:**
- Titre (français et arabe)
- Contenu (français et arabe)
- Type (info, warning, success, promotion)
- Image (URL, optionnelle)
- Date de début
- Date de fin (optionnelle)
- Affichage page d'accueil (booléen)
- Priorité (ordre d'affichage)
- Statut actif/inactif

#### 2.4.2 Fonctionnalités
- Création avec prévisualisation
- Planification (dates début/fin)
- Désactivation automatique après expiration
- Affichage priorisé sur la page d'accueil

---

## 3. SPÉCIFICATIONS TECHNIQUES DE LA BASE DE DONNÉES

### 3.1 Schéma des Tables Principales

#### Table: users
```sql
CREATE TABLE users (
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
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### Table: roles
```sql
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Table: menu_items
```sql
CREATE TABLE menu_items (
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
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 3.2 Index et Optimisation

**Index Composites:**
```sql
CREATE INDEX idx_menu_category_available ON menu_items(category_id, is_available);
CREATE INDEX idx_announcement_active_dates ON announcements(is_active, start_date, end_date);
CREATE INDEX idx_reservation_type_status ON reservations(type, status);
```

**Index Simples:**
- Tous les champs de clés étrangères
- Champs fréquemment utilisés dans les WHERE
- Champs utilisés pour les JOIN

### 3.3 Triggers et Événements

**Triggers:**
- `after_user_insert`: Log de création d'utilisateur
- `after_menu_update`: Log de modification du menu

**Événements Planifiés:**
- `clean_sessions_event`: Nettoyage des sessions (1x/heure)
- `deactivate_expired_announcements`: Désactivation des annonces expirées (quotidien)

### 3.4 Vues Matérialisées

**v_users_with_roles:**
```sql
CREATE VIEW v_users_with_roles AS
SELECT u.*, r.name as role_name, r.description as role_description, r.permissions
FROM users u
INNER JOIN roles r ON u.role_id = r.id;
```

**v_menu_complete:**
```sql
CREATE VIEW v_menu_complete AS
SELECT mi.*, mc.name as category_name, mc.name_ar as category_name_ar, mc.icon as category_icon
FROM menu_items mi
INNER JOIN menu_categories mc ON mi.category_id = mc.id
WHERE mc.is_active = TRUE
ORDER BY mc.display_order, mi.display_order;
```

---

## 4. API REST - SPÉCIFICATIONS

### 4.1 Endpoints d'Authentification

**Base URL:** `/api/auth.php`

#### POST /api/auth.php?action=login
Connexion utilisateur

**Request:**
```json
{
    "username": "admin",
    "password": "monmotdepasse"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Connexion réussie",
    "data": {
        "token": "a1b2c3d4...",
        "expires_at": "2026-01-28 15:30:00",
        "user": {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com",
            "full_name": "Administrateur",
            "role": "admin",
            "permissions": {...}
        }
    }
}
```

**Codes d'Erreur:**
- 400: Données manquantes
- 401: Identifiants incorrects
- 500: Erreur serveur

#### POST /api/auth.php?action=logout
Déconnexion

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Déconnexion réussie"
}
```

#### GET /api/auth.php?action=verify
Vérification du token

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Token valide",
    "data": {
        "user": {...}
    }
}
```

### 4.2 Endpoints de Gestion des Utilisateurs

**Base URL:** `/api/users.php`

**Authentification requise:** Oui (token Bearer)

#### GET /api/users.php?action=list
Lister tous les utilisateurs

**Permission:** users:read

**Response (200):**
```json
{
    "success": true,
    "message": "Liste des utilisateurs",
    "data": {
        "users": [
            {
                "id": 1,
                "username": "admin",
                "email": "admin@example.com",
                "full_name": "Administrateur",
                "phone": "0555123456",
                "is_active": true,
                "last_login": "2026-01-28 14:00:00",
                "created_at": "2026-01-01 10:00:00",
                "role_name": "admin",
                "role_description": "Administrateur"
            }
        ]
    }
}
```

#### POST /api/users.php?action=create
Créer un utilisateur

**Permission:** users:create

**Request:**
```json
{
    "username": "nouveluser",
    "email": "user@example.com",
    "password": "motdepasse123",
    "full_name": "Nom Complet",
    "phone": "0555987654",
    "role_id": 2
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Utilisateur créé avec succès",
    "data": {
        "user_id": 5
    }
}
```

#### POST /api/users.php?action=update&id={id}
Mettre à jour un utilisateur

**Permission:** users:update

**Request:**
```json
{
    "email": "newemail@example.com",
    "full_name": "Nouveau Nom",
    "role_id": 3,
    "is_active": false
}
```

#### POST /api/users.php?action=delete&id={id}
Supprimer un utilisateur

**Permission:** users:delete

**Response (200):**
```json
{
    "success": true,
    "message": "Utilisateur supprimé avec succès"
}
```

#### GET /api/users.php?action=roles
Obtenir la liste des rôles

**Permission:** users:read

**Response (200):**
```json
{
    "success": true,
    "message": "Liste des rôles",
    "data": {
        "roles": [
            {
                "id": 1,
                "name": "super_admin",
                "description": "Super Administrateur"
            }
        ]
    }
}
```

### 4.3 Endpoints de Gestion du Menu

**Base URL:** `/api/menu.php`

#### GET /api/menu.php?action=get
Obtenir le menu complet (public, pas d'auth requise)

**Response (200):**
```json
{
    "success": true,
    "message": "Menu récupéré",
    "data": {
        "menu": [
            {
                "id": 1,
                "name": "Plats Principaux",
                "name_ar": "الأطباق الرئيسية",
                "icon": "fa-plate-wheat",
                "items": [
                    {
                        "id": 1,
                        "name": "Couscous royal",
                        "name_ar": "الكسكس الملكي",
                        "description": null,
                        "price": 1200.00,
                        "image_url": null,
                        "is_featured": false
                    }
                ]
            }
        ]
    }
}
```

#### GET /api/menu.php?action=list
Lister tous les éléments (admin)

**Permission:** menu:read

#### POST /api/menu.php?action=create
Créer un élément

**Permission:** menu:create

**Request:**
```json
{
    "category_id": 1,
    "name": "Nouveau plat",
    "name_ar": "طبق جديد",
    "description": "Description du plat",
    "price": 950.00,
    "is_available": true,
    "is_featured": false
}
```

#### POST /api/menu.php?action=update&id={id}
Mettre à jour un élément

**Permission:** menu:update

#### POST /api/menu.php?action=delete&id={id}
Supprimer un élément

**Permission:** menu:delete

#### GET /api/menu.php?action=categories
Obtenir les catégories

---

## 5. SÉCURITÉ

### 5.1 Prévention des Attaques

**SQL Injection:**
- Utilisation exclusive de requêtes préparées (PDO)
- Paramètres bindés avec typage
- Aucune concaténation directe dans les requêtes

**XSS (Cross-Site Scripting):**
- Échappement de toutes les sorties HTML
- Validation des entrées utilisateur
- Content Security Policy (CSP) recommandée

**CSRF (Cross-Site Request Forgery):**
- Tokens CSRF pour les formulaires
- Vérification de l'origine des requêtes
- SameSite cookie attribute

**Brute Force:**
- Limitation du taux de requêtes
- Blocage après X tentatives échouées
- Captcha optionnel

### 5.2 Gestion des Sessions

**Sécurité des Tokens:**
- Génération cryptographiquement sécurisée (random_bytes)
- Longueur de 64 caractères hexadécimaux
- Stockage sécurisé en base de données
- Expiration configurable

**Tracking:**
- IP address du client
- User-Agent
- Horodatage de création
- Horodatage d'expiration

### 5.3 Logs et Audit

**Activity Logs:**
- Toutes les actions sensibles sont loggées
- Informations trackées :
  * Utilisateur
  * Action effectuée
  * Entité concernée
  * Détails (JSON)
  * IP et User-Agent
  * Timestamp

**Actions Loggées:**
- LOGIN_SUCCESS / LOGIN_FAILED
- USER_CREATED / USER_UPDATED / USER_DELETED
- MENU_ITEM_CREATED / MENU_ITEM_UPDATED / MENU_ITEM_DELETED
- PASSWORD_CHANGED
- LOGOUT

---

## 6. CONFIGURATION ET DÉPLOIEMENT

### 6.1 Prérequis Serveur

**Serveur Web:**
- Apache 2.4+ ou Nginx 1.18+
- Support .htaccess (Apache) ou configuration équivalente
- Mod_rewrite activé

**PHP:**
- Version: 7.4+ (8.0+ recommandé)
- Extensions requises:
  * PDO
  * pdo_mysql
  * mbstring
  * json
  * openssl
  * session

**Base de Données:**
- MySQL 5.7+ ou MariaDB 10.3+
- Encodage: utf8mb4
- Collation: utf8mb4_unicode_ci

**Ressources Minimales:**
- RAM: 512 MB
- Espace disque: 1 GB
- Processeur: 1 cœur

### 6.2 Installation

**Étape 1: Configuration de la base de données**
```bash
mysql -u root -p < config/init_database.sql
```

**Étape 2: Configuration PHP**
Éditer `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'station_service_db');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('JWT_SECRET_KEY', 'générer_une_clé_aléatoire_sécurisée');
```

**Étape 3: Permissions fichiers**
```bash
chmod 755 api/
chmod 644 api/*.php
chmod 755 config/
chmod 644 config/*.php
```

**Étape 4: Configuration Apache**
Créer `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1 [L,QSA]

# Sécurité
<Files "database.php">
    Order allow,deny
    Deny from all
</Files>
```

### 6.3 Compte Super Admin par Défaut

**Identifiants:**
- Username: `superadmin`
- Password: `SuperAdmin2026!`
- Email: superadmin@stationservice.dz

**⚠️ IMPORTANT:** Changer immédiatement le mot de passe en production!

### 6.4 Variables d'Environnement

**Fichier .env (optionnel):**
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=localhost
DB_NAME=station_service_db
DB_USER=db_user
DB_PASS=db_password
SESSION_LIFETIME=3600
JWT_SECRET=votre_clé_secrète
TIMEZONE=Africa/Algiers
```

---

## 7. MAINTENANCE

### 7.1 Tâches Automatisées

**Nettoyage des Sessions (Horaire):**
- Fréquence: Toutes les heures
- Procédure: `clean_expired_sessions()`
- Action: Suppression des sessions expirées

**Désactivation des Annonces (Quotidien):**
- Fréquence: Tous les jours à minuit
- Action: Désactivation des annonces avec end_date dépassée

### 7.2 Sauvegarde

**Base de Données:**
```bash
# Sauvegarde complète
mysqldump -u root -p station_service_db > backup_$(date +%Y%m%d).sql

# Sauvegarde avec compression
mysqldump -u root -p station_service_db | gzip > backup_$(date +%Y%m%d).sql.gz
```

**Recommandations:**
- Sauvegarde quotidienne automatique
- Rétention: 30 jours minimum
- Stockage hors site (cloud)
- Test de restauration mensuel

### 7.3 Monitoring

**Métriques à Surveiller:**
- Taux d'erreurs API (>5% = alerte)
- Temps de réponse moyen (<200ms souhaité)
- Nombre de sessions actives
- Espace disque base de données
- Logs d'erreurs PHP

**Outils Recommandés:**
- New Relic / Datadog pour APM
- Prometheus + Grafana pour métriques
- Sentry pour tracking d'erreurs
- Uptime Robot pour monitoring disponibilité

---

## 8. PERFORMANCES ET OPTIMISATION

### 8.1 Optimisation Base de Données

**Index:**
- Index sur tous les champs de recherche fréquents
- Index composites pour les requêtes multi-colonnes
- Analyse régulière avec `EXPLAIN`

**Requêtes:**
- Utilisation de vues pour requêtes complexes
- Pagination pour grandes listes (LIMIT/OFFSET)
- Éviter les SELECT *

**Caching:**
- Query caching MySQL activé
- Redis recommandé pour cache applicatif
- TTL adapté par type de données

### 8.2 Optimisation PHP

**Configuration php.ini:**
```ini
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 128
```

**Bonnes Pratiques:**
- Autoloading des classes
- Connection pooling
- Lazy loading
- Minification des réponses JSON

### 8.3 Optimisation Frontend

**Assets:**
- Minification CSS/JS
- Compression gzip/brotli
- CDN pour FontAwesome et autres bibliothèques
- Lazy loading des images

**Caching Navigateur:**
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

## 9. TESTS

### 9.1 Tests Unitaires (Recommandé)

**Framework:** PHPUnit

**Couverture Minimale:**
- Authentification: 90%
- Gestion utilisateurs: 85%
- Gestion menu: 85%
- Logs: 70%

### 9.2 Tests d'Intégration

**Scénarios Critiques:**
1. Connexion → Création utilisateur → Déconnexion
2. Connexion → Création élément menu → Modification → Suppression
3. Tentatives de connexion échouées → Verrouillage
4. Session expirée → Redirection login

### 9.3 Tests de Charge

**Outils:** Apache JMeter, Gatling

**Cibles:**
- 100 utilisateurs simultanés
- Temps de réponse < 500ms (P95)
- 0% d'erreurs

---

## 10. DOCUMENTATION DÉVELOPPEUR

### 10.1 Structure des Dossiers

```
project/
├── api/                  # APIs REST
│   ├── auth.php         # Authentification
│   ├── users.php        # Gestion utilisateurs
│   ├── menu.php         # Gestion menu
│   └── ...
├── config/              # Configuration
│   ├── database.php     # Config DB
│   └── init_database.sql # Script initialisation
├── public/              # Fichiers publics
│   ├── index.html
│   ├── admin-login.html
│   ├── admin-panel.html
│   ├── reservation.html
│   ├── style.css
│   └── script.js
└── logs/                # Logs applicatifs
```

### 10.2 Conventions de Code

**PHP:**
- PSR-12 pour le style
- CamelCase pour les classes
- snake_case pour les variables/fonctions
- Documentation PHPDoc

**JavaScript:**
- camelCase pour variables/fonctions
- PascalCase pour classes
- Utilisation de const/let (pas var)
- JSDoc pour fonctions complexes

**SQL:**
- MAJUSCULES pour mots-clés SQL
- snake_case pour noms de tables/colonnes
- Indentation pour lisibilité

### 10.3 Gestion des Erreurs

**Niveaux de Log:**
- ERROR: Erreurs critiques
- WARNING: Avertissements
- INFO: Informations
- DEBUG: Debug (désactivé en production)

**Format de Log:**
```
[2026-01-28 14:30:00] ERROR: Erreur login - User: admin - IP: 192.168.1.1
```

---

## 11. ÉVOLUTIONS FUTURES

### 11.1 Phase 2 (Court Terme)

- [ ] Système de notifications email
- [ ] Gestion des images (upload)
- [ ] Statistiques et tableau de bord
- [ ] Export de données (PDF, Excel)
- [ ] Multi-langue complet (FR/AR)

### 11.2 Phase 3 (Moyen Terme)

- [ ] Application mobile (React Native)
- [ ] Système de paiement en ligne
- [ ] Programme de fidélité
- [ ] Système de commande en ligne
- [ ] Intégration WhatsApp Business API

### 11.3 Phase 4 (Long Terme)

- [ ] Intelligence artificielle (recommandations)
- [ ] Système de réservation automatisé
- [ ] Analytics avancés
- [ ] Intégration comptabilité
- [ ] API publique pour partenaires

---

## 12. CONTACT ET SUPPORT

**Développeur Principal:**
- Nom: [À compléter]
- Email: [À compléter]
- Téléphone: [À compléter]

**Support Technique:**
- Email: support@stationservice.dz
- Heures: 9h-18h (lun-ven)

**Ressources:**
- Documentation: [URL]
- Wiki: [URL]
- Issue Tracker: [URL]

---

## ANNEXES

### A. Codes de Statut HTTP Utilisés

- **200 OK**: Requête réussie
- **201 Created**: Ressource créée
- **400 Bad Request**: Données invalides
- **401 Unauthorized**: Authentification requise/échec
- **403 Forbidden**: Permission refusée
- **404 Not Found**: Ressource introuvable
- **500 Internal Server Error**: Erreur serveur

### B. Format JSON des Permissions

```json
{
    "users": ["create", "read", "update", "delete"],
    "roles": ["manage"],
    "menu": ["manage"],
    "announcements": ["manage"],
    "reservations": ["manage"],
    "settings": ["manage"],
    "logs": ["read"]
}
```

### C. Checklist de Déploiement

- [ ] Base de données créée et initialisée
- [ ] Configuration database.php mise à jour
- [ ] Clé secrète JWT modifiée
- [ ] Mot de passe super admin changé
- [ ] Permissions fichiers correctes
- [ ] .htaccess configuré
- [ ] Sauvegarde automatique configurée
- [ ] Monitoring mis en place
- [ ] Tests de charge effectués
- [ ] Documentation à jour

---

**FIN DU DOCUMENT**

*Ce document est maintenu et mis à jour régulièrement. Dernière révision: 28 janvier 2026*