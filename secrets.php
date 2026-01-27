<?php
/**
 * Fichier de configuration des secrets
 * 
 * ⚠️  ATTENTION : Ne JAMAIS commiter ce fichier dans git !
 * 
 * Généré automatiquement le : <?php echo date('Y-m-d H:i:s'); ?>

 */

// Empêche l'accès direct via le navigateur
if (!defined('APP_ACCESS')) {
    http_response_code(403);
    die('Accès interdit');
}

return [
    // Clé secrète pour JWT (JSON Web Tokens)
    'jwt_secret' => '5f48cd8d9477b2f3fcc20728728cf9551b21fda82cf7ba064100f7479efad2c8',
    
    // Clé API pour les services externes
    'api_key' => 'c8dce09f9558159d28b1572ab157690f16456a019537a29126dfe504f7244bcc',
    
    // Clé de chiffrement pour les données sensibles
    'encryption_key' => '907d5cb71d92d0cb98273d0603f09a86e75eb4b0a4c053743559a51f55c61566',
    
    // Informations de base de données (à personnaliser)
    'db' => [
        'host' => 'localhost',
        'database' => 'nom_de_votre_base',
        'username' => 'votre_utilisateur',
        'password' => 'votre_mot_de_passe',
        'charset' => 'utf8mb4'
    ],
    
    // Autres secrets (à ajouter selon vos besoins)
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'votre_email@example.com',
        'password' => 'votre_mot_de_passe_email'
    ]
];
