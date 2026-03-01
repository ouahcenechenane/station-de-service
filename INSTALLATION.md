# 🚀 GUIDE D'INSTALLATION
## Station Service & Hôtel - Système de Gestion

---

## 📋 TABLE DES MATIÈRES

1. [Prérequis](#prérequis)
2. [Installation Rapide](#installation-rapide)
3. [Configuration Détaillée](#configuration-détaillée)
4. [Premier Démarrage](#premier-démarrage)
5. [Résolution de Problèmes](#résolution-de-problèmes)

---

## 🔧 PRÉREQUIS

### Serveur

- **Serveur Web**: Apache 2.4+ ou Nginx 1.18+
- **PHP**: Version 7.4+ (PHP 8.0+ recommandé)
- **MySQL**: Version 5.7+ ou MariaDB 10.3+
- **Extensions PHP requises**:
  - PDO
  - pdo_mysql
  - mbstring
  - json
  - openssl
  - session

### Vérifier PHP et extensions

```bash
php -v
php -m | grep -E 'pdo|mysql|mbstring|json|openssl'
```

---

## ⚡ INSTALLATION RAPIDE

### Étape 1: Cloner ou Télécharger le Projet

```bash
# Placer les fichiers dans le dossier web
cd /var/www/html/station-service
# ou
cd C:/xampp/htdocs/station-service
```

### Étape 2: Créer la Base de Données

```bash
# Se connecter à MySQL
mysql -u root -p

# Exécuter le script d'initialisation
mysql -u root -p < config/init_database.sql
```

**OU** via phpMyAdmin:
1. Créer une nouvelle base de données nommée `station_service_db`
2. Importer le fichier `config/init_database.sql`

### Étape 3: Configurer la Connexion

Éditer le fichier `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'station_service_db');
define('DB_USER', 'root');           // Votre utilisateur MySQL
define('DB_PASS', '');               // Votre mot de passe MySQL
```

⚠️ **IMPORTANT:** Changer la clé secrète:

```php
define('JWT_SECRET_KEY', 'GÉNÉRER_UNE_CLÉ_ALÉATOIRE_ICI');
```

Pour générer une clé sécurisée:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Étape 4: Configuration Apache (si nécessaire)

Créer un fichier `.htaccess` à la racine:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Bloquer l'accès au fichier de config
<Files "database.php">
    Order allow,deny
    Deny from all
</Files>

# Headers de sécurité
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
```

### Étape 5: Permissions (Linux/Mac)

```bash
# Rendre les fichiers lisibles
chmod 755 api/
chmod 644 api/*.php
chmod 755 config/
chmod 644 config/*.php

# Vérifier que le serveur web peut lire
chown -R www-data:www-data .
```

---

## 🔐 PREMIER DÉMARRAGE

### Accès au Site

- **Site Public**: `http://localhost/station-service/index.html`
- **Page de Connexion Admin**: `http://localhost/station-service/admin-login.html`

### Identifiants Super Admin par Défaut

```
Username: superadmin
Password: SuperAdmin2026!
Email: superadmin@stationservice.dz
```

⚠️ **CRITIQUE**: Changez immédiatement ce mot de passe après la première connexion!

### Tester l'API

```bash
# Test de l'API (devrait retourner une erreur d'authentification)
curl http://localhost/station-service/api/auth.php?action=verify
```

---

## 🛠️ CONFIGURATION DÉTAILLÉE

### Structure des Fichiers

```
station-service/
├── api/                    # APIs REST
│   ├── auth.php           # Authentification
│   ├── users.php          # Gestion utilisateurs
│   └── menu.php           # Gestion menu
├── config/                # Configuration
│   ├── database.php       # Connexion DB
│   └── init_database.sql  # Script initialisation
├── public/                # Fichiers publics
│   ├── index.html
│   ├── admin-login.html
│   ├── admin-panel.html
│   ├── reservation.html
│   ├── style.css
│   └── script.js
└── .htaccess             # Configuration Apache
```

### Configuration MySQL Avancée

Pour de meilleures performances, ajuster `my.cnf` ou `my.ini`:

```ini
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 32M
query_cache_type = 1
```

### Configuration PHP Recommandée

Éditer `php.ini`:

```ini
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M
session.gc_maxlifetime = 3600

# Activer OPcache (production)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
```

---

## 🧪 VÉRIFICATION DE L'INSTALLATION

### Checklist Post-Installation

- [ ] Base de données créée et accessible
- [ ] Fichier `config/database.php` correctement configuré
- [ ] Clé secrète JWT modifiée
- [ ] Connexion super admin fonctionnelle
- [ ] API `auth.php` répond aux requêtes
- [ ] API `menu.php` retourne le menu
- [ ] Permissions fichiers correctes (Linux)

### Tests de Connexion

#### 1. Tester la Connexion à la DB

Créer un fichier `test_db.php`:

```php
<?php
require_once 'config/database.php';

try {
    $db = getDB();
    echo "✅ Connexion réussie à la base de données!";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "\n✅ Nombre d'utilisateurs: " . $result['count'];
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>
```

Exécuter:
```bash
php test_db.php
```

#### 2. Tester l'API Auth

```bash
# Test de connexion
curl -X POST http://localhost/station-service/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"superadmin","password":"SuperAdmin2026!"}'
```

Devrait retourner un token.

#### 3. Tester l'API Menu (Public)

```bash
curl http://localhost/station-service/api/menu.php?action=get
```

Devrait retourner le menu du restaurant.

---

## 🐛 RÉSOLUTION DE PROBLÈMES

### Problème 1: Erreur de Connexion à la Base de Données

**Symptôme**: `Erreur de connexion à la base de données`

**Solutions**:
1. Vérifier les identifiants dans `config/database.php`
2. Vérifier que MySQL est démarré:
   ```bash
   # Linux
   sudo systemctl status mysql
   
   # Windows (XAMPP)
   # Vérifier dans le panneau XAMPP
   ```
3. Vérifier que l'utilisateur a les permissions:
   ```sql
   GRANT ALL PRIVILEGES ON station_service_db.* TO 'root'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Problème 2: Page Blanche

**Symptôme**: Page blanche sans erreur

**Solutions**:
1. Activer l'affichage des erreurs PHP:
   ```php
   // Ajouter en haut de database.php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
2. Vérifier les logs PHP:
   ```bash
   # Linux
   tail -f /var/log/apache2/error.log
   
   # XAMPP
   # Voir dans xampp/apache/logs/error.log
   ```

### Problème 3: Erreur 404 sur les API

**Symptôme**: `404 Not Found` sur `/api/auth.php`

**Solutions**:
1. Vérifier que `mod_rewrite` est activé (Apache):
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
2. Vérifier la configuration `.htaccess`
3. Vérifier les chemins dans les appels API

### Problème 4: Session Expirée Immédiatement

**Symptôme**: Déconnexion automatique après connexion

**Solutions**:
1. Vérifier la configuration de session dans `php.ini`:
   ```ini
   session.gc_maxlifetime = 3600
   ```
2. Vérifier que les cookies sont acceptés
3. Vérifier la configuration de `SESSION_LIFETIME` dans `database.php`

### Problème 5: Erreur de Permissions (Linux)

**Symptôme**: `Permission denied` dans les logs

**Solutions**:
```bash
# Donner les bonnes permissions
sudo chown -R www-data:www-data /var/www/html/station-service
sudo chmod 755 /var/www/html/station-service
sudo find /var/www/html/station-service -type f -exec chmod 644 {} \;
sudo find /var/www/html/station-service -type d -exec chmod 755 {} \;
```

### Problème 6: Caractères Arabes Mal Affichés

**Symptôme**: ������ à la place des caractères arabes

**Solutions**:
1. Vérifier l'encodage de la DB:
   ```sql
   ALTER DATABASE station_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Vérifier les headers PHP:
   ```php
   header('Content-Type: application/json; charset=utf-8');
   ```
3. Vérifier l'encodage des fichiers HTML (doit être UTF-8)

---

## 📊 VÉRIFIER QUE TOUT FONCTIONNE

### Workflow de Test Complet

1. **Accéder au site public**
   - URL: `http://localhost/station-service/index.html`
   - Vérifier que le slider fonctionne
   - Vérifier que le menu s'affiche

2. **Se connecter en tant qu'admin**
   - URL: `http://localhost/station-service/admin-login.html`
   - Username: `superadmin`
   - Password: `SuperAdmin2026!`
   - Devrait rediriger vers le panneau admin

3. **Tester la gestion du menu**
   - Ajouter un nouvel élément
   - Modifier un élément existant
   - Supprimer un élément
   - Vérifier que les changements apparaissent sur le site public

4. **Tester la gestion des utilisateurs**
   - Créer un nouvel utilisateur (Admin ou Éditeur)
   - Se déconnecter
   - Se reconnecter avec le nouvel utilisateur
   - Vérifier les permissions

---

## 🔐 SÉCURITÉ POST-INSTALLATION

### Actions Critiques

1. **Changer le mot de passe super admin**:
   ```sql
   UPDATE users 
   SET password_hash = '$2y$10$...' 
   WHERE username = 'superadmin';
   ```
   Ou via l'interface admin.

2. **Changer la clé secrète JWT** dans `config/database.php`

3. **Supprimer le fichier `test_db.php`** si créé

4. **Configurer HTTPS** (production):
   ```apache
   # Forcer HTTPS
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

5. **Limiter les tentatives de connexion** (recommandé):
   - Implémenter un rate limiting
   - Bloquer les IP après X tentatives

---

## 📞 SUPPORT

### En cas de problème

1. Consulter les logs:
   - Logs Apache: `/var/log/apache2/error.log`
   - Logs MySQL: `/var/log/mysql/error.log`
   - Logs PHP: vérifier `error_log` dans `php.ini`

2. Vérifier la documentation complète:
   - `SPECIFICATIONS_TECHNIQUES.md`

3. Contacter le support:
   - Email: support@stationservice.dz
   - Téléphone: 0796254287 (Restaurant) / 0659860108 (Hôtel)

---

## 🎉 C'EST TERMINÉ!

Votre système de gestion est maintenant installé et opérationnel!

**Prochaines étapes**:
1. Personnaliser le contenu du site
2. Ajouter vos plats au menu
3. Créer des comptes pour vos administrateurs
4. Configurer les paramètres (téléphones, emails, etc.)

**N'oubliez pas**:
- Faire des sauvegardes régulières
- Surveiller les logs
- Mettre à jour les dépendances
- Former vos équipes

---

*Dernière mise à jour: 28 janvier 2026*