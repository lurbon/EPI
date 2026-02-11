<?php
/**
 * Gestionnaire de déconnexion avec suivi
 */

// Démarrer la session
session_start();

// Charger la configuration
require_once('config.php');

// Récupérer les informations avant de détruire la session
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'utilisateur';
$connexionLogId = isset($_SESSION['connexion_log_id']) ? $_SESSION['connexion_log_id'] : null;
$loginTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : null;

// Mettre à jour le log de connexion avec la déconnexion
if ($connexionLogId && $loginTime) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $dureeSession = time() - $loginTime;
        
        $stmt = $pdo->prepare("
            UPDATE connexions_log 
            SET date_deconnexion = NOW(),
                duree_session = ?
            WHERE id = ?
        ");
        $stmt->execute([$dureeSession, $connexionLogId]);
        
    } catch (PDOException $e) {
        // Continuer même si le logging échoue
        error_log("Erreur logging déconnexion: " . $e->getMessage());
    }
}

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session si il existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection vers la page de login avec un message de succès
header('Location: login.html?message=' . urlencode('Déconnexion réussie. À bientôt ' . $userName . ' !'));
exit();
