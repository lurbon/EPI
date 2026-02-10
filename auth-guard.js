// ============================================================
// 🛡️ AUTH-GUARD.JS - Script de protection universel
// À inclure dans TOUTES vos pages HTML
// Usage: <script src="auth-guard.js"></script>
// ============================================================

(function() {
    'use strict';
    
    // 🔑 Configuration de l'URL du script (À REMPLACER PAR VOTRE URL DE DÉPLOIEMENT)
    // ⚠️ Remplacez VOTRE_URL_SCRIPT_APPS par l'URL réelle !
	
    const SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbzznslRdeck2s4IDkOlAjHg-yE9oB36Vtkir_-nza6GJtiJlYu2wt9BNPM2awqJlHee5g/exec'; 
    window.SCRIPT_URL = SCRIPT_URL; // Rendre accessible globalement

    // Configuration
    const SESSION_KEY = 'entraide_session';
    const LOGIN_PAGE = 'login_page.html';
    const SESSION_DURATION = 8 * 60 * 60 * 1000; // 8 heures
    // Fonction universelle pour appeler l'API de manière sécurisée
	
	
window.secureFetch = async function(paramsObj) {
    const sessionData = localStorage.getItem('entraide_session');
    let token = '';
    
    if (sessionData) {
        try {
            const session = JSON.parse(sessionData);
            token = session.token; // Le token qu'on a reçu au login
        } catch(e) { console.error(e); }
    }

    // Construire l'URL avec le token
    const url = new URL(window.SCRIPT_URL);
    
    // Ajouter les paramètres demandés
    Object.keys(paramsObj).forEach(key => url.searchParams.append(key, paramsObj[key]));
    
    // AJOUTER LE TOKEN AUTOMATIQUEMENT
    if(token) url.searchParams.append('token', token);

    const response = await fetch(url);
    const data = await response.json();

    // Si le serveur dit que la session est expirée
    if (data.error === 'SESSION_EXPIRED') {
        alert("Votre session a expiré par sécurité.");
        logout(); // Fonction existante dans auth-guard qui redirige vers login
        return null;
    }

    return data;
};
    // ==================== VÉRIFICATION SESSION ====================
    
    function checkAuth() {
        const sessionData = localStorage.getItem(SESSION_KEY);
        
        // Pas de session
        if (!sessionData) {
            console.log('❌ Aucune session trouvée');
            redirectToLogin('Veuillez vous connecter');
            return false;
        }
        
        try {
            const session = JSON.parse(sessionData);
            
            // Vérification structure
            if (!session.user || !session.expiresAt) {
                throw new Error('Session invalide');
            }
            
            // Vérification expiration
            if (Date.now() > session.expiresAt) {
                localStorage.removeItem(SESSION_KEY);
                redirectToLogin('Session expirée. Reconnectez-vous.');
                return false;
            }
            
            // ✅ Session valide
            console.log('✅ Session valide:', session.user.email);
            return session.user;
            
        } catch (e) {
            console.error('❌ Erreur session:', e);
            localStorage.removeItem(SESSION_KEY);
            redirectToLogin('Session corrompue');
            return false;
        }
    }
    
    // ==================== REDIRECTION ====================
    
    function redirectToLogin(message) {
        // Ne pas rediriger si déjà sur la page de login
        if (window.location.pathname.endsWith(LOGIN_PAGE)) {
            return;
        }
        
        if (message) {
            console.log('🔀 Redirection:', message);
        }
        
        window.location.href = LOGIN_PAGE;
    }
    
    // ==================== DÉCONNEXION ====================
    
    window.logout = function() {
        if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
            console.log('🚪 Déconnexion');
            localStorage.removeItem(SESSION_KEY);
            
            // Nettoyer aussi le cache si présent
            localStorage.removeItem('entraide_data');
            localStorage.removeItem('entraide_data_time');
            
            window.location.href = LOGIN_PAGE;
        }
    };
    
    // ==================== INFO SESSION ====================
    
    window.getSessionInfo = function() {
        const sessionData = localStorage.getItem(SESSION_KEY);
        if (!sessionData) return null;
        
        try {
            const session = JSON.parse(sessionData);
            const now = Date.now();
            const remaining = session.expiresAt - now;
            
            return {
                user: session.user,
                loginTime: new Date(session.loginTime),
                expiresAt: new Date(session.expiresAt),
                remainingMinutes: Math.floor(remaining / 60000),
                isExpired: remaining <= 0
            };
        } catch (e) {
            return null;
        }
    };
    
    // ==================== PROLONGER SESSION ====================
    
    window.extendSession = function() {
        const sessionData = localStorage.getItem(SESSION_KEY);
        if (!sessionData) return false;
        
        try {
            const session = JSON.parse(sessionData);
            session.expiresAt = Date.now() + SESSION_DURATION;
            localStorage.setItem(SESSION_KEY, JSON.stringify(session));
            console.log('✅ Session prolongée de 8h');
            return true;
        } catch (e) {
            console.error('❌ Erreur prolongation:', e);
            return false;
        }
    };
    
    // ==================== INITIALISATION ====================
    
    // Ne pas vérifier sur la page de login elle-même
    if (!window.location.pathname.endsWith(LOGIN_PAGE)) {
        const currentUser = checkAuth();
        
        if (currentUser) {
            // Rendre l'utilisateur accessible globalement
            window.currentUser = currentUser;
            
            // Afficher info dans console
            console.log('👤 Connecté:', currentUser.nom || currentUser.email);
            console.log('🎭 Rôle:', currentUser.role);
            
            const info = getSessionInfo();
            if (info) {
                console.log('⏱️ Session expire dans', info.remainingMinutes, 'minutes');
            }
            
            // Prolonger auto la session toutes les 30 min d'activité
            let lastActivity = Date.now();
            
            ['mousedown', 'keypress', 'scroll', 'touchstart'].forEach(event => {
                document.addEventListener(event, function() {
                    const now = Date.now();
                    if (now - lastActivity > 30 * 60 * 1000) { // 30 min
                        extendSession();
                        lastActivity = now;
                    }
                }, { passive: true });
            });
        }
    }
    
    // ==================== HELPERS UI ====================
    
    // Ajouter automatiquement un badge utilisateur si élément existe
    window.addEventListener('DOMContentLoaded', function() {
        const userBadge = document.getElementById('user-badge');
        if (userBadge && window.currentUser) {
            userBadge.innerHTML = `
                <span>👤 ${window.currentUser.nom || window.currentUser.email}</span>
                <button onclick="logout()" style="margin-left: 10px;">🚪 Déconnexion</button>
            `;
        }
    });
    
})();

// ============================================================
// USAGE DANS VOS PAGES :
// ============================================================
//
// 1. Inclure le script :
//    <script src="auth-guard.js"></script>
//
// 2. Accéder à l'utilisateur :
//    console.log(window.currentUser.nom);
//
// 3. Vérifier le rôle :
//    if (window.currentUser.role === 'admin') { ... }
//
// 4. Ajouter un bouton déconnexion :
//    <button onclick="logout()">Déconnexion</button>
//
// 5. Badge utilisateur auto (optionnel) :
//    <div id="user-badge"></div>
//
// ============================================================
