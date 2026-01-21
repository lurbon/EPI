<?php
/**
 * Système d'authentification centralisé
 * À inclure au début de chaque page protégée avec : require_once('auth.php');
 */

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour vérifier l'authentification
function verifierAuthentification() {
    // Vérifier si un token existe dans la session
    if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
        redirectionLogin("Session expirée ou inexistante");
        return false;
    }
    
    // Vérifier l'expiration absolue (ne peut pas être renouvelée)
    if (!isset($_SESSION['token_expires_absolute']) || $_SESSION['token_expires_absolute'] < time()) {
        session_destroy();
        redirectionLogin("Session expirée après 1 heure");
        return false;
    }
    
    // Vérifier l'inactivité (peut être renouvelée)
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 1800) {
        // 30 minutes d'inactivité
        session_destroy();
        redirectionLogin("Session expirée pour inactivité");
        return false;
    }
    
    // Vérifier si les données utilisateur existent
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        redirectionLogin("Données utilisateur manquantes");
        return false;
    }
    
    return true;
}

// Fonction pour rediriger vers la page de login
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

// Fonction pour vérifier un rôle spécifique
function verifierRole($rolesAutorises) {
    if (!is_array($rolesAutorises)) {
        $rolesAutorises = [$rolesAutorises];
    }
    
    if (!isset($_SESSION['user']['roles'])) {
        redirectionLogin("Rôles non définis");
        return false;
    }
    
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
        // Redirection vers le dashboard avec un message d'erreur
        header('Location: dashboard.php?error=' . urlencode('Accès refusé : rôle insuffisant'));
        exit();
    }
    
    return true;
}

// Fonction pour obtenir l'utilisateur connecté
function getUtilisateurConnecte() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

// Fonction pour obtenir le token
function getToken() {
    if (isset($_SESSION['token'])) {
        return $_SESSION['token'];
    }
    return null;
}

// Exécuter la vérification d'authentification
if (!verifierAuthentification()) {
    exit(); // Sortir si non authentifié
}

// Mettre à jour la dernière activité (pour le timeout d'inactivité)
$_SESSION['last_activity'] = time();

// NE PAS renouveler token_expires_absolute - c'est le point clé !
// La session expirera définitivement après 1 heure, même si l'utilisateur est actif
