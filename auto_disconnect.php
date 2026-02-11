<?php
/**
 * Script CRON : Déconnexion automatique optimisée
 * VERSION OPTIMISÉE - Compatible avec le nouveau système de sessions
 * 
 * À exécuter régulièrement via CRON (recommandé : toutes les 15 minutes) :
 * */15 * * * * /usr/bin/php /chemin/vers/auto_disconnect.php
 * 
 * OU toutes les heures (moins précis) :
 * 0 * * * * /usr/bin/php /chemin/vers/auto_disconnect.php
 * 
 * RÔLE DANS LE SYSTÈME :
 * =====================
 * Ce script complète login.php qui ferme automatiquement les anciennes sessions
 * lors d'une nouvelle connexion. auto_disconnect.php gère les cas où l'utilisateur :
 * - Ne se reconnecte jamais (session abandonnée)
 * - Reste inactif trop longtemps
 * 
 * OPTIMISATIONS :
 * ==============
 * 1. Évite les doublons avec login.php en ne traitant que les sessions réellement inactives
 * 2. Garde seulement la session la plus récente par utilisateur (au cas où login.php aurait raté)
 * 3. Timeout configurable (par défaut 60 minutes)
 * 4. Rapport détaillé avec distinction des cas
 */

// Charger la configuration
require_once(__DIR__ . '/config.php');

// Protection : CLI ou test manuel
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('Ce script doit être exécuté via CRON ou CLI. Pour test manuel : ?manual_run=1');
}

// CONFIGURATION
$INACTIVITY_TIMEOUT = 60; // Délai d'inactivité en minutes (1 heure par défaut)

// Détection mode CLI vs HTTP
$isCLI = php_sapi_name() === 'cli';
$nl = $isCLI ? "\n" : "<br>\n";

// Fonction d'affichage compatible CLI et HTTP
function output($message, $nl) {
    echo $message . $nl;
    if (!$GLOBALS['isCLI']) {
        flush();
    }
}

output("=================================================", $nl);
output("  DÉCONNEXION AUTOMATIQUE OPTIMISÉE", $nl);
output("=================================================", $nl);
output("[" . date('Y-m-d H:i:s') . "] Démarrage...", $nl);
output("", $nl);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // ÉTAPE 1 : Détecter et nettoyer les sessions multiples (sécurité supplémentaire)
    output("📊 Vérification des sessions multiples...", $nl);
    
    $stmtMultiples = $pdo->query("
        SELECT 
            user_id,
            username,
            COUNT(*) as nb_sessions
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
        GROUP BY user_id, username
        HAVING COUNT(*) > 1
    ");
    $multiplesUsers = $stmtMultiples->fetchAll(PDO::FETCH_ASSOC);
    
    $sessionsMultiplesClosed = 0;
    if (count($multiplesUsers) > 0) {
        output("⚠️  Trouvé " . count($multiplesUsers) . " utilisateur(s) avec sessions multiples (pas fermées par login.php)", $nl);
        
        foreach ($multiplesUsers as $user) {
            // Garder la plus récente, fermer les autres
            $stmtGetSessions = $pdo->prepare("
                SELECT 
                    id,
                    date_connexion,
                    last_activity_db,
                    TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_seconds
                FROM connexions_log
                WHERE user_id = ?
                AND date_deconnexion IS NULL
                AND statut = 'success'
                ORDER BY COALESCE(last_activity_db, date_connexion) DESC
            ");
            $stmtGetSessions->execute([$user['user_id']]);
            $sessions = $stmtGetSessions->fetchAll(PDO::FETCH_ASSOC);
            
            // Garder la première (plus récente), fermer les autres
            $kept = array_shift($sessions);
            
            if (count($sessions) > 0) {
                $stmtCloseMultiple = $pdo->prepare("
                    UPDATE connexions_log 
                    SET date_deconnexion = NOW(),
                        duree_session = ?,
                        message = CONCAT(
                            COALESCE(message, 'Connexion réussie'), 
                            ' [Déconnexion auto - session multiple détectée par auto_disconnect]'
                        )
                    WHERE id = ?
                ");
                
                foreach ($sessions as $session) {
                    $stmtCloseMultiple->execute([$session['duree_seconds'], $session['id']]);
                    $sessionsMultiplesClosed++;
                }
                
                output("   → " . $user['username'] . " : " . count($sessions) . " session(s) dupliquée(s) fermée(s)", $nl);
            }
        }
        
        output("✓ " . $sessionsMultiplesClosed . " session(s) dupliquée(s) fermée(s)", $nl);
    } else {
        output("✓ Aucune session multiple détectée", $nl);
    }
    output("", $nl);
    
    // ÉTAPE 2 : Fermer les sessions inactives
    output("⏱️  Recherche des sessions inactives (>" . $INACTIVITY_TIMEOUT . " min)...", $nl);
    
    $stmtInactives = $pdo->prepare("
        SELECT 
            id,
            username,
            user_id,
            date_connexion,
            last_activity_db,
            ip_address,
            TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW()) as minutes_inactivite,
            TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_session
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
        AND TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW()) >= ?
        ORDER BY minutes_inactivite DESC
    ");
    $stmtInactives->execute([$INACTIVITY_TIMEOUT]);
    $sessionsInactives = $stmtInactives->fetchAll(PDO::FETCH_ASSOC);
    
    $countInactives = count($sessionsInactives);
    
    if ($countInactives > 0) {
        output("⚠️  Trouvé " . $countInactives . " session(s) inactive(s)", $nl);
        
        $stmtCloseInactive = $pdo->prepare("
            UPDATE connexions_log 
            SET date_deconnexion = NOW(),
                duree_session = ?,
                message = CONCAT(
                    COALESCE(message, 'Connexion réussie'), 
                    ' [Déconnexion auto après ', ?, ' min d\\'inactivité]'
                )
            WHERE id = ?
        ");
        
        foreach ($sessionsInactives as $session) {
            $stmtCloseInactive->execute([
                $session['duree_session'],
                $session['minutes_inactivite'],
                $session['id']
            ]);
            
            output(sprintf(
                "   → Session #%d (%s) - %d min d'inactivité - IP: %s",
                $session['id'],
                $session['username'],
                $session['minutes_inactivite'],
                $session['ip_address']
            ), $nl);
        }
        
        output("✓ " . $countInactives . " session(s) inactive(s) fermée(s)", $nl);
    } else {
        output("✓ Aucune session inactive à fermer", $nl);
    }
    output("", $nl);
    
    // ÉTAPE 3 : Statistiques finales
    output("=================================================", $nl);
    output("  RAPPORT FINAL", $nl);
    output("=================================================", $nl);
    
    $totalFermes = $sessionsMultiplesClosed + $countInactives;
    output("Sessions fermées ce tour :", $nl);
    output("  - Sessions multiples : " . $sessionsMultiplesClosed, $nl);
    output("  - Sessions inactives : " . $countInactives, $nl);
    output("  - TOTAL : " . $totalFermes, $nl);
    output("", $nl);
    
    // État actuel du système
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(DISTINCT user_id) as nb_users_actifs,
            COUNT(*) as sessions_actives,
            MIN(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as min_inactivite,
            MAX(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as max_inactivite,
            AVG(TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_db, date_connexion), NOW())) as avg_inactivite
        FROM connexions_log
        WHERE date_deconnexion IS NULL
        AND statut = 'success'
    ");
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
    output("État actuel du système :", $nl);
    output("  - Utilisateurs connectés : " . $stats['nb_users_actifs'], $nl);
    output("  - Sessions actives totales : " . $stats['sessions_actives'], $nl);
    
    if ($stats['sessions_actives'] > 0) {
        output("  - Inactivité min/max/moy : " . 
               $stats['min_inactivite'] . " / " . 
               $stats['max_inactivite'] . " / " . 
               round($stats['avg_inactivite']) . " minutes", $nl);
    }
    output("", $nl);
    
    // Vérifier s'il reste des problèmes
    $stmtCheck = $pdo->query("
        SELECT COUNT(*) as nb_problemes
        FROM (
            SELECT user_id
            FROM connexions_log
            WHERE date_deconnexion IS NULL
            AND statut = 'success'
            GROUP BY user_id
            HAVING COUNT(*) > 1
        ) as check_multiples
    ");
    $checkResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($checkResult['nb_problemes'] > 0) {
        output("⚠️  ATTENTION : " . $checkResult['nb_problemes'] . " utilisateur(s) ont encore des sessions multiples", $nl);
        output("   Relancez le script ou vérifiez login.php", $nl);
    } else {
        output("✅ Système sain : aucune session multiple détectée", $nl);
    }
    
    // Logger dans les fichiers système
    if ($totalFermes > 0) {
        error_log("auto_disconnect: $totalFermes sessions fermées (multiples: $sessionsMultiplesClosed, inactives: $countInactives)");
    }
    
} catch (PDOException $e) {
    output("❌ Erreur : " . $e->getMessage(), $nl);
    error_log("Erreur auto_disconnect: " . $e->getMessage());
    exit(1);
}

output("", $nl);
output("[" . date('Y-m-d H:i:s') . "] Terminé", $nl);
output("=================================================", $nl);

exit(0);
