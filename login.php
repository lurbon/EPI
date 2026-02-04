<?php
/**
 * Page de connexion unifiée avec JWT et suivi des connexions
 * - GET  : Affiche le formulaire de connexion
 * - POST : Traite la connexion et retourne JSON
 */

// Configuration sécurisée des sessions AVANT session_start()
ini_set('session.cookie_httponly', 1);    // Cookie non accessible via JavaScript (protection XSS)
ini_set('session.cookie_secure', 1);      // Cookie transmis uniquement via HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Protection CSRF - Lax permet les onglets multiples
ini_set('session.use_strict_mode', 1);    // Refuse les ID de session non initialisés
ini_set('session.use_only_cookies', 1);   // Pas d'ID de session dans l'URL

// Démarrer la session
session_start();

// Inclure les headers de sécurité HTTP
require_once(__DIR__ . '/security-headers.php');

// ============================================================================
// TRAITEMENT POST : Authentification JWT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Charger la configuration WordPress pour la connexion à la base de données
    require_once('wp-config.php');

    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Vérifier que les identifiants sont fournis
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Identifiants manquants']);
        exit();
    }

    $username = $data['username'];
    $password = $data['password'];

    // Connexion à la base de données
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Erreur de connexion à la base de données']);
        exit();
    }

    // Récupérer l'adresse IP et le User Agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Appeler l'API JWT WordPress
    $jwtUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/wp-json/jwt-auth/v1/token';

    $jwtData = json_encode([
        'username' => $username,
        'password' => $password
    ]);

    $ch = curl_init($jwtUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jwtData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jwtData)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Vérifier les erreurs cURL
    if ($curlError) {
        // Logger la tentative échouée
        try {
            $stmt = $pdo->prepare("
                INSERT INTO connexions_log 
                (user_id, username, user_role, date_connexion, ip_address, user_agent, statut, message)
                VALUES (0, ?, 'unknown', NOW(), ?, ?, 'failed', ?)
            ");
            $stmt->execute([$username, $ipAddress, $userAgent, 'Erreur serveur: ' . $curlError]);
        } catch (PDOException $e) {
            // Ignorer les erreurs de logging
        }
        
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Erreur de connexion au serveur']);
        exit();
    }

    // Décoder la réponse
    $jwtResponse = json_decode($response, true);

    // Vérifier la réponse JWT
    if ($httpCode !== 200 || !isset($jwtResponse['token'])) {
        // Logger la tentative échouée
        try {
            $stmt = $pdo->prepare("
                INSERT INTO connexions_log 
                (user_id, username, user_role, date_connexion, ip_address, user_agent, statut, message)
                VALUES (0, ?, 'unknown', NOW(), ?, ?, 'failed', ?)
            ");
            $stmt->execute([
                $username, 
                $ipAddress, 
                $userAgent, 
                $jwtResponse['message'] ?? 'Identifiants incorrects'
            ]);
        } catch (PDOException $e) {
            // Ignorer les erreurs de logging
        }
        
        http_response_code($httpCode);
        echo json_encode([
            'error' => true,
            'message' => $jwtResponse['message'] ?? 'Identifiants incorrects'
        ]);
        exit();
    }

    // Extraire les informations utilisateur
    $userRoles = $jwtResponse['roles'] ?? 
                 $jwtResponse['role'] ?? 
                 $jwtResponse['user_roles'] ?? 
                 $jwtResponse['user_role'] ?? 
                 ['subscriber'];

    // Normaliser les rôles en tableau
    if (is_string($userRoles)) {
        $userRoles = [$userRoles];
    }
    if (!is_array($userRoles)) {
        $userRoles = ['subscriber'];
    }

    // Construire l'objet utilisateur
    $userData = [
        'id' => $jwtResponse['id'] ?? $jwtResponse['user_id'] ?? null,
        'name' => $jwtResponse['user_nicename'] ?? $jwtResponse['user_display_name'] ?? $jwtResponse['username'] ?? $username,
        'email' => $jwtResponse['user_email'] ?? $jwtResponse['email'] ?? '',
        'username' => $jwtResponse['username'] ?? $jwtResponse['user_login'] ?? $username,
        'roles' => $userRoles
    ];

    // Régénérer l'ID de session pour prévenir les attaques de fixation de session
    // IMPORTANT : À faire AVANT de stocker les données sensibles
    session_regenerate_id(true);

    // Stocker dans la session
    $_SESSION['token'] = $jwtResponse['token'];
    $_SESSION['user'] = $userData;
    $_SESSION['token_expires_absolute'] = time() + 10800; // Expiration ABSOLUE - 3 heures (renouvelée à chaque activité)
    $_SESSION['last_activity'] = time(); // Pour détecter l'inactivité
    $_SESSION['login_time'] = time();

    // Fermer automatiquement les anciennes sessions actives de cet utilisateur
    try {
        // Trouver toutes les sessions actives de cet utilisateur
        $stmtOldSessions = $pdo->prepare("
            SELECT 
                id,
                session_id,
                date_connexion,
                TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_seconds
            FROM connexions_log
            WHERE user_id = ?
            AND date_deconnexion IS NULL
            AND statut = 'success'
            ORDER BY date_connexion DESC
        ");
        $stmtOldSessions->execute([$userData['id']]);
        $oldSessions = $stmtOldSessions->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldSessions) > 0) {
            // Fermer toutes les anciennes sessions
            $stmtCloseSession = $pdo->prepare("
                UPDATE connexions_log 
                SET date_deconnexion = NOW(),
                    duree_session = ?,
                    message = CONCAT(
                        COALESCE(message, 'Connexion réussie'), 
                        ' [Déconnexion automatique - nouvelle connexion détectée depuis ', ?, ']'
                    )
                WHERE id = ?
            ");
            
            foreach ($oldSessions as $oldSession) {
                $stmtCloseSession->execute([
                    $oldSession['duree_seconds'],
                    $ipAddress,
                    $oldSession['id']
                ]);
            }
            
            // Logger le nombre de sessions fermées
            error_log("login.php: " . count($oldSessions) . " ancienne(s) session(s) fermée(s) pour l'utilisateur " . $userData['username']);
        }
        
    } catch (PDOException $e) {
        // Continuer même si la fermeture des anciennes sessions échoue
        error_log("Erreur fermeture anciennes sessions: " . $e->getMessage());
    }

    // Logger la connexion réussie
    try {
        $stmt = $pdo->prepare("
            INSERT INTO connexions_log 
            (user_id, username, user_role, date_connexion, last_activity_db, ip_address, user_agent, session_id, statut, message)
            VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?, 'success', 'Connexion réussie')
        ");
        $stmt->execute([
            $userData['id'],
            $userData['username'],
            $userRoles[0],
            $ipAddress,
            $userAgent,
            session_id()
        ]);
        
        // Stocker l'ID du log pour la déconnexion
        $_SESSION['connexion_log_id'] = $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        // Continuer même si le logging échoue
        error_log("Erreur logging connexion: " . $e->getMessage());
    }

    // Retourner le succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => $userData,
        'redirect' => 'dashboard.php'
    ]);
    exit();
}

// ============================================================================
// AFFICHAGE GET : Formulaire de connexion HTML
// ============================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - Espace Bénévoles</title>
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
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .login-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      padding: 40px;
      max-width: 400px;
      width: 100%;
    }

    .logo-container {
      text-align: center;
      margin-bottom: 25px;
      padding: 10px;
    }

    .logo-container img {
      max-width: 150px;
      height: auto;
      border-radius: 0;
      box-shadow: none;
    }

    h2 {
      color: #667eea;
      margin-bottom: 30px;
      text-align: center;
      font-size: 28px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .password-wrapper {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #667eea;
      cursor: pointer;
      font-size: 18px;
      padding: 5px;
      width: auto;
      margin: 0;
    }

    .toggle-password:hover {
      color: #764ba2;
      transform: translateY(-50%) scale(1.1);
      box-shadow: none;
    }

    label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 600;
      font-size: 14px;
    }

    input {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    button {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      margin-top: 10px;
    }

    button:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    button:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    #error {
      background: #ffebee;
      color: #c62828;
      padding: 12px;
      border-radius: 8px;
      margin: 15px 0;
      font-weight: 600;
      display: none;
      border-left: 4px solid #c62828;
    }

    #error.show {
      display: block;
    }

    #success {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 12px;
      border-radius: 8px;
      margin: 15px 0;
      font-weight: 600;
      display: none;
      border-left: 4px solid #2e7d32;
    }

    #success.show {
      display: block;
    }

    .forgot-password {
      text-align: center;
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
    }

    .forgot-password a {
      color: #667eea;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .forgot-password a:hover {
      color: #764ba2;
      text-decoration: underline;
    }

    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(102, 126, 234, 0.3);
      border-top-color: #667eea;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin-right: 10px;
      vertical-align: middle;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @media (max-width: 480px) {
      .login-container {
        padding: 30px 20px;
      }

      h2 {
        font-size: 24px;
      }

      .logo-container img {
        max-width: 120px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo-container">
      <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo Entraide Plus Iroise">
    </div>
    <h2>🔐 Connexion</h2>

    <form id="loginForm" autocomplete="on">
      <div class="form-group">
        <label for="username">Nom d'utilisateur</label>
        <input 
          type="text"
          id="username" 
          name="username" 
          placeholder="Entrez votre nom d'utilisateur" 
          autocomplete="username"
          required
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="password">Mot de passe</label>
        <div class="password-wrapper">
          <input 
            type="password"
            id="password"
            name="password" 
            placeholder="Entrez votre mot de passe" 
            autocomplete="current-password"
            required
          >
          <button type="button" class="toggle-password" id="togglePassword" title="Afficher/Masquer le mot de passe">
            👁️
          </button>
        </div>
      </div>

      <button type="submit" id="submitBtn">Se connecter</button>
    </form>

    <div id="error"></div>
    <div id="success"></div>

    <div class="forgot-password">
      <a href="reset_password.php">Mot de passe oublié ?</a>
    </div>
  </div>

  <script>
    const errorEl = document.getElementById('error');
    const successEl = document.getElementById('success');
    const submitBtn = document.getElementById('submitBtn');
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    // Gérer l'affichage/masquage du mot de passe
    togglePassword.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      togglePassword.textContent = type === 'password' ? '👁️' : '🙈';
    });

    function showError(message) {
      errorEl.textContent = '❌ ' + message;
      errorEl.classList.add('show');
      successEl.classList.remove('show');
    }

    function showSuccess(message) {
      successEl.textContent = '✅ ' + message;
      successEl.classList.add('show');
      errorEl.classList.remove('show');
    }

    // Afficher le message d'erreur de l'URL si présent
    const urlParams = new URLSearchParams(window.location.search);
    const urlError = urlParams.get('error');
    if (urlError) {
      showError(urlError);
    }

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Réinitialiser les messages
      errorEl.classList.remove('show');
      successEl.classList.remove('show');

      // Désactiver le bouton
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="loading-spinner"></span>Connexion en cours...';

      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;

      try {
        const res = await fetch('login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });

        const data = await res.json();

        if (!res.ok || data.error) {
          throw new Error(data.message || 'Erreur de connexion');
        }

        showSuccess('Connexion réussie ! Redirection en cours...');

        // Redirection vers le dashboard
        setTimeout(() => {
          window.location.href = data.redirect || 'dashboard.php';
        }, 1000);

      } catch (error) {
        console.error('Erreur:', error);
        showError(error.message || 'Erreur de connexion');
        
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.textContent = 'Se connecter';
      }
    });
  </script>
</body>
</html>
