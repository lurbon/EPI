<?php
/**
 * Headers de sécurité HTTP
 *
 * Ce fichier configure les headers de sécurité pour protéger l'application contre :
 * - XSS (Cross-Site Scripting)
 * - Clickjacking
 * - MIME sniffing
 * - Attaques MITM (Man-In-The-Middle)
 *
 * À inclure au début de chaque fichier PHP avec : require_once('security-headers.php');
 *
 * Documentation :
 * - https://owasp.org/www-project-secure-headers/
 * - https://content-security-policy.com/
 */

// Content Security Policy - Protection contre XSS
// Autorise uniquement les ressources provenant du même domaine
// Permet les styles et scripts inline (nécessaires pour l'application actuelle)
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .                                      // Par défaut : même origine uniquement
    "script-src 'self' 'unsafe-inline'; " .                       // Scripts : même origine + inline (à améliorer)
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " . // Styles : même origine + Google Fonts
    "font-src 'self' https://fonts.gstatic.com; " .               // Polices : même origine + Google Fonts
    "img-src 'self' data:; " .                                    // Images : même origine + data URIs
    "connect-src 'self'; " .                                      // AJAX/Fetch : même origine uniquement
    "frame-ancestors 'self'; " .                                  // Iframes : même origine uniquement
    "base-uri 'self'; " .                                         // Balise <base> : même origine uniquement
    "form-action 'self'"                                          // Formulaires : même origine uniquement
);

// X-Frame-Options - Protection contre le Clickjacking
// Empêche l'affichage du site dans une iframe sur un autre domaine
header("X-Frame-Options: SAMEORIGIN");

// X-Content-Type-Options - Protection contre le MIME sniffing
// Force les navigateurs à respecter le Content-Type déclaré
header("X-Content-Type-Options: nosniff");

// Strict-Transport-Security (HSTS) - Force HTTPS
// Le navigateur utilisera toujours HTTPS pendant 1 an (incluant les sous-domaines)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// X-XSS-Protection - Protection XSS des navigateurs anciens
// Active la protection XSS intégrée aux navigateurs (pour compatibilité)
header("X-XSS-Protection: 1; mode=block");

// Referrer-Policy - Contrôle des informations de référence
// N'envoie l'URL complète que pour les requêtes vers le même domaine
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions-Policy - Désactive les API sensibles
// Désactive l'accès à la géolocalisation, microphone, caméra, etc.
header(
    "Permissions-Policy: " .
    "geolocation=(), " .                   // Pas de géolocalisation
    "microphone=(), " .                    // Pas de microphone
    "camera=(), " .                        // Pas de caméra
    "payment=(), " .                       // Pas d'API de paiement
    "usb=(), " .                           // Pas d'accès USB
    "magnetometer=(), " .                  // Pas de magnétomètre
    "gyroscope=(), " .                     // Pas de gyroscope
    "accelerometer=()"                     // Pas d'accéléromètre
);

// Cache-Control pour les pages authentifiées
// Empêche la mise en cache des pages sensibles
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Note importante sur Content-Security-Policy :
 *
 * L'utilisation de 'unsafe-inline' pour script-src et style-src n'est pas idéale
 * mais nécessaire car l'application utilise des scripts et styles inline.
 *
 * Pour améliorer la sécurité, il faudrait :
 * 1. Extraire tous les scripts inline dans des fichiers .js séparés
 * 2. Extraire tous les styles inline dans des fichiers .css séparés
 * 3. Utiliser des nonces ou des hashes pour les scripts/styles restants
 *
 * Exemple avec nonce :
 * - Générer un nonce : $nonce = base64_encode(random_bytes(16));
 * - Header : "script-src 'self' 'nonce-{$nonce}';"
 * - Dans HTML : <script nonce="<?php echo $nonce; ?>">...</script>
 */