<?php
/**
 * Système d'authentification centralisé - VERSION SÉCURISÉE
 * À inclure au début de chaque page protégée avec : require_once('auth.php');
 *
 * Améliorations de sécurité :
 * - Configuration sécurisée des sessions (httponly, secure, samesite)
 * - Headers de sécurité HTTP (CSP, X-Frame-Options, HSTS, etc.)
 * - Timeout de session cohérent (3 heures absolu, 30 min inactivité)
 * - Protection contre Session Fixation
 * - Protection contre MIME sniffing, Clickjacking, XSS
 * - GESTION D'UNE SEULE CONNEXION PAR UTILISATEUR
 *
 * Date de création : 2026-01-21
 * Dernière modification : 2026-02-03
 */

// Configuration sécurisée des sessions AVANT session_start()
// Ces paramètres protègent contre le vol de session et les attaques XSS
ini_set('session.cookie_httponly', 1);    // Cookie non accessible via JavaScript (protection XSS)
ini_set('session.cookie_secure', 1);      // Cookie transmis uniquement via HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Protection CSRF - Lax pour compatibilité navigation (Strict était trop restrictif)
ini_set('session.use_strict_mode', 1);    // Refuse les ID de session non initialisés
ini_set('session.use_only_cookies', 1);   // Pas d'ID de session dans l'URL

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les headers de sécurité HTTP
// Protection contre XSS, Clickjacking, MIME sniffing, etc.
require_once(__DIR__ . '/security-headers.php');

// Constantes de configuration
define('SESSION_TIMEOUT_ABSOLUTE', 10800);  // 3 heures (doit correspondre à login.php)
define('SESSION_TIMEOUT_INACTIVITY', 3600); // 1 heure (60 minutes)

/**
 * Vérifie si cette session est toujours la session active pour cet utilisateur
 * 
 * IMPORTANT: Utilise le session_id PHP pour permettre plusieurs onglets du même navigateur
 * tout en bloquant les connexions depuis d'autres appareils/navigateurs
 * 
 * @return bool True si c'est la session active, False sinon
 */
function verifierSessionUnique() {
    // Vérifier que nous avons les informations nécessaires
    if (!isset($_SESSION['user']['id'])) {
        return false;
    }
    
    try {
        // Charger la configuration
        require_once(__DIR__ . '/config.php');
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Récupérer le session_id actuel
        $currentSessionId = session_id();
        
        // Vérifier si cette session (par session_id) est active en BDD
        $stmt = $pdo->prepare("
            SELECT 
                id,
                date_deconnexion,
                session_id
            FROM connexions_log 
            WHERE user_id = ?
            AND session_id = ?
            AND statut = 'success'
            ORDER BY date_connexion DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user']['id'], $currentSessionId]);
        $sessionActive = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sessionActive) {
            // Aucune session trouvée avec ce session_id
            return false;
        }
        
        // Vérifier si la session est fermée
        if ($sessionActive['date_deconnexion'] !== null) {
            return false;
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur vérification session unique: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre une déconnexion automatique dans la base de données
 * 
 * @param string $raison Raison de la déconnexion ('timeout_absolu' ou 'timeout_inactivite')
 * @return void
 */
function enregistrerDeconnexionAuto($raison = 'timeout') {
    // Vérifier que nous avons les informations nécessaires
    if (!isset($_SESSION['connexion_log_id']) || !isset($_SESSION['login_time'])) {
        return;
    }
    
    try {
        // Charger la configuration
        require_once(__DIR__ . '/config.php');
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $dureeSession = time() - $_SESSION['login_time'];
        
        $stmt = $pdo->prepare("
            UPDATE connexions_log 
            SET date_deconnexion = NOW(),
                duree_session = ?,
                message = CONCAT(message, ' [Déconnexion auto: ', ?, ']')
            WHERE id = ?
        ");
        $stmt->execute([$dureeSession, $raison, $_SESSION['connexion_log_id']]);
        
    } catch (PDOException $e) {
        // Continuer même si le logging échoue
        error_log("Erreur logging déconnexion auto: " . $e->getMessage());
    }
}

/**
 * Vérifie si l'utilisateur est authentifié et que sa session est valide
 *
 * @return bool True si authentifié, sinon redirection vers login
 */
function verifierAuthentification() {
    // Vérifier si un token existe dans la session
    if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
        redirectionLogin("Session expirée ou inexistante");
        return false;
    }

    // Vérifier l'expiration absolue (ne peut pas être renouvelée)
    // La session expire définitivement après SESSION_TIMEOUT_ABSOLUTE
    if (!isset($_SESSION['token_expires_absolute']) || $_SESSION['token_expires_absolute'] < time()) {
        // Enregistrer la déconnexion automatique dans la base
        enregistrerDeconnexionAuto('timeout_absolu');
        session_destroy();
        redirectionLogin("Session expirée après " . (SESSION_TIMEOUT_ABSOLUTE/3600) . " heures");
        return false;
    }

    // Vérifier l'inactivité (peut être renouvelée)
    // La session expire si aucune activité pendant SESSION_TIMEOUT_INACTIVITY
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_INACTIVITY) {
        // Enregistrer la déconnexion automatique dans la base
        enregistrerDeconnexionAuto('timeout_inactivite');
        session_destroy();
        redirectionLogin("Session expirée après " . (SESSION_TIMEOUT_INACTIVITY/60) . " minutes d'inactivité");
        return false;
    }

    // Vérifier si les données utilisateur existent
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        redirectionLogin("Données utilisateur manquantes");
        return false;
    }
    
    // 🔒 NOUVEAU : Vérifier que cette session est toujours la session active
    // Détecte si une nouvelle connexion a eu lieu depuis un autre appareil/navigateur
    if (!verifierSessionUnique()) {
        // Enregistrer la déconnexion automatique dans la base
        enregistrerDeconnexionAuto('nouvelle_connexion_detectee');
        session_destroy();
        redirectionLogin("Une nouvelle connexion a été détectée depuis un autre appareil. Vous avez été déconnecté.");
        return false;
    }

    return true;
}

/**
 * Redirige vers la page de login avec un message d'erreur
 *
 * @param string $message Message d'erreur à afficher
 * @return void
 */
function redirectionLogin($message = "") {
    // Si c'est une requête AJAX, retourner une erreur JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => $message ?: 'Non authentifié'
        ]);
        exit();
    }

    // Sinon, rediriger vers la page de login
    session_destroy();
    header('Location: login.html' . ($message ? '?error=' . urlencode($message) : ''));
    exit();
}

/**
 * Vérifie si l'utilisateur a l'un des rôles autorisés
 *
 * @param string|array $rolesAutorises Rôle(s) autorisé(s) : 'admin', 'benevole', 'chauffeur', 'gestionnaire'
 * @return bool True si autorisé, sinon redirection vers dashboard
 */
function verifierRole($rolesAutorises) {
    // Convertir en tableau si c'est une chaîne
    if (!is_array($rolesAutorises)) {
        $rolesAutorises = [$rolesAutorises];
    }

    // Vérifier que les rôles utilisateur existent
    if (!isset($_SESSION['user']['roles'])) {
        redirectionLogin("Rôles non définis");
        return false;
    }

    // Récupérer les rôles de l'utilisateur
    $userRoles = $_SESSION['user']['roles'];
    if (!is_array($userRoles)) {
        $userRoles = [$userRoles];
    }

    // Vérifier si l'utilisateur a au moins un des rôles autorisés
    $hasRole = false;
    foreach ($rolesAutorises as $role) {
        if (in_array($role, $userRoles)) {
            $hasRole = true;
            break;
        }
    }

    if (!$hasRole) {
        // Logger la tentative d'accès non autorisée
        error_log(sprintf(
            "Tentative d'accès non autorisée - User: %s, Rôles requis: %s, Rôles actuels: %s, Page: %s",
            $_SESSION['user']['username'] ?? 'inconnu',
            implode(',', $rolesAutorises),
            implode(',', $userRoles),
            $_SERVER['PHP_SELF'] ?? 'inconnue'
        ));

        // Redirection vers le dashboard avec un message d'erreur
        header('Location: dashboard.php?error=' . urlencode('Accès refusé : rôle insuffisant'));
        exit();
    }

    return true;
}

/**
 * Retourne les informations de l'utilisateur connecté
 *
 * @return array|null Données utilisateur ou null si non connecté
 */
function getUtilisateurConnecte() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

/**
 * Retourne le token de session de l'utilisateur connecté
 *
 * @return string|null Token de session ou null si non connecté
 */
function getToken() {
    if (isset($_SESSION['token'])) {
        return $_SESSION['token'];
    }
    return null;
}

/**
 * Retourne le nom d'affichage de l'utilisateur connecté
 *
 * @return string Nom d'affichage ou 'Utilisateur' par défaut
 */
function getNomUtilisateur() {
    if (isset($_SESSION['user']['name'])) {
        return htmlspecialchars($_SESSION['user']['name'], ENT_QUOTES, 'UTF-8');
    }
    return 'Utilisateur';
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique (sans redirection)
 *
 * @param string|array $roles Rôle(s) à vérifier
 * @return bool True si l'utilisateur a le rôle
 */
function hasRole($roles) {
    if (!isset($_SESSION['user']['roles'])) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    $userRoles = $_SESSION['user']['roles'];
    if (!is_array($userRoles)) {
        $userRoles = [$userRoles];
    }

    foreach ($roles as $role) {
        if (in_array($role, $userRoles)) {
            return true;
        }
    }

    return false;
}

// ========== EXÉCUTION AUTOMATIQUE ==========

// Exécuter la vérification d'authentification
if (!verifierAuthentification()) {
    exit(); // Sortir si non authentifié
}

// Mettre à jour la dernière activité (pour le timeout d'inactivité)
$_SESSION['last_activity'] = time();

// Mettre à jour l'activité dans la base de données toutes les 5 minutes
// (pour éviter trop de requêtes SQL)
if (!isset($_SESSION['last_activity_db_update']) || 
    (time() - $_SESSION['last_activity_db_update']) > 300) { // 5 minutes
    
    require_once(__DIR__ . '/activity_tracker.php');
    if (enregistrerActiviteDB()) {
        $_SESSION['last_activity_db_update'] = time();
    }
}

// ⚠️ IMPORTANT : NE PAS renouveler token_expires_absolute ici !
// La session doit expirer définitivement après SESSION_TIMEOUT_ABSOLUTE (3 heures)
// même si l'utilisateur est actif.
//
// ERREUR À NE PAS FAIRE :
// $_SESSION['token_expires_absolute'] = time() + 10800; // ❌ MAUVAIS !
//
// Cela permettrait des sessions infinies et annulerait la sécurité du timeout absolu.
// Le token_expires_absolute est défini UNE SEULE FOIS dans login.php lors de la connexion.

// Note de sécurité :
// La régénération d'ID de session devrait être faite UNIQUEMENT lors de l'authentification
// (dans login.php) et non à chaque requête, car cela peut causer des problèmes
// avec les requêtes AJAX parallèles.
