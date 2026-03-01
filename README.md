# 🚗 Station Service & Hôtel - Système de Gestion Complet

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![License](https://img.shields.io/badge/license-Proprietary-red)

## 📝 Description

Système de gestion complet pour une station-service avec restaurant, cafétéria et hôtel. Comprend une interface publique moderne et un panneau d'administration multi-niveaux avec gestion des rôles.

## ✨ Fonctionnalités Principales

### 🌐 Site Public
- ✅ Page d'accueil avec slider automatique
- ✅ Présentation des services (Pompe, Restaurant, Cafétéria, Toilettes, Hôtel)
- ✅ Menu restaurant dynamique
- ✅ Système de réservations (Restaurant & Hôtel)
- ✅ Design responsive et animations modernes
- ✅ Support bilingue (Français/Arabe)

### 🔐 Panneau d'Administration
- ✅ **Système de rôles multi-niveaux**
  - Super Administrateur (contrôle total)
  - Administrateur (gestion contenu + réservations)
  - Éditeur (gestion menu uniquement)
  
- ✅ **Gestion des utilisateurs**
  - Création/modification/suppression
  - Attribution des rôles
  - Gestion des permissions
  
- ✅ **Gestion du menu restaurant**
  - CRUD complet sur les éléments
  - Catégories personnalisables
  - Prix et descriptions
  - Support multilingue
  
- ✅ **Système de logs**
  - Traçabilité complète des actions
  - IP tracking
  - Historique des modifications

### 🔒 Sécurité
- ✅ Authentification par token sécurisé
- ✅ Mots de passe hashés (bcrypt)
- ✅ Protection SQL injection (requêtes préparées)
- ✅ Sessions avec expiration
- ✅ Logs d'activité complets
- ✅ Permissions granulaires

## 📦 Contenu du Package

```
📁 station-service/
├── 📁 api/                          # APIs REST
│   ├── auth.php                     # API Authentification
│   ├── users.php                    # API Gestion Utilisateurs
│   └── menu.php                     # API Gestion Menu
│
├── 📁 config/                       # Configuration
│   ├── database.php                 # Configuration Base de Données
│   └── init_database.sql            # Script Initialisation DB
│
├── 📁 public/ (vos fichiers existants)
│   ├── index.html                   # Page d'accueil
│   ├── admin-login.html             # Page de connexion admin
│   ├── admin-panel.html             # Panneau admin
│   ├── reservation.html             # Page réservation
│   ├── style.css                    # Styles
│   ├── script.js                    # Scripts site public
│   └── admin-script.js              # Scripts admin
│
├── 📄 INSTALLATION.md               # Guide d'installation détaillé
├── 📄 SPECIFICATIONS_TECHNIQUES.md  # Documentation technique complète
└── 📄 README.md                     # Ce fichier
```

## 🚀 Installation Rapide

### Prérequis
- PHP 7.4+ (8.0+ recommandé)
- MySQL 5.7+ ou MariaDB 10.3+
- Apache/Nginx
- Extensions PHP: PDO, pdo_mysql, mbstring, json, openssl

### Installation en 4 étapes

#### 1️⃣ Créer la base de données
```bash
mysql -u root -p < config/init_database.sql
```

#### 2️⃣ Configurer la connexion
Éditer `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'station_service_db');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_password');
define('JWT_SECRET_KEY', 'générer_clé_aléatoire');
```

#### 3️⃣ Configurer Apache
Créer `.htaccess` pour bloquer l'accès aux fichiers sensibles.

#### 4️⃣ Premier démarrage
- Site: `http://localhost/station-service/`
- Admin: `http://localhost/station-service/admin-login.html`

**Identifiants par défaut:**
```
Username: superadmin
Password: SuperAdmin2026!
```

⚠️ **IMPORTANT:** Changez ce mot de passe immédiatement!

👉 **Pour plus de détails, consultez [INSTALLATION.md](INSTALLATION.md)**

## 📚 Documentation

- **[INSTALLATION.md](INSTALLATION.md)** - Guide d'installation complet avec résolution de problèmes
- **[SPECIFICATIONS_TECHNIQUES.md](SPECIFICATIONS_TECHNIQUES.md)** - Documentation technique détaillée

## 🎯 Structure de la Base de Données

### Tables Principales

| Table | Description |
|-------|-------------|
| `users` | Utilisateurs et administrateurs |
| `roles` | Rôles et permissions |
| `menu_categories` | Catégories du menu |
| `menu_items` | Éléments du menu restaurant |
| `announcements` | Annonces et promotions |
| `reservations` | Réservations (restaurant & hôtel) |
| `sessions` | Sessions utilisateurs |
| `activity_logs` | Logs d'activité |
| `settings` | Paramètres du site |

## 🔌 API REST

### Endpoints Disponibles

#### Authentification (`/api/auth.php`)
```bash
POST   ?action=login           # Connexion
POST   ?action=logout          # Déconnexion
GET    ?action=verify          # Vérifier token
POST   ?action=change-password # Changer mot de passe
```

#### Utilisateurs (`/api/users.php`)
```bash
GET    ?action=list            # Lister utilisateurs
POST   ?action=create          # Créer utilisateur
POST   ?action=update&id=X     # Modifier utilisateur
POST   ?action=delete&id=X     # Supprimer utilisateur
GET    ?action=roles           # Lister rôles
```

#### Menu (`/api/menu.php`)
```bash
GET    ?action=get             # Menu complet (public)
GET    ?action=list            # Liste admin
POST   ?action=create          # Créer élément
POST   ?action=update&id=X     # Modifier élément
POST   ?action=delete&id=X     # Supprimer élément
GET    ?action=categories      # Lister catégories
```

### Exemple d'Utilisation

```javascript
// Connexion
const response = await fetch('/api/auth.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        username: 'superadmin',
        password: 'SuperAdmin2026!'
    })
});
const data = await response.json();
const token = data.data.token;

// Requête authentifiée
const users = await fetch('/api/users.php?action=list', {
    headers: { 'Authorization': `Bearer ${token}` }
});
```

## 🔐 Système de Permissions

### Rôles Prédéfinis

| Rôle | Permissions |
|------|------------|
| **Super Admin** | Accès complet (users, roles, menu, announcements, reservations, settings, logs) |
| **Admin** | Gestion contenu (menu, announcements, reservations) + lecture users/settings |
| **Éditeur** | Gestion menu + création/modification announcements uniquement |

### Format des Permissions (JSON)
```json
{
    "users": ["create", "read", "update", "delete"],
    "menu": ["manage"],
    "announcements": ["manage"],
    "reservations": ["manage"],
    "settings": ["read"]
}
```

## 🛡️ Sécurité

### Mesures Implémentées
- ✅ **Authentification par token** (64 caractères hexadécimaux)
- ✅ **Hachage bcrypt** pour les mots de passe
- ✅ **Requêtes préparées** (protection SQL injection)
- ✅ **Validation** de toutes les entrées
- ✅ **Sessions sécurisées** avec expiration
- ✅ **Logs d'activité** complets
- ✅ **IP tracking** pour chaque action
- ✅ **Headers de sécurité** HTTP

### Recommandations Production
1. Activer HTTPS
2. Configurer CSP (Content Security Policy)
3. Limiter les tentatives de connexion
4. Configurer les sauvegardes automatiques
5. Monitorer les logs

## 📊 Données Initiales

### Super Admin
- **Username:** superadmin
- **Email:** superadmin@stationservice.dz
- **Role:** Super Administrateur

### Menu Restaurant (Exemples)
- 4 plats principaux (Couscous, Tajine, Pizza, Poulet)
- 4 boissons (Jus, Coca, Eau, Café)
- 4 desserts (Baklava, Crème caramel, Glace, Fruits)

### Paramètres
- Téléphones (Restaurant: 0796254287, Hôtel: 0659860108)
- Email: contact@stationservice.dz
- WhatsApp: 213659860108

## 🔧 Configuration Avancée

### Performance MySQL
```ini
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 32M
```

### Configuration PHP
```ini
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
opcache.enable = 1
```

## 🐛 Dépannage

### Problèmes Courants

**Erreur de connexion DB:**
- Vérifier les identifiants dans `config/database.php`
- Vérifier que MySQL est démarré

**Page blanche:**
- Activer `display_errors` dans PHP
- Consulter les logs Apache/PHP

**Caractères arabes mal affichés:**
- Vérifier l'encodage UTF-8 de la DB
- Vérifier les headers `Content-Type`

👉 **Guide complet dans [INSTALLATION.md](INSTALLATION.md)**

## 📈 Évolutions Futures

### Phase 2
- [ ] Notifications email
- [ ] Upload d'images
- [ ] Tableau de bord avec statistiques
- [ ] Export PDF/Excel

### Phase 3
- [ ] Application mobile
- [ ] Paiement en ligne
- [ ] Programme de fidélité
- [ ] WhatsApp Business API

## 🤝 Support

**Contact:**
- Restaurant: 0796254287
- Hôtel: 0659860108
- Email: contact@stationservice.dz

**Documentation:**
- Installation: [INSTALLATION.md](INSTALLATION.md)
- Technique: [SPECIFICATIONS_TECHNIQUES.md](SPECIFICATIONS_TECHNIQUES.md)

## 📝 License

Propriétaire - Station Service & Hôtel © 2026

---

**Développé avec ❤️ pour Station Service & Hôtel**

*Dernière mise à jour: 28 janvier 2026*