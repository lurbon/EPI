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
    
    // Vérifier si l'expiration est définie et valide
    if (!isset($_SESSION['token_expires']) || $_SESSION['token_expires'] < time()) {
        session_destroy();
        redirectionLogin("Session expirée");
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

// Rafraîchir l'expiration à chaque requête (renouveler la session)
$_SESSION['token_expires'] = time() + (3600); // +1 heure
