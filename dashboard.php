<?php
require_once('auth.php');
$utilisateur = getUtilisateurConnecte();
$token = getToken();

if (!$utilisateur || !$token) {
    die('Erreur : utilisateur ou token manquant dans la session PHP');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Espace Personnel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 10px;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .section-separator {
            height: 15px;
        }

        /* Barre utilisateur */
        .user-bar {
            background: white;
            padding: 6px 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .user-info {
            font-size: 12px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-name {
            font-weight: 700;
            color: #667eea;
            font-size: 13px;
        }

        .user-role {
            padding: 2px 8px;
            background: #e3f2fd;
            border-radius: 8px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #1976d2;
        }

        .btn-logout {
            padding: 5px 10px;
            background: #f56565;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #e53e3e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245,101,101,0.4);
        }

        .btn-change-password {
            padding: 5px 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-change-password:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Containers */
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 12px;
            width: 100%;
        }

        .header-section {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        h1 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 0;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        /* Menu cards */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .menu-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 10px 8px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 65px;
            border: none;
            width: 100%;
            font-family: inherit;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }

        .menu-card-icon {
            font-size: 22px;
            margin-bottom: 4px;
        }

        .menu-card-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 3px;
            text-align: center;
            line-height: 1.1;
        }

        .menu-card-description {
            font-size: 12px;
            opacity: 0.95;
            text-align: center;
            line-height: 1.1;
        }

        /* Sections spécifiques */
        .top-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .top-section.benevole-layout {
            grid-template-columns: 1fr 1fr;
            align-items: stretch;
        }
        
        .top-section.benevole-layout .container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .bottom-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        /* Responsive tablette */
        @media (min-width: 601px) and (max-width: 1024px) {
            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Desktop */
        @media (min-width: 1025px) {
            .top-section {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .bottom-section {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .menu-grid.three-columns {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu-grid.two-columns {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Style spécial pour l'affichage bénévole */
        .benevole-missions-layout {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            align-items: center;
        }

        .benevole-logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .benevole-logo-section img {
            width: 180px;
            height: auto;
            object-fit: contain;
        }

        .benevole-cards-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .benevole-missions-layout {
                grid-template-columns: 1fr;
            }

            .benevole-logo-section img {
                width: 120px;
            }
        }

        /* Loader */
        .loader {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden {
            display: none !important;
        }

        .info-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .info-box h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }

        .info-box p {
            font-size: 14px;
            line-height: 1.5;
        }

        .info-box.success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .info-box.success h3 {
            color: #2e7d32;
        }

        .info-box.info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .info-box.info h3 {
            color: #1565c0;
        }

        .info-box.warning {
            background: #fff3e0;
            color: #bf360c;
        }

        .info-box.warning h3 {
            color: #e65100;
        }

        /* Sélecteur de thème discret */
        .theme-selector {
            position: relative;
            display: inline-block;
        }

        .theme-button {
            background: transparent;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 5px 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .theme-button:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .theme-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-top: 5px;
            z-index: 1000;
            min-width: 180px;
            overflow: hidden;
        }

        .theme-dropdown.show {
            display: block;
        }

        .theme-option {
            padding: 10px 15px;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .theme-option:hover {
            background: #f5f5f5;
        }

        .theme-preview {
            width: 30px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .theme-name {
            flex: 1;
            font-weight: 500;
        }

        .theme-check {
            color: #667eea;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Barre utilisateur -->
        <div class="user-bar">
            <div class="user-info">
                <span>👤 <span class="user-name" id="userName"></span></span>
                <span class="user-role" id="userRole"></span>
            </div>
			<div style="display: flex; gap: 10px; align-items: center;">
                <!-- Sélecteur de thème discret -->
                <div class="theme-selector">
                    <button class="theme-button" id="themeButton" title="Changer de thème">
                        🎨
                    </button>
                    <div class="theme-dropdown" id="themeDropdown">
                        <div class="theme-option" onclick="changeTheme('purple')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                            <span class="theme-name">Violet</span>
                            <span class="theme-check" id="check-purple">✓</span>
                        </div>
                        <div class="theme-option" onclick="changeTheme('blue')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"></div>
                            <span class="theme-name">Bleu</span>
                            <span class="theme-check" id="check-blue" style="display: none;">✓</span>
                        </div>
                        <div class="theme-option" onclick="changeTheme('green')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);"></div>
                            <span class="theme-name">Vert</span>
                            <span class="theme-check" id="check-green" style="display: none;">✓</span>
                        </div>
                        <div class="theme-option" onclick="changeTheme('orange')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);"></div>
                            <span class="theme-name">Orange</span>
                            <span class="theme-check" id="check-orange" style="display: none;">✓</span>
                        </div>
                        <div class="theme-option" onclick="changeTheme('red')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"></div>
                            <span class="theme-name">Rose</span>
                            <span class="theme-check" id="check-red" style="display: none;">✓</span>
                        </div>
                        <div class="theme-option" onclick="changeTheme('dark')">
                            <div class="theme-preview" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);"></div>
                            <span class="theme-name">Sombre</span>
                            <span class="theme-check" id="check-dark" style="display: none;">✓</span>
                        </div>
                    </div>
                </div>
                <a href="img/photo.jpg" title="Voir le monstre">
					<img src="img/cam.jpg" width="60" height="60" alt="" ></a>
				<a href="change_password.php" class="btn-change-password">🔑 Changer mot de passe</a>
				<button class="btn-logout" id="logout">🚪 Déconnexion</button>
			</div>
        </div>

        <!-- Loader initial -->
        <div id="loader" class="container">
            <div class="loader">
                <div class="spinner"></div>
                <p>⏳ Chargement de votre espace...</p>
            </div>
        </div>

        <!-- Contenu principal (caché au départ) -->
        <div id="mainContent" class="hidden">
            <!-- Section supérieure -->
            <div class="top-section">
                <!-- Bloc principal gauche -->
                <div class="container" id="mainBlock">
                    <div class="header-section">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" class="logo">
                        <h1 id="mainBlockTitle">Saisies et modifications</h1>
                    </div>
                    <div class="menu-grid three-columns" id="mainBlockGrid">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>

                <!-- Bloc secondaire droit -->
                <div class="container" id="secondaryBlock">
                    <div class="header-section">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" class="logo">
                        <h1 id="secondaryBlockTitle">Gestion des missions</h1>
                    </div>
                    <div class="menu-grid" id="secondaryBlockGrid">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>
            </div>

            <!-- Séparateur entre les sections -->
            <div class="section-separator"></div>

            <!-- Section inférieure -->
            <div class="bottom-section">
                <!-- Bloc info gauche -->
                <div class="container" id="infoBlock">
                    <div class="header-section">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" class="logo">
                        <h1 id="infoBlockTitle">Listes</h1>
                    </div>
                      <div class="menu-grid three-columns" id="infoBlockGrid">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>

                <!-- Bloc stats droit -->
                <div class="container" id="statsBlock">
                    <div class="header-section">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" class="logo">
                        <h1 id="statsBlockTitle">Autres actions</h1>
                    </div>
                    <div class="menu-grid three-columns" id="statsBlockGrid">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Injection des données PHP -->
    <script>
        const phpToken = <?php echo json_encode($token); ?>;
        const phpUser = <?php echo json_encode($utilisateur); ?>;
        const phpExpires = <?php echo isset($_SESSION['token_expires']) ? $_SESSION['token_expires'] * 1000 : 'null'; ?>;
        
        if (phpToken) sessionStorage.setItem('token', phpToken);
        if (phpUser) sessionStorage.setItem('user', JSON.stringify(phpUser));
        if (phpExpires) sessionStorage.setItem('token_expires', phpExpires);
    </script>

    <!-- Script dashboard -->
    <script>
        // Récupération utilisateur
        function getUser() {
            const userStr = sessionStorage.getItem('user');
            if (!userStr) return null;
            try {
                return JSON.parse(userStr);
            } catch (e) {
                console.error('Erreur parsing user:', e);
                return null;
            }
        }

        // Affichage info utilisateur
        const user = getUser();
        if (user) {
            document.getElementById('userName').textContent = user.name || 'Utilisateur';
            const roles = Array.isArray(user.roles) ? user.roles : [user.roles];
            document.getElementById('userRole').textContent = roles[0] || 'User';
        }

        // Fonction pour créer une carte menu
        function createMenuCard(icon, title,description,  href, onclick) {
            if (href) {
                return `
                    <a href="${href}" class="menu-card" style="text-decoration: none;">
                        <div class="menu-card-icon">${icon}</div>
                        <div class="menu-card-title">${title}</div>
                       
                    </a>
                `;
            } else if (onclick) {
                return `
                    <button class="menu-card" onclick="${onclick}">
                        <div class="menu-card-icon">${icon}</div>
                        <div class="menu-card-title">${title}</div>
          
                    </button>
                `;
            } else {
                return `
                    <div class="menu-card" style="cursor: default;">
                        <div class="menu-card-icon">${icon}</div>
                        <div class="menu-card-title">${title}</div>
                        ${description ? `<div class="menu-card-description">${description}</div>` : ''}
                    </div>
                `;
            }
        }

        // Chargement du dashboard
        function loadDashboard() {
            const user = getUser();
            if (!user || !user.roles) {
                window.location.href = 'login.html';
                return;
            }

            const roles = Array.isArray(user.roles) ? user.roles : [user.roles];
            const isAdmin = roles.includes('admin');
            const isGestionnaire = roles.includes('gestionnaire');
            const isBenevole = roles.includes('benevole');
            const isChauffeur = roles.includes('chauffeur');
            
            // Extraire le prénom de l'utilisateur (premier mot du nom)
            const userFirstName = user.name ? user.name.split(' ')[0] : 'Utilisateur';

            // INTERFACE ADMINISTRATEUR (accès complet)
            if (isAdmin) {
                document.getElementById('mainBlockTitle').textContent = 'Saisies et modifications';
                document.getElementById('mainBlockGrid').innerHTML = `
                    ${createMenuCard('👤', 'Nouveau bénévole', null, 'formulaire_benevole.php')}
                    ${createMenuCard('✏', 'Modifier un bénévole', null, 'modifier_benevole.php')}					
                    ${createMenuCard('🤝', 'Nouvel aidé', null, 'formulaire_aide.php')}
                    ${createMenuCard('✏', 'Modifier un aidé', 'Inscription', 'modifier_aide.php')}					
                    ${createMenuCard('📋', 'Nouvelle mission', null,  'formulaire_mission.php')}
                    ${createMenuCard('✏', 'Modifier une mission', 'Rapports','modifier_mission.php')}
                `;

                document.getElementById('secondaryBlockTitle').textContent = 'Gestion des missions';
                document.getElementById('secondaryBlockGrid').innerHTML = `
                    ${createMenuCard('❓', 'Mission sans bénévole', null,'missions_sans_benevoles.php')}
                    ${createMenuCard('🚗⌚', 'Saisie des KM et heures', null, 'saisie_km.php')}
                    ${createMenuCard('🔄', 'Dupliquer une mission', null, 'dupliquer_mission.php')}
                `;

                document.getElementById('infoBlockTitle').textContent = 'Listes';
                document.getElementById('infoBlockGrid').innerHTML = `
                     ${createMenuCard('📝', 'Liste des missions',   null,'liste_missions.php')}
					${createMenuCard('👤', 'Liste des aidés',  null,'liste_aides.php')}
                   ${createMenuCard('👤', 'Liste des bénévoles', null, 'liste_benevoles.php')}
				    ${createMenuCard('👤', userFirstName + ' - Vos missions', null,'vos_missions.php')}
                   
                `;

                document.getElementById('statsBlockTitle').textContent = 'Autres actions';
                document.getElementById('statsBlockGrid').innerHTML = `
                     ${createMenuCard('🚌', 'Minibus',   null,'minibus.php')}
					${createMenuCard('👤', 'Adhésion bénévole',  null,'paiements_benevoles.php')}
                   ${createMenuCard('👤', 'Adhésion aidé', null,'paiements_aides.php')}
                   ${createMenuCard('⚙️', 'Administration', null,'admin.php')}
                `;
            }

            // INTERFACE GESTIONNAIRE (sans accès aux adhésions)
            else if (isGestionnaire) {
                document.getElementById('mainBlockTitle').textContent = 'Saisies et modifications';
                document.getElementById('mainBlockGrid').innerHTML = `
                    ${createMenuCard('👤', 'Nouveau bénévole', null, 'formulaire_benevole.php')}
                    ${createMenuCard('🤝', 'Nouvel aidé', null, 'formulaire_aide.php')}
                    ${createMenuCard('📋', 'Nouvelle mission', null,  'formulaire_mission.php')}
                    ${createMenuCard('✏', 'Modifier un bénévole', null, 'modifier_benevole.php')}
                    ${createMenuCard('✏', 'Modifier un aidé', 'Inscription', 'modifier_aide.php')}
                    ${createMenuCard('✏', 'Modifier une mission', 'Rapports','modifier_mission.php')}
                `;

                document.getElementById('secondaryBlockTitle').textContent = 'Gestion des missions';
                document.getElementById('secondaryBlockGrid').innerHTML = `
                    ${createMenuCard('❓', 'Mission sans bénévole', null,'missions_sans_benevoles.php')}
                    ${createMenuCard('🚗⌚', 'Saisie des KM et heures', null, 'saisie_km.php')}
                    ${createMenuCard('🔄', 'Dupliquer une mission', null, 'dupliquer_mission.php')}
                `;

                document.getElementById('infoBlockTitle').textContent = 'Listes';
                document.getElementById('infoBlockGrid').innerHTML = `
                     ${createMenuCard('📝', 'Liste des missions',   null,'liste_missions.php')}
					${createMenuCard('👤', 'Liste des aidés',  null,'liste_aides.php')}
                   ${createMenuCard('👤', 'Liste des bénévoles', null, 'liste_benevoles.php')}
				   ${createMenuCard('👤', userFirstName + ' - Vos missions', null,'vos_missions.php')}
                   
                `;

        document.getElementById('statsBlockTitle').textContent = 'Autres actions';
                document.getElementById('statsBlockGrid').innerHTML = `
                     ${createMenuCard('🚌', 'Minibus',   null,'minibus.php')}
					 ${createMenuCard('🚌', 'Stats secteur',   null,'stats_secteurs.php')}
                `;
            }

            // INTERFACE BÉNÉVOLE
            else if (isBenevole) {
                // Ajouter la classe pour layout côte à côte avec hauteurs égales
                document.querySelector('.top-section').classList.add('benevole-layout');
                
                // Restructurer le bloc principal (Gestion des missions)
                document.getElementById('mainBlock').querySelector('.header-section').style.display = 'none';
                
                document.getElementById('mainBlockGrid').style.display = 'grid';
                document.getElementById('mainBlockGrid').style.gridTemplateColumns = '200px 1fr';
                document.getElementById('mainBlockGrid').style.gap = '20px';
                document.getElementById('mainBlockGrid').style.alignItems = 'center';
                document.getElementById('mainBlockGrid').style.flex = '1';
                
                document.getElementById('mainBlockGrid').innerHTML = `
                    <div style="display: flex; justify-content: center; align-items: center;">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" style="width: 180px; height: auto; object-fit: contain;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <div style="color: #667eea; font-size: 24px; font-weight: 800; margin-bottom: 10px;">Gestion des missions</div>
                        ${createMenuCard('👤', userFirstName + ' - Vos missions', null, 'vos_missions.php')}
                        ${createMenuCard('🚗⌚', 'Saisie KM et heures', null, 'saisie_km.php')}
                    </div>
                `;
				// Restructurer le bloc secondaire (Autres)
                document.getElementById('secondaryBlock').querySelector('.header-section').style.display = 'none';                
                document.getElementById('secondaryBlockGrid').style.display = 'grid';
                document.getElementById('secondaryBlockGrid').style.gridTemplateColumns = '200px 1fr';
                document.getElementById('secondaryBlockGrid').style.gap = '20px';
                document.getElementById('secondaryBlockGrid').style.alignItems = 'center';
                document.getElementById('secondaryBlockGrid').style.flex = '1';
                
                document.getElementById('secondaryBlockGrid').innerHTML = `
                    <div style="display: flex; justify-content: center; align-items: center;">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" style="width: 180px; height: auto; object-fit: contain;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <div style="color: #667eea; font-size: 24px; font-weight: 800; margin-bottom: 10px;">Autres</div>
						${createMenuCard('🚌', 'Stats secteur',   null,'stats_secteurs.php')}
                    </div>
                `;

                // Masquer les blocs non utilisés pour les bénévoles
                  document.getElementById('infoBlock').style.display = 'none';
                document.getElementById('statsBlock').style.display = 'none';
            }

            // INTERFACE CHAUFFEUR (même accès que bénévole + minibus)
            else if (isChauffeur) {
                // Ajouter la classe pour layout côte à côte avec hauteurs égales
                document.querySelector('.top-section').classList.add('benevole-layout');
                
                // Restructurer le bloc principal (Gestion des missions)
                document.getElementById('mainBlock').querySelector('.header-section').style.display = 'none';                
                document.getElementById('mainBlockGrid').style.display = 'grid';
                document.getElementById('mainBlockGrid').style.gridTemplateColumns = '200px 1fr';
                document.getElementById('mainBlockGrid').style.gap = '20px';
                document.getElementById('mainBlockGrid').style.alignItems = 'center';
                document.getElementById('mainBlockGrid').style.flex = '1';
                
                document.getElementById('mainBlockGrid').innerHTML = `
                    <div style="display: flex; justify-content: center; align-items: center;">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" style="width: 180px; height: auto; object-fit: contain;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <div style="color: #667eea; font-size: 24px; font-weight: 800; margin-bottom: 10px;">Gestion des missions</div>
                           ${createMenuCard('👤', userFirstName + ' - Vos missions', null, 'vos_missions.php')}
                           ${createMenuCard('🚗⌚', 'Saisie KM et heures', null, 'saisie_km.php')}
                    </div>
                `;
                
                // Restructurer le bloc secondaire (Autres)
                document.getElementById('secondaryBlock').querySelector('.header-section').style.display = 'none';
                
                document.getElementById('secondaryBlockGrid').style.display = 'grid';
                document.getElementById('secondaryBlockGrid').style.gridTemplateColumns = '200px 1fr';
                document.getElementById('secondaryBlockGrid').style.gap = '20px';
                document.getElementById('secondaryBlockGrid').style.alignItems = 'center';
                document.getElementById('secondaryBlockGrid').style.flex = '1';
                
                document.getElementById('secondaryBlockGrid').innerHTML = `
                    <div style="display: flex; justify-content: center; align-items: center;">
                        <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo" style="width: 180px; height: auto; object-fit: contain;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <div style="color: #667eea; font-size: 24px; font-weight: 800; margin-bottom: 10px;">Autres</div>
                        ${createMenuCard('🚌', 'Minibus', null, 'minibus.php')}
						${createMenuCard('🚌', 'Stats secteur',   null,'stats_secteurs.php')}
                    </div>
                `;

                // Masquer les blocs non utilisés pour les chauffeurs
                document.getElementById('infoBlock').style.display = 'none';
                document.getElementById('statsBlock').style.display = 'none';
            }

            // Afficher le contenu
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('mainContent').classList.remove('hidden');
        }

        // Déconnexion
        document.getElementById('logout').addEventListener('click', () => {
            sessionStorage.clear();
            window.location.href = 'logout.php';
        });

        // Lancement
        loadDashboard();

        // Gestion du sélecteur de thème
        const themeButton = document.getElementById('themeButton');
        const themeDropdown = document.getElementById('themeDropdown');

        // Toggle dropdown
        themeButton.addEventListener('click', (e) => {
            e.stopPropagation();
            themeDropdown.classList.toggle('show');
        });

        // Fermer le dropdown si on clique ailleurs
        document.addEventListener('click', () => {
            themeDropdown.classList.remove('show');
        });

        // Empêcher la fermeture si on clique dans le dropdown
        themeDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Définition des thèmes
        const themes = {
            purple: {
                gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                primary: '#667eea',
                secondary: '#764ba2'
            },
            blue: {
                gradient: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                primary: '#4facfe',
                secondary: '#00f2fe'
            },
            green: {
                gradient: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                primary: '#43e97b',
                secondary: '#38f9d7'
            },
            orange: {
                gradient: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                primary: '#fa709a',
                secondary: '#fee140'
            },
            red: {
                gradient: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                primary: '#f093fb',
                secondary: '#f5576c'
            },
            dark: {
                gradient: 'linear-gradient(135deg, #2c3e50 0%, #34495e 100%)',
                primary: '#2c3e50',
                secondary: '#34495e'
            }
        };

        // Fonction pour changer le thème
        function changeTheme(themeName) {
            const theme = themes[themeName];
            
            // Sauvegarder le thème dans localStorage
            localStorage.setItem('dashboardTheme', themeName);
            
            // Appliquer le thème
            document.body.style.background = theme.gradient;
            
            // Mettre à jour toutes les cartes du menu
            document.querySelectorAll('.menu-card').forEach(card => {
                card.style.background = theme.gradient;
            });
            
            // Mettre à jour les titres et éléments colorés
            document.querySelectorAll('h1, .user-name').forEach(el => {
                el.style.color = theme.primary;
            });
            
            // Mettre à jour tous les éléments avec la couleur primaire
            document.querySelectorAll('[style*="667eea"]').forEach(el => {
                const style = el.getAttribute('style');
                if (style) {
                    el.setAttribute('style', style.replace(/#667eea/g, theme.primary));
                }
            });
            
            // Mettre à jour le spinner
            const spinner = document.querySelector('.spinner');
            if (spinner) {
                spinner.style.borderTopColor = theme.primary;
            }
            
            // Mettre à jour les checkmarks
            Object.keys(themes).forEach(t => {
                const check = document.getElementById(`check-${t}`);
                if (check) {
                    check.style.display = t === themeName ? 'inline' : 'none';
                }
            });
            
            // Fermer le dropdown
            themeDropdown.classList.remove('show');
        }

        // Charger le thème sauvegardé au démarrage
        const savedTheme = localStorage.getItem('dashboardTheme');
        if (savedTheme && themes[savedTheme]) {
            // Petit délai pour s'assurer que tout est chargé
            setTimeout(() => {
                changeTheme(savedTheme);
            }, 100);
        }

        // Rendre la fonction changeTheme globale
        window.changeTheme = changeTheme;
    </script>
	
</body>
</html>