// ===================================
// super-admin-script.js — VERSION FINALE
// ===================================

// ⚠️  IMPORTANT : Accédez au site via XAMPP uniquement :
// http://localhost/station-de-service/public/super-admin-panel.html
// N'utilisez PAS Live Server (127.0.0.1:5500)

const API_BASE_URL = 'http://localhost/station-de-service/api';

// ===================================
// VÉRIFICATION SESSION SUPER ADMIN
// ===================================
async function checkSuperAdmin() {
    const token = sessionStorage.getItem('adminToken');

    if (!token) {
        window.location.href = 'admin-login.html';
        return;
    }

    try {
        const res = await fetch(`${API_BASE_URL}/auth.php?action=verify`, {
            method: 'GET',
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        console.log('🔍 Verify response:', data);

        if (!data.success) {
            sessionStorage.removeItem('adminToken');
            window.location.href = 'admin-login.html';
            return;
        }

        // Vérification robuste du rôle (accepte plusieurs formats)
        const user = data.data.user;
        const role = String(user.role || user.role_name || '').trim().toLowerCase().replace(/\s+/g, '_');
        console.log('🔐 Rôle détecté:', role);

        if (role !== 'super_admin') {
            alert('Accès refusé - Super Admin uniquement\nVotre rôle détecté : ' + role);
            sessionStorage.removeItem('adminToken');
            window.location.href = 'admin-login.html';
        }

    } catch (err) {
        console.error('Erreur CORS ou réseau. Utilisez localhost pas Live Server:', err);
        alert(
            '❌ Erreur de connexion au serveur.\n\n' +
            'Vous devez accéder au site via XAMPP :\n' +
            'http://localhost/station-de-service/public/admin-login.html\n\n' +
            'Fermez Live Server et utilisez cette URL.'
        );
        sessionStorage.removeItem('adminToken');
        window.location.href = 'admin-login.html';
    }
}

// ===================================
// DÉCONNEXION
// ===================================
document.getElementById('logoutBtn')?.addEventListener('click', async () => {
    if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return;

    const token = sessionStorage.getItem('adminToken');
    if (token) {
        try {
            await fetch(`${API_BASE_URL}/auth.php?action=logout`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
            });
        } catch (err) { console.error(err); }
    }

    sessionStorage.removeItem('adminToken');
    sessionStorage.removeItem('redirecting');
    sessionStorage.removeItem('userInfo');
    window.location.href = 'logout.html';
});

// ===================================
// GESTION DES ONGLETS
// ===================================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`${tab}-tab`).classList.add('active');

        if (tab === 'approvals') loadPendingApprovals();
        if (tab === 'admins') loadAdmins();
        if (tab === 'menu') loadAllMenu();
    });
});

// ===================================
// STATISTIQUES
// ===================================
async function loadStats() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/approvals.php?action=stats`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        if (data.success) {
            const s = data.data.stats;
            document.getElementById('statsGrid').innerHTML = `
                <div class="stat-card" style="border-left:4px solid #ffc107">
                    <div class="stat-icon" style="color:#ffc107"><i class="fas fa-clock"></i></div>
                    <div class="stat-value">${s.en_attente || 0}</div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-card" style="border-left:4px solid #28a745">
                    <div class="stat-icon" style="color:#28a745"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value">${s.approuve || 0}</div>
                    <div class="stat-label">Approuvées</div>
                </div>
                <div class="stat-card" style="border-left:4px solid #dc3545">
                    <div class="stat-icon" style="color:#dc3545"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value">${s.refuse || 0}</div>
                    <div class="stat-label">Refusées</div>
                </div>
                <div class="stat-card" style="border-left:4px solid #667eea">
                    <div class="stat-icon" style="color:#667eea"><i class="fas fa-utensils"></i></div>
                    <div class="stat-value">${s.total || 0}</div>
                    <div class="stat-label">Total</div>
                </div>`;
        }
    } catch (err) { console.error('loadStats error:', err); }
}

// ===================================
// PUBLICATIONS EN ATTENTE
// ===================================
async function loadPendingApprovals() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/approvals.php?action=list-pending`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();

        if (data.success) {
            const items = data.data.items;
            const container = document.getElementById('pendingList');

            if (items.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>Aucune publication en attente</p>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th>Nom</th><th>Catégorie</th><th>Prix</th><th>Créé par</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td><strong>${item.name}</strong><br><small>${item.description || ''}</small></td>
                                <td>${item.category_name}</td>
                                <td><strong>${item.price} DA</strong></td>
                                <td>${item.updated_by_name || 'N/A'}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-approve" onclick="approveItem(${item.id})">
                                            <i class="fas fa-check"></i> Approuver
                                        </button>
                                        <button class="btn-reject" onclick="rejectItem(${item.id})">
                                            <i class="fas fa-times"></i> Refuser
                                        </button>
                                    </div>
                                </td>
                            </tr>`).join('')}
                    </tbody>
                </table>`;
        }
    } catch (err) { console.error('loadPendingApprovals error:', err); }
}

async function approveItem(id) {
    if (!confirm('Approuver cette publication ?')) return;
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/approvals.php?action=approve&id=${id}`, {
            method: 'POST', headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) { loadPendingApprovals(); loadStats(); }
    } catch (err) { console.error(err); }
}

async function rejectItem(id) {
    if (!confirm('Refuser cette publication ?')) return;
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/approvals.php?action=reject&id=${id}`, {
            method: 'POST', headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) { loadPendingApprovals(); loadStats(); }
    } catch (err) { console.error(err); }
}

// ===================================
// GESTION DES ADMINS
// ===================================
async function loadAdmins() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/admins.php?action=list`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();

        if (data.success) {
            const admins = data.data.admins;
            const container = document.getElementById('adminsList');

            if (admins.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>Aucun administrateur créé</p>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th>Nom d'utilisateur</th><th>Email</th><th>Nom complet</th>
                        <th>Statut</th><th>Dernière connexion</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        ${admins.map(admin => `
                            <tr>
                                <td><strong>${admin.username}</strong></td>
                                <td>${admin.email}</td>
                                <td>${admin.full_name}</td>
                                <td>
                                    <span class="badge ${admin.is_active ? 'badge-active' : 'badge-inactive'}">
                                        ${admin.is_active ? 'Actif' : 'Inactif'}
                                    </span>
                                </td>
                                <td>${admin.last_login ? new Date(admin.last_login).toLocaleString('fr-FR') : 'Jamais'}</td>
                                <td>
                                    <button class="btn-toggle" onclick="toggleAdminStatus(${admin.id}, ${!admin.is_active})">
                                        <i class="fas fa-${admin.is_active ? 'ban' : 'check'}"></i>
                                        ${admin.is_active ? 'Désactiver' : 'Activer'}
                                    </button>
                                </td>
                            </tr>`).join('')}
                    </tbody>
                </table>`;
        }
    } catch (err) { console.error(err); }
}

async function toggleAdminStatus(id, isActive) {
    const action = isActive ? 'activer' : 'désactiver';
    if (!confirm(`Voulez-vous ${action} ce compte admin ?`)) return;
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/admins.php?action=toggle-status&id=${id}`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_active: isActive })
        });
        const data = await res.json();
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdmins();
    } catch (err) { console.error(err); }
}

// ===================================
// CRÉATION D'ADMIN
// ===================================
document.getElementById('createAdminBtn')?.addEventListener('click', () => {
    document.getElementById('createAdminModal').classList.add('active');
});

function closeModal() {
    document.getElementById('createAdminModal').classList.remove('active');
    document.getElementById('createAdminForm').reset();
}

document.getElementById('createAdminForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = sessionStorage.getItem('adminToken');
    const data = {
        username: document.getElementById('adminUsername').value,
        email: document.getElementById('adminEmail').value,
        full_name: document.getElementById('adminFullName').value,
        phone: document.getElementById('adminPhone').value,
        password: document.getElementById('adminPassword').value
    };
    try {
        const res = await fetch(`${API_BASE_URL}/admins.php?action=create`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        showNotification(result.message, result.success ? 'success' : 'error');
        if (result.success) { closeModal(); loadAdmins(); }
    } catch (err) {
        console.error(err);
        showNotification('Erreur lors de la création', 'error');
    }
});

// ===================================
// TOUT LE MENU
// ===================================
async function loadAllMenu() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE_URL}/menu.php?action=list`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        if (data.success) {
            const items = data.data.items;
            document.getElementById('menuList').innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th>Nom</th><th>Catégorie</th><th>Prix</th><th>Statut</th><th>Créé par</th>
                    </tr></thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td><strong>${item.name}</strong></td>
                                <td>${item.category_name}</td>
                                <td>${item.price} DA</td>
                                <td>
                                    <span class="badge ${
                                        item.status === 'approuve' ? 'badge-approved' :
                                        item.status === 'en_attente' ? 'badge-pending' : 'badge-rejected'
                                    }">
                                        ${item.status === 'approuve' ? 'Approuvé' :
                                          item.status === 'en_attente' ? 'En attente' : 'Refusé'}
                                    </span>
                                </td>
                                <td>${item.updated_by_name || 'N/A'}</td>
                            </tr>`).join('')}
                    </tbody>
                </table>`;
        }
    } catch (err) { console.error(err); }
}

// ===================================
// NOTIFICATIONS
// ===================================
function showNotification(message, type = 'success') {
    const n = document.createElement('div');
    n.style.cssText = `
        position:fixed; top:100px; right:20px;
        background:${type === 'success' ? 'linear-gradient(135deg,#06D6A0,#05a57a)' : 'linear-gradient(135deg,#dc3545,#c82333)'};
        color:white; padding:1.5rem 2rem; border-radius:15px;
        box-shadow:0 10px 30px rgba(0,0,0,0.3); z-index:10002; font-weight:600;
    `;
    n.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 3000);
}

document.getElementById('createAdminModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'createAdminModal') closeModal();
});

// ===================================
// INITIALISATION
// ===================================
window.addEventListener('DOMContentLoaded', async () => {
    await checkSuperAdmin();
    loadStats();
    loadPendingApprovals();
});