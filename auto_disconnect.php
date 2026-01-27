<?php
/**
 * Script CRON : Déconnecter les utilisateurs inactifs depuis 1 heure
 * VERSION ADAPTÉE POUR EXÉCUTION HORAIRE
 * 
 * À exécuter toutes les heures via CRON :
 * 0 * * * * /usr/bin/php /chemin/vers/auto_disconnect_hourly.php
 * 
 * Principe :
 * - Cherche les connexions actives (date_deconnexion IS NULL)
 * - Dont la dernière activité (last_activity_db) remonte à plus d'1 heure
 * - Enregistre la déconnexion automatique
 * 
 * IMPORTANT : Avec une exécution horaire, les déconnexions peuvent avoir
 * jusqu'à 1h59 de retard (ex: inactif à 10h01, déconnecté à 11h00)
 */

// Charger la configuration WordPress
require_once(__DIR__ . '/wp-config.php');

// Pour éviter que le script soit appelé directement via HTTP (sauf test manuel)
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('Ce script doit être exécuté via CRON ou CLI');
}

// Configuration
$INACTIVITY_TIMEOUT = 60; // 1 heure en minutes

echo "[" . date('Y-m-d H:i:s') . "] Démarrage de la déconnexion automatique (exécution horaire)...\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Trouver les sessions inactives depuis 1 heure
    // Note : avec un CRON horaire, on peut avoir des sessions inactives jusqu'à 1h59
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            user_id,
            date_connexion,
            last_activity_db,
            TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW()) as minutes_inactivite,
            TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_session
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
        AND TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW()) >= ?
        ORDER BY minutes_inactivite DESC
    ");
    $stmt->execute([$INACTIVITY_TIMEOUT]);
    $sessionsInactives = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($sessionsInactives);
    echo "Trouvé $count session(s) inactive(s) depuis plus de $INACTIVITY_TIMEOUT minutes\n";
    
    if ($count > 0) {
        // Déconnecter chaque session inactive
        $stmtUpdate = $pdo->prepare("
            UPDATE connexions_log 
            SET date_deconnexion = NOW(),
                duree_session = ?,
                message = CONCAT(
                    COALESCE(message, 'Connexion réussie'), 
                    ' [Déconnexion automatique après ', ?, ' minutes d\\'inactivité en base de données]'
                )
            WHERE id = ?
        ");
        
        foreach ($sessionsInactives as $session) {
            $stmtUpdate->execute([
                $session['duree_session'],
                $session['minutes_inactivite'],
                $session['id']
            ]);
            
            echo sprintf(
                "  → Session #%d (%s) déconnectée - %d min d'inactivité (dernière activité: %s)\n",
                $session['id'],
                $session['username'],
                $session['minutes_inactivite'],
                $session['last_activity_db'] ?? 'jamais'
            );
        }
        
        echo "✓ $count session(s) déconnectée(s) avec succès\n";
    } else {
        echo "✓ Aucune session inactive à déconnecter\n";
    }
    
    // Statistiques
    echo "\n--- Statistiques ---\n";
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as sessions_actives,
            MIN(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as min_inactivite,
            MAX(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as max_inactivite,
            AVG(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as avg_inactivite
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
    ");
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
    echo sprintf(
        "Sessions actives : %d\n",
        $stats['sessions_actives']
    );
    
    if ($stats['sessions_actives'] > 0) {
        echo sprintf(
            "Inactivité min/max/moyenne : %d / %d / %d minutes\n",
            $stats['min_inactivite'],
            $stats['max_inactivite'],
            round($stats['avg_inactivite'])
        );
    }
    
    echo "\nNote : Avec un CRON horaire, les déconnexions peuvent avoir jusqu'à 1h59 de retard.\n";
    echo "Pour plus de précision, envisagez un CRON toutes les 5 ou 15 minutes.\n";
    
} catch (PDOException $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    error_log("Erreur auto_disconnect_hourly: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Terminé\n\n";
exit(0);
