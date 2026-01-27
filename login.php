<?php
/**
 * Gestionnaire de connexion avec JWT et suivi des connexions
 */

// Configuration sécurisée des sessions AVANT session_start()
ini_set('session.cookie_httponly', 1);    // Cookie non accessible via JavaScript (protection XSS)
ini_set('session.cookie_secure', 1);      // Cookie transmis uniquement via HTTPS
ini_set('session.cookie_samesite', 'Strict'); // Protection CSRF au niveau cookie
ini_set('session.use_strict_mode', 1);    // Refuse les ID de session non initialisés
ini_set('session.use_only_cookies', 1);   // Pas d'ID de session dans l'URL

// Démarrer la session
session_start();

// Inclure les headers de sécurité HTTP
require_once(__DIR__ . '/security-headers.php');

// Charger la configuration WordPress pour la connexion à la base de données
require_once('wp-config.php');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Méthode non autorisée']);
    exit();
}

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