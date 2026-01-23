<?php
/**
 * Script de nettoyage des sessions zombies
 * 
 * Ce script met à jour les enregistrements de connexions_log qui n'ont pas de date_deconnexion
 * mais dont la session est forcément expirée (plus de 3 heures).
 * 
 * À exécuter régulièrement via CRON, par exemple :
 * 0 * * * * php /chemin/vers/cleanup_sessions.php
 * (toutes les heures)
 * 
 * Ou manuellement depuis le navigateur en protégeant l'accès
 */

// Charger la configuration WordPress
require_once(__DIR__ . '/wp-config.php');

// Protection : exécution en ligne de commande ou avec un token secret
$secret_token = 'a7f3c9b2e8d1f6a4c5b8e7d3f2a9b1c4'; // À personnaliser !

if (php_sapi_name() !== 'cli') {
    // Exécution via navigateur : vérifier le token
    if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
        http_response_code(403);
        die('Accès refusé');
    }
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Récupérer les sessions zombies (sans date_deconnexion et datant de plus de 3h)
    $stmt = $pdo->query("
        SELECT 
            id, 
            username, 
            date_connexion,
            TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_seconds
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
        AND date_connexion < DATE_SUB(NOW(), INTERVAL 3 HOUR)
    ");
    
    $sessions_zombies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $nb_zombies = count($sessions_zombies);
    
    if ($nb_zombies > 0) {
        echo "🧹 Nettoyage de $nb_zombies session(s) zombie(s)...\n\n";
        
        foreach ($sessions_zombies as $session) {
            echo "  - ID: {$session['id']}, User: {$session['username']}, ";
            echo "Connexion: {$session['date_connexion']}, ";
            echo "Durée estimée: " . round($session['duree_seconds'] / 60) . " min\n";
        }
        
        // Mettre à jour toutes les sessions zombies
        $updateStmt = $pdo->prepare("
            UPDATE connexions_log 
            SET date_deconnexion = DATE_ADD(date_connexion, INTERVAL 3 HOUR),
                duree_session = 10800,
                message = CONCAT(
                    COALESCE(message, 'Connexion réussie'), 
                    ' [Session zombie nettoyée automatiquement le ', 
                    NOW(), 
                    ']'
                )
            WHERE id = ?
        ");
        
        $nb_updated = 0;
        foreach ($sessions_zombies as $session) {
            if ($updateStmt->execute([$session['id']])) {
                $nb_updated++;
            }
        }
        
        echo "\n✅ $nb_updated session(s) nettoyée(s) avec succès !\n";
        
        // Log dans le fichier système
        error_log("Cleanup sessions: $nb_updated sessions zombies nettoyées");
        
    } else {
        echo "✨ Aucune session zombie trouvée. Base de données propre !\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    error_log("Erreur cleanup sessions: " . $e->getMessage());
    exit(1);
}

// Afficher les statistiques
try {
    echo "\n📊 Statistiques des connexions :\n";
    
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_connexions,
            COUNT(CASE WHEN date_deconnexion IS NULL THEN 1 END) as sessions_ouvertes,
            COUNT(CASE WHEN date_deconnexion IS NOT NULL THEN 1 END) as sessions_fermees,
            COUNT(CASE WHEN statut = 'success' THEN 1 END) as connexions_reussies,
            COUNT(CASE WHEN statut = 'failed' THEN 1 END) as connexions_echouees
        FROM connexions_log
        WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "  - Total connexions (30 derniers jours) : {$stats['total_connexions']}\n";
    echo "  - Sessions actuellement ouvertes : {$stats['sessions_ouvertes']}\n";
    echo "  - Sessions fermées : {$stats['sessions_fermees']}\n";
    echo "  - Connexions réussies : {$stats['connexions_reussies']}\n";
    echo "  - Connexions échouées : {$stats['connexions_echouees']}\n";
    
} catch (PDOException $e) {
    echo "⚠️ Impossible de récupérer les statistiques\n";
}

echo "\n✅ Nettoyage terminé !\n";
