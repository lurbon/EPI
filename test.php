<?php
/**
 * Générateur de clés secrètes
 * 
 * Ce script génère des clés sécurisées et crée automatiquement
 * votre fichier secrets.php
 * 
 * UTILISATION :
 * php generate_secrets.php
 */

function generateSecretKey($length = 32) {
    return bin2hex(random_bytes($length));
}

function createSecretsFile() {
    $secretsFile = __DIR__ . '/secrets.php';
    
    // Vérifier si le fichier existe déjà
    if (file_exists($secretsFile)) {
        echo "⚠️  Le fichier secrets.php existe déjà.\n";
        echo "Voulez-vous le régénérer ? (o/n) : ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'o') {
            echo "Opération annulée.\n";
            exit(0);
        }
        fclose($handle);
    }
    
    // Générer les clés
    $jwtSecret = generateSecretKey(32);
    $apiKey = generateSecretKey(32);
    $encryptionKey = generateSecretKey(32);
    
    // Contenu du fichier secrets.php
    $content = <<<PHP
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
    'jwt_secret' => '$jwtSecret',
    
    // Clé API pour les services externes
    'api_key' => '$apiKey',
    
    // Clé de chiffrement pour les données sensibles
    'encryption_key' => '$encryptionKey',
    
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

PHP;
    
    // Écrire le fichier
    if (file_put_contents($secretsFile, $content)) {
        // Définir les permissions restrictives (lecture seule pour le propriétaire)
        chmod($secretsFile, 0600);
        
        echo "✅ Fichier secrets.php créé avec succès !\n\n";
        echo "📋 Clés générées :\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "JWT Secret:       $jwtSecret\n";
        echo "API Key:          $apiKey\n";
        echo "Encryption Key:   $encryptionKey\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "⚠️  IMPORTANT :\n";
        echo "1. Vérifiez que 'secrets.php' est dans votre .gitignore\n";
        echo "2. Personnalisez les informations de base de données\n";
        echo "3. Conservez une copie sécurisée de ces clés\n";
        
        // Créer/mettre à jour .gitignore
        updateGitignore();
        
    } else {
        echo "❌ Erreur lors de la création du fichier secrets.php\n";
        exit(1);
    }
}

function updateGitignore() {
    $gitignorePath = __DIR__ . '/.gitignore';
    $entry = 'secrets.php';
    
    if (file_exists($gitignorePath)) {
        $content = file_get_contents($gitignorePath);
        if (strpos($content, $entry) === false) {
            file_put_contents($gitignorePath, "\n" . $entry . "\n", FILE_APPEND);
            echo "✅ secrets.php ajouté au .gitignore\n";
        }
    } else {
        file_put_contents($gitignorePath, $entry . "\n");
        echo "✅ .gitignore créé avec secrets.php\n";
    }
}

// Créer également un fichier exemple
function createExampleFile() {
    $exampleFile = __DIR__ . '/secrets.example.php';
    
    $content = <<<'PHP'
<?php
/**
 * Fichier de configuration des secrets - EXEMPLE
 * 
 * INSTRUCTIONS :
 * 1. Utilisez generate_secrets.php pour générer vos vraies clés
 *    OU copiez ce fichier vers "secrets.php" et remplissez manuellement
 * 2. Ne commitez JAMAIS secrets.php dans git
 * 
 * Pour générer une clé manuellement :
 * php -r "echo bin2hex(random_bytes(32));"
 */

if (!defined('APP_ACCESS')) {
    http_response_code(403);
    die('Accès interdit');
}

return [
    'jwt_secret' => 'REMPLACER_PAR_UNE_CLE_SECRETE_64_CARACTERES',
    'api_key' => 'REMPLACER_PAR_UNE_CLE_API_64_CARACTERES',
    'encryption_key' => 'REMPLACER_PAR_UNE_CLE_CHIFFREMENT_64_CARACTERES',
    
    'db' => [
        'host' => 'localhost',
        'database' => 'nom_de_votre_base',
        'username' => 'votre_utilisateur',
        'password' => 'votre_mot_de_passe',
        'charset' => 'utf8mb4'
    ],
    
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'votre_email@example.com',
        'password' => 'votre_mot_de_passe_email'
    ]
];
PHP;
    
    file_put_contents($exampleFile, $content);
    echo "✅ Fichier secrets.example.php créé\n";
}

// Exécution
echo "\n🔐 Générateur de secrets PHP\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

createSecretsFile();
createExampleFile();

echo "\n✨ Terminé !\n\n";