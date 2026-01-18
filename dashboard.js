// ===================================
// DASHBOARD.JS - Version simplifiée
// ===================================

console.log('🚀 Dashboard.js chargé');

// Vérifier le token
const token = sessionStorage.getItem('token');
console.log('Token trouvé:', token ? 'Oui' : 'Non');

if (!token) {
  console.log('❌ Pas de token, redirection vers login.html');
  window.location.href = 'login.html';
  throw new Error('Pas de token');
}

// Vérifier l'expiration du token
const expiresAt = sessionStorage.getItem('token_expires');
if (expiresAt && Date.now() > parseInt(expiresAt)) {
  console.log('❌ Token expiré');
  sessionStorage.clear();
  window.location.href = 'login.html?error=' + encodeURIComponent('Session expirée');
  throw new Error('Token expiré');
}

// Fonction pour récupérer l'utilisateur depuis sessionStorage
function getUser() {
  console.log('📦 Récupération des données utilisateur depuis sessionStorage...');
  
  const userStr = sessionStorage.getItem('user');
  
  if (!userStr) {
    console.error('❌ Aucune donnée utilisateur dans sessionStorage');
    return null;
  }
  
  try {
    const user = JSON.parse(userStr);
    console.log('✓ Données utilisateur récupérées:', user);
    return user;
  } catch (e) {
    console.error('❌ Erreur parsing user:', e);
    return null;
  }
}

// Fonction pour charger le dashboard
function loadDashboard() {
  console.log('🎨 Chargement du dashboard...');
  
  const titre = document.getElementById('titre');
  const contenu = document.getElementById('contenu');
  
  try {
    const user = getUser();
    
    if (!user || !user.roles) {
      throw new Error('Utilisateur invalide - données manquantes');
    }
    
    console.log('👤 Utilisateur:', user.name);
    console.log('🎭 Rôles:', user.roles);
    
    // Normaliser les rôles en tableau
    let roles = Array.isArray(user.roles) ? user.roles : [user.roles];
    
    console.log('Rôles normalisés:', roles);

    // === INTERFACE ADMINISTRATEUR ===
    if (roles.includes('administrator')) {
      console.log('✓ Affichage interface administrateur');
      
      titre.innerText = `👋 Bienvenue ${user.name}`;
      contenu.innerHTML = `
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>🛠️ Panneau d'administration</h3>
          <p>Vous avez accès à toutes les fonctionnalités d'administration.</p>
        </div>
        
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>📋 Accès à la liste des bénévoles</h3>
          <p>Pour lister les bénévoles avec toutes les informations détaillées (adresse, véhicule, disponibilités, etc.)</p>
          <a href="liste_benevoles.php" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
            🚀 Liste
          </a>
        </div>
        
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>📋 Accès paiements</h3>
          <p>Pour lister les bénévoles et les paiements</p>
          <a href="paiements_benevoles.php" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
            🚀 Liste
          </a>
        </div>
        
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>📋 Ajouter un bénévole</h3>
          <p>Pour créer un bénévole avec toutes les informations détaillées (adresse, véhicule, disponibilités, etc.)</p>
          <a href="formulaire-benevole.php" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
            🚀 Ouvrir le formulaire</a>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <details style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
          <summary style="cursor: pointer; font-weight: bold;">🔍 Debug Info</summary>
          <pre style="margin-top: 10px; background: white; padding: 10px; overflow-x: auto;">${JSON.stringify(user, null, 2)}</pre>
        </details>
      `;
      
    // === INTERFACE BÉNÉVOLE ===
    } else if (roles.includes('benevole')) {
      console.log('✓ Affichage interface bénévole');
      
      titre.innerText = `👋 Bienvenue ${user.name}`;
      contenu.innerHTML = `
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>📋 Votre espace bénévole</h3>
          <p>Bienvenue dans votre espace personnel. Vous pouvez saisir vos Km et vos temps de trajets, consulter les informations aidés.</p>
        </div>
        
        <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;">
          <h4>📌 Vos missions</h4>
          <p><em>Aucune mission pour le moment</em></p>
        </div>
        
        <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;">
          <h4>📄 Documents</h4>
          <p><em>Aucun document disponible</em></p>
        </div>
        
        <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
          <h4>👤 Vos informations</h4>
          <p><strong>Nom :</strong> ${user.name}</p>
          <p><strong>Email :</strong> ${user.email || 'Non renseigné'}</p>
          <p><strong>Rôle :</strong> Bénévole</p>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <details style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
          <summary style="cursor: pointer; font-weight: bold;">🔍 Debug Info</summary>
          <pre style="margin-top: 10px; background: white; padding: 10px; overflow-x: auto;">${JSON.stringify(user, null, 2)}</pre>
        </details>
      `;
      
    // === INTERFACE PAR DÉFAUT ===
    } else {
      console.log('⚠️ Rôle non reconnu, affichage interface par défaut');
      
      titre.innerText = `👋 Bienvenue ${user.name}`;
      contenu.innerHTML = `
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
          <h3>👤 Espace utilisateur</h3>
          <p><strong>Rôle actuel :</strong> ${roles.join(', ')}</p>
        </div>
        
        <p>Votre compte est actif mais n'a pas encore de rôle spécifique attribué.</p>
        <p>Contactez l'administrateur pour plus d'informations.</p>
        
        <hr style="margin: 30px 0;">
        
        <details style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
          <summary style="cursor: pointer; font-weight: bold;">🔍 Debug Info</summary>
          <pre style="margin-top: 10px; background: white; padding: 10px; overflow-x: auto;">${JSON.stringify(user, null, 2)}</pre>
        </details>
      `;
    }
    
  } catch (error) {
    console.error('❌ Erreur lors du chargement du dashboard:', error);
    titre.innerText = '❌ Erreur';
    contenu.innerHTML = `
      <div style="background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828;">
        <h3>Une erreur est survenue</h3>
        <p>${error.message}</p>
        <button onclick="sessionStorage.clear(); location.href='login.html'" style="margin-top: 15px; padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
          Retour à la connexion
        </button>
      </div>
    `;
  }
}

// Bouton de déconnexion
const logoutBtn = document.getElementById('logout');
if (logoutBtn) {
  logoutBtn.addEventListener('click', () => {
    console.log('👋 Déconnexion');
    sessionStorage.clear();
    window.location.href = 'logout.php';
  });
} else {
  console.warn('⚠️ Bouton logout introuvable');
}

// === LANCEMENT ===
console.log('🎬 Lancement du dashboard...');
loadDashboard();