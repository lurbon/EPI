<?php
/**
 * Configuration unifiée des sessions PHP
 * À inclure AVANT session_start() dans tous vos fichiers PHP
 * 
 * IMPORTANT: Utiliser les mêmes paramètres partout pour éviter les conflits
 * 
 * Usage:
 * require_once __DIR__ . '/session_config_unified.php';
 * session_start();
 */

// Durée de vie de la session : 3 heures (10800 secondes)
ini_set('session.gc_maxlifetime', 10800);

// Durée de vie du cookie de session : 3 heures
ini_set('session.cookie_lifetime', 10800);

// Probabilité de nettoyage des sessions expirées : 1%
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Nom du cookie de session (personnalisé pour sécurité)
ini_set('session.name', 'BENEVOLES_SESSION');

// Sécurité des cookies
ini_set('session.cookie_httponly', 1);    // Empêche l'accès JavaScript (protection XSS)
ini_set('session.cookie_secure', 1);      // Mettre à 1 si HTTPS activé (RECOMMANDÉ)

// ⚠️ IMPORTANT: Utiliser 'Lax' pour permettre plusieurs onglets du même navigateur
// 'Strict' peut causer des problèmes avec les nouveaux onglets
ini_set('session.cookie_samesite', 'Lax'); // Protection CSRF (Lax = plus souple que Strict)

// Empêcher l'utilisation d'ID de session dans l'URL
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// Régénération automatique de l'ID de session (sécurité)
ini_set('session.use_strict_mode', 1);

/**
 * Notes importantes:
 * 
 * 1. SameSite=Lax vs Strict:
 *    - Strict: Le cookie n'est JAMAIS envoyé depuis un autre site (même liens normaux)
 *              → Peut causer des problèmes avec nouveaux onglets/redirections
 *    - Lax: Le cookie est envoyé pour les liens normaux (GET), mais pas pour POST cross-site
 *           → Bon compromis entre sécurité et UX
 * 
 * 2. cookie_secure=1:
 *    - Mettre à 1 uniquement si vous utilisez HTTPS
 *    - Si vous êtes en HTTP local (dev), mettre à 0
 * 
 * 3. Partage de session entre onglets:
 *    - Tant que c'est le même navigateur, le session_id est partagé
 *    - Tous les onglets ont accès à la même $_SESSION
 */
