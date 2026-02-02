<?php
/**
 * NETTOYAGE IMMÉDIAT DES SESSIONS MULTIPLES
 * À exécuter via navigateur en étant connecté en admin
 */

require_once('wp-config.php');
require_once('auth.php');

// Vérifier que c'est un admin
try {
    verifierRole(['admin']);
} catch (Exception $e) {
    die('❌ Accès refusé - Admin uniquement');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage Sessions Multiples</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
        }
        .user-row {
            background: #fff;
        }
        .user-row:hover {
            background: #f9f9f9;
        }
        .session-detail {
            font-size: 12px;
            color: #666;
            margin: 3px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        #result {
            margin-top: 20px;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Nettoyage des Sessions Multiples</h1>

        <div class="info-box">
            <strong>ℹ️ Information</strong><br>
            Ce script identifie les utilisateurs ayant plusieurs sessions actives et ferme toutes les sessions sauf la plus récente.
        </div>

        <?php
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Action de nettoyage
            if (isset($_POST['cleanup'])) {
                echo '<div class="loading"><div class="spinner"></div>Nettoyage en cours...</div>';
                echo '<div id="result">';
                
                // Trouver les utilisateurs avec plusieurs sessions
                $stmt = $pdo->query("
                    SELECT 
                        user_id,
                        username,
                        COUNT(*) as nb_sessions
                    FROM connexions_log
                    WHERE date_deconnexion IS NULL
                    AND statut = 'success'
                    GROUP BY user_id, username
                    HAVING COUNT(*) > 1
                    ORDER BY nb_sessions DESC
                ");
                
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $nbUsers = count($users);
                $totalClosed = 0;
                
                if ($nbUsers === 0) {
                    echo '<div class="success-box"><strong>✅ Aucune session multiple trouvée !</strong><br>La base de données est propre.</div>';
                } else {
                    echo '<div class="warning-box"><strong>⚠️ Trouvé ' . $nbUsers . ' utilisateur(s) avec sessions multiples</strong></div>';
                    
                    foreach ($users as $user) {
                        // Récupérer toutes les sessions de l'utilisateur
                        $stmtSessions = $pdo->prepare("
                            SELECT 
                                id,
                                date_connexion,
                                last_activity_db,
                                ip_address,
                                TIMESTAMPDIFF(SECOND, date_connexion, NOW()) as duree_seconds
                            FROM connexions_log
                            WHERE user_id = ?
                            AND date_deconnexion IS NULL
                            AND statut = 'success'
                            ORDER BY COALESCE(last_activity_db, date_connexion) DESC
                        ");
                        $stmtSessions->execute([$user['user_id']]);
                        $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Garder la première (plus récente), fermer les autres
                        $kept = array_shift($sessions);
                        
                        echo '<div class="info-box">';
                        echo '<strong>👤 ' . htmlspecialchars($user['username']) . '</strong> - ' . $user['nb_sessions'] . ' sessions<br>';
                        echo '<span style="color: #4caf50;">✓ Session conservée : #' . $kept['id'] . ' (IP: ' . htmlspecialchars($kept['ip_address']) . ')</span><br>';
                        
                        if (count($sessions) > 0) {
                            $stmtClose = $pdo->prepare("
                                UPDATE connexions_log 
                                SET date_deconnexion = NOW(),
                                    duree_session = ?,
                                    message = CONCAT(
                                        COALESCE(message, 'Connexion réussie'), 
                                        ' [Déconnexion automatique - nettoyage sessions multiples]'
                                    )
                                WHERE id = ?
                            ");
                            
                            foreach ($sessions as $session) {
                                $stmtClose->execute([$session['duree_seconds'], $session['id']]);
                                echo '<span style="color: #f57c00;">✗ Session fermée : #' . $session['id'] . ' (IP: ' . htmlspecialchars($session['ip_address']) . ')</span><br>';
                                $totalClosed++;
                            }
                        }
                        echo '</div>';
                    }
                    
                    echo '<div class="success-box">';
                    echo '<strong>✅ Nettoyage terminé !</strong><br>';
                    echo 'Utilisateurs traités : ' . $nbUsers . '<br>';
                    echo 'Sessions fermées : ' . $totalClosed . '<br>';
                    echo 'Sessions conservées : ' . $nbUsers;
                    echo '</div>';
                }
                
                echo '</div>';
                
            } else {
                // Afficher l'état actuel
                $stmt = $pdo->query("
                    SELECT 
                        user_id,
                        username,
                        COUNT(*) as nb_sessions
                    FROM connexions_log
                    WHERE date_deconnexion IS NULL
                    AND statut = 'success'
                    GROUP BY user_id, username
                    HAVING COUNT(*) > 1
                    ORDER BY nb_sessions DESC
                ");
                
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $nbUsers = count($users);
                
                if ($nbUsers === 0) {
                    echo '<div class="success-box"><strong>✅ Aucune session multiple détectée</strong><br>Tous les utilisateurs n\'ont qu\'une seule session active.</div>';
                } else {
                    echo '<div class="warning-box"><strong>⚠️ ' . $nbUsers . ' utilisateur(s) avec sessions multiples détecté(s)</strong></div>';
                    
                    echo '<table>';
                    echo '<thead><tr>';
                    echo '<th>Utilisateur</th>';
                    echo '<th>Sessions actives</th>';
                    echo '<th>Détails</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($users as $user) {
                        // Récupérer les détails des sessions
                        $stmtDetails = $pdo->prepare("
                            SELECT 
                                id,
                                date_connexion,
                                last_activity_db,
                                ip_address
                            FROM connexions_log
                            WHERE user_id = ?
                            AND date_deconnexion IS NULL
                            AND statut = 'success'
                            ORDER BY COALESCE(last_activity_db, date_connexion) DESC
                        ");
                        $stmtDetails->execute([$user['user_id']]);
                        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo '<tr class="user-row">';
                        echo '<td><strong>' . htmlspecialchars($user['username']) . '</strong></td>';
                        echo '<td><span class="badge badge-warning">' . $user['nb_sessions'] . ' sessions</span></td>';
                        echo '<td>';
                        
                        $first = true;
                        foreach ($details as $detail) {
                            $label = $first ? '<span class="badge badge-success">À CONSERVER</span>' : '<span class="badge badge-warning">À FERMER</span>';
                            echo '<div class="session-detail">';
                            echo $label . ' Session #' . $detail['id'] . ' - IP: ' . htmlspecialchars($detail['ip_address']) . ' - ';
                            echo 'Connexion: ' . $detail['date_connexion'];
                            if ($detail['last_activity_db']) {
                                echo ' - Dernière activité: ' . $detail['last_activity_db'];
                            }
                            echo '</div>';
                            $first = false;
                        }
                        
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                    
                    echo '<form method="POST" onsubmit="return confirm(\'Confirmer le nettoyage de ' . $nbUsers . ' utilisateur(s) ?\');">';
                    echo '<button type="submit" name="cleanup">🧹 Nettoyer maintenant</button>';
                    echo '</form>';
                }
            }
            
            // Statistiques globales
            $stats = $pdo->query("
                SELECT 
                    COUNT(DISTINCT user_id) as nb_users,
                    COUNT(*) as total_sessions
                FROM connexions_log
                WHERE date_deconnexion IS NULL
                AND statut = 'success'
            ")->fetch(PDO::FETCH_ASSOC);
            
            echo '<div class="info-box" style="margin-top: 30px;">';
            echo '<strong>📊 Statistiques globales</strong><br>';
            echo 'Utilisateurs avec session active : ' . $stats['nb_users'] . '<br>';
            echo 'Total sessions actives : ' . $stats['total_sessions'];
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="error-box"><strong>❌ Erreur</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <a href="logs_connexions.php" class="back-link">← Retour aux logs de connexions</a>
    </div>
</body>
</html>
