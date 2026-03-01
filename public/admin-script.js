// ===================================
// admin-script.js — VERSION CORRIGÉE
// ===================================

const API_URL = 'http://localhost/station-de-service/api/auth.php';

// -------------------------------------------------------
// Vérifier si l'utilisateur est connecté au chargement
// -------------------------------------------------------
async function checkLogin() {
    const token = sessionStorage.getItem('adminToken');

    if (!token) {
        window.location.href = 'admin-login.html';
        return;
    }

    try {
        const res = await fetch(`${API_URL}?action=verify`, {
            method: 'GET',
            headers: { 'Authorization': `Bearer ${token}` }
        });

        const data = await res.json();

        if (!data.success) {
            sessionStorage.removeItem('adminToken');
            window.location.href = 'admin-login.html';
        } else {
            console.log('✅ Connecté en tant que:', data.data.user.username);
        }
    } catch (err) {
        console.error('Erreur vérification:', err);
        sessionStorage.removeItem('adminToken');
        window.location.href = 'admin-login.html';
    }
}

// ===================================
// DONNÉES DU MENU (localStorage)
// ===================================
let menuData = {};

function loadMenuData() {
    const saved = localStorage.getItem('restaurantMenu');
    if (saved) {
        menuData = JSON.parse(saved);
    } else {
        menuData = {
            plats: [
                { id: 1, name: 'Couscous royal', price: 1200 },
                { id: 2, name: 'Tajine poulet', price: 900 },
                { id: 3, name: 'Pizza margherita', price: 800 },
                { id: 4, name: 'Poulet grillé', price: 850 }
            ],
            boissons: [
                { id: 1, name: "Jus d'orange frais", price: 200 },
                { id: 2, name: 'Coca-Cola', price: 150 },
                { id: 3, name: 'Eau minérale', price: 80 },
                { id: 4, name: 'Café', price: 120 }
            ],
            desserts: [
                { id: 1, name: 'Baklava', price: 250 },
                { id: 2, name: 'Crème caramel', price: 200 },
                { id: 3, name: 'Glace vanille', price: 180 },
                { id: 4, name: 'Fruits frais', price: 300 }
            ]
        };
        saveMenuData();
    }
}

function saveMenuData() {
    localStorage.setItem('restaurantMenu', JSON.stringify(menuData));
}

function displayMenuItems() {
    ['plats', 'boissons', 'desserts'].forEach(category => {
        const container = document.getElementById(category + 'Container');
        if (!container) return;
        container.innerHTML = '';
        menuData[category].forEach(item => {
            container.innerHTML += createMenuItemCard(item, category);
        });
    });
}

function createMenuItemCard(item, category) {
    return `
        <div class="menu-item-card">
            <div class="item-info">
                <div class="item-name">${item.name}</div>
                <div class="item-price">${item.price} DA</div>
            </div>
            <div class="item-actions">
                <button class="edit-btn" onclick="editItem(${item.id}, '${category}')">
                    <i class="fas fa-edit"></i> Modifier
                </button>
                <button class="delete-btn" onclick="deleteItem(${item.id}, '${category}')">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </div>
    `;
}

let currentCategory = '';
let editingItemId = null;

function openAddModal(category) {
    currentCategory = category;
    editingItemId = null;
    document.getElementById('modalTitle').textContent = 'Ajouter un élément';
    document.getElementById('itemName').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('itemModal').classList.add('active');
}

function editItem(id, category) {
    currentCategory = category;
    editingItemId = id;
    const item = menuData[category].find(i => i.id === id);
    if (item) {
        document.getElementById('modalTitle').textContent = "Modifier l'élément";
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemPrice').value = item.price;
        document.getElementById('itemModal').classList.add('active');
    }
}

function closeModal() {
    document.getElementById('itemModal').classList.remove('active');
}

function deleteItem(id, category) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) return;
    menuData[category] = menuData[category].filter(item => item.id !== id);
    saveMenuData();
    displayMenuItems();
    showNotification('Élément supprimé avec succès !', 'success');
}

document.getElementById('itemForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = document.getElementById('itemName').value;
    const price = parseInt(document.getElementById('itemPrice').value);

    if (editingItemId) {
        const item = menuData[currentCategory].find(i => i.id === editingItemId);
        if (item) { item.name = name; item.price = price; }
        showNotification('Élément modifié avec succès !', 'success');
    } else {
        const newId = menuData[currentCategory].length > 0
            ? Math.max(...menuData[currentCategory].map(i => i.id)) + 1 : 1;
        menuData[currentCategory].push({ id: newId, name, price });
        showNotification('Élément ajouté avec succès !', 'success');
    }

    saveMenuData();
    displayMenuItems();
    closeModal();
});

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed; top: 100px; right: 20px;
        background: ${type === 'success' ? 'linear-gradient(135deg,#06D6A0,#05a57a)' : 'linear-gradient(135deg,#dc3545,#c82333)'};
        color: white; padding: 1.5rem 2rem; border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 10002;
        font-weight: 600;
    `;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

document.getElementById('itemModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'itemModal') closeModal();
});

// -------------------------------------------------------
// ✅ FIX BUG 2 : Le listener logout est maintenant DANS
//    DOMContentLoaded pour que le bouton existe dans le DOM
// -------------------------------------------------------
window.addEventListener('DOMContentLoaded', () => {
    checkLogin();
    loadMenuData();
    displayMenuItems();

    // Déconnexion — attaché APRÈS que le DOM est prêt
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return;

            const token = sessionStorage.getItem('adminToken');

            if (token) {
                try {
                    await fetch(`${API_URL}?action=logout`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                } catch (err) {
                    console.error('Erreur lors de la déconnexion API:', err);
                }
            }

            // Nettoyer le sessionStorage
            sessionStorage.removeItem('adminToken');
            sessionStorage.removeItem('redirecting');
            sessionStorage.removeItem('userInfo');

            // ✅ Redirection correcte vers logout.html
            window.location.href = 'logout.html';
        });
    }
});