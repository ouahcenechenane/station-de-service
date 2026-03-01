// ===================================
// logout.js
// ===================================

const API_BASE_URL = 'http://localhost/station-de-service/api';

async function performLogout() {
    const token = sessionStorage.getItem('adminToken');

    if (token) {
        try {
            await fetch(`${API_BASE_URL}/auth.php?action=logout`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
        } catch (err) {
            console.error('Erreur API déconnexion:', err);
        }
    }

    // Nettoyer la session
    sessionStorage.removeItem('adminToken');
    sessionStorage.removeItem('redirecting');
    sessionStorage.removeItem('userInfo');

    showLogoutMessage();
}

function showLogoutMessage() {
    const container = document.querySelector('.container');
    if (container) {
        container.innerHTML = `
            <div class="success"><i class="fas fa-check-circle"></i></div>
            <h1>Déconnexion réussie !</h1>
            <p class="message">Votre session a été nettoyée avec succès.</p>
            <p style="color:#999;font-size:0.9rem">Redirection dans 3 secondes...</p>
            <a href="admin-login.html" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Retour à la connexion
            </a>
        `;
    }
    setTimeout(() => { window.location.href = 'admin-login.html'; }, 3000);
}

window.addEventListener('DOMContentLoaded', () => {
    const token = sessionStorage.getItem('adminToken');
    if (!token) {
        showLogoutMessage();
    } else {
        performLogout();
    }
});

// Empêcher le retour en arrière
window.history.pushState(null, '', window.location.href);
window.addEventListener('popstate', () => {
    window.history.pushState(null, '', window.location.href);
});