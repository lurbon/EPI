<?php
require_once('wp-config.php');
require_once('auth.php');
verifierRole('admin');

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    error_log("Erreur connexion DB profils: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// Récupérer tous les utilisateurs depuis connexions_log (méthode sécurisée)
$utilisateurs = [];

try {
    // Récupérer la liste des utilisateurs avec leurs dernières informations
    $stmt = $pdo->query("
        SELECT
            c1.username,
            c1.user_role
        FROM connexions_log c1
        INNER JOIN (
            SELECT username, MAX(date_connexion) as max_date
            FROM connexions_log
            WHERE statut = 'success'
            GROUP BY username
        ) c2 ON c1.username = c2.username AND c1.date_connexion = c2.max_date
        WHERE c1.statut = 'success'
        GROUP BY c1.username
        ORDER BY c1.username
    ");
    $utilisateurs_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($utilisateurs_log as $user_log) {
        // Récupérer la dernière connexion réussie
        $stmtConnexion = $pdo->prepare("
            SELECT
                date_connexion,
                ip_address,
                user_agent,
                TIMESTAMPDIFF(MINUTE, date_connexion, NOW()) as minutes_depuis
            FROM connexions_log
            WHERE username = ? AND statut = 'success'
            ORDER BY date_connexion DESC
            LIMIT 1
        ");
        $stmtConnexion->execute([$user_log['username']]);
        $derniereConnexion = $stmtConnexion->fetch(PDO::FETCH_ASSOC);

        // Récupérer le nombre total de connexions
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM connexions_log
            WHERE username = ? AND statut = 'success'
        ");
        $stmtCount->execute([$user_log['username']]);
        $countData = $stmtCount->fetch(PDO::FETCH_ASSOC);

        // Déterminer le rôle
        $role = $user_log['user_role'] ?? 'user';
        $roles = [$role];

        $utilisateurs[] = [
            'slug' => $user_log['username'],
            'name' => $user_log['user_name'] ?: $user_log['username'],
            'email' => $user_log['user_email'] ?: 'N/A',
            'roles' => $roles,
            'derniere_connexion' => $derniereConnexion,
            'nombre_connexions' => $countData['total'] ?? 0,
            'est_connecte' => $derniereConnexion && $derniereConnexion['minutes_depuis'] <= 30
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur récupération utilisateurs: " . $e->getMessage());
    // Continuer avec tableau vide si erreur
}

// Statistiques
$statsStmt = $pdo->query("
    SELECT
        COUNT(DISTINCT username) as utilisateurs_actifs_30j,
        COUNT(*) as connexions_30j
    FROM connexions_log
    WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND statut = 'success'
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Compter les utilisateurs actuellement connectés
$connectesStmt = $pdo->query("
    SELECT COUNT(DISTINCT username) as connectes
    FROM connexions_log
    WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    AND statut = 'success'
");
$connectesData = $connectesStmt->fetch(PDO::FETCH_ASSOC);
$utilisateursConnectes = $connectesData['connectes'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profils Utilisateurs</title>
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

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .container {
            max-width: 1400px;
            margin: 80px auto 40px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .user-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .user-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }

        .user-card.connected {
            border-color: #4CAF50;
            background: linear-gradient(to right, #ffffff 0%, #f1f8f4 100%);
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-badge.online {
            background: #4CAF50;
            color: white;
        }

        .status-badge.offline {
            background: #e0e0e0;
            color: #666;
        }

        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-email {
            font-size: 14px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: bold;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .role-administrator {
            background: #FF6B6B;
            color: white;
        }

        .role-benevole {
            background: #4ECDC4;
            color: white;
        }

        .role-other {
            background: #95A5A6;
            color: white;
        }

        .no-data {
            color: #999;
            font-style: italic;
        }

        .last-activity {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #667eea;
            color: white;
        }

        .filter-btn.active {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .users-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au Dashboard</a>

    <div class="container">
        <h1>👥 Profils Utilisateurs</h1>
        <p class="subtitle">Vue d'ensemble de tous les utilisateurs de l'application</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($utilisateurs); ?></div>
                <div class="stat-label">Utilisateurs Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $utilisateursConnectes; ?></div>
                <div class="stat-label">Connectés (< 30 min)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['utilisateurs_actifs_30j'] ?? 0; ?></div>
                <div class="stat-label">Actifs (30 jours)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['connexions_30j'] ?? 0; ?></div>
                <div class="stat-label">Connexions (30 jours)</div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterUsers('all')">Tous</button>
                <button class="filter-btn" onclick="filterUsers('connected')">🟢 Connectés</button>
                <button class="filter-btn" onclick="filterUsers('offline')">⚫ Hors ligne</button>
                <button class="filter-btn" onclick="filterUsers('administrator')">👑 Admins</button>
                <button class="filter-btn" onclick="filterUsers('benevole')">🤝 Bénévoles</button>
            </div>
        </div>

        <div class="users-grid" id="usersGrid">
            <?php foreach ($utilisateurs as $user): ?>
                <?php
                    $roles = $user['roles'] ?? [];
                    $role = !empty($roles) ? $roles[0] : 'user';
                    $roleClass = in_array('administrator', $roles) ? 'administrator' :
                                (in_array('benevole', $roles) ? 'benevole' : 'other');
                    $roleLabel = in_array('administrator', $roles) ? 'Administrateur' :
                                (in_array('benevole', $roles) ? 'Bénévole' : ucfirst($role));

                    $initiales = '';
                    if (!empty($user['name'])) {
                        $parts = explode(' ', $user['name']);
                        $initiales = strtoupper(substr($parts[0], 0, 1));
                        if (count($parts) > 1) {
                            $initiales .= strtoupper(substr($parts[count($parts)-1], 0, 1));
                        }
                    } else {
                        $initiales = strtoupper(substr($user['slug'], 0, 2));
                    }
                ?>
                <div class="user-card <?php echo $user['est_connecte'] ? 'connected' : ''; ?>"
                     data-status="<?php echo $user['est_connecte'] ? 'connected' : 'offline'; ?>"
                     data-role="<?php echo $roleClass; ?>">

                    <span class="status-badge <?php echo $user['est_connecte'] ? 'online' : 'offline'; ?>">
                        <?php echo $user['est_connecte'] ? '🟢 En ligne' : '⚫ Hors ligne'; ?>
                    </span>

                    <div class="user-header">
                        <div class="user-avatar"><?php echo htmlspecialchars($initiales); ?></div>
                        <div class="user-info">
                            <div class="user-name" title="<?php echo htmlspecialchars($user['name'] ?? $user['slug']); ?>">
                                <?php echo htmlspecialchars($user['name'] ?? $user['slug']); ?>
                            </div>
                            <div class="user-email" title="<?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="user-details">
                        <div class="detail-row">
                            <span class="detail-label">Rôle:</span>
                            <span class="detail-value">
                                <span class="role-badge role-<?php echo $roleClass; ?>">
                                    <?php echo htmlspecialchars($roleLabel); ?>
                                </span>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Connexions:</span>
                            <span class="detail-value"><?php echo $user['nombre_connexions']; ?></span>
                        </div>

                        <?php if ($user['derniere_connexion']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Dernière connexion:</span>
                                <span class="detail-value">
                                    <?php
                                        $date = new DateTime($user['derniere_connexion']['date_connexion']);
                                        echo $date->format('d/m/Y H:i');
                                    ?>
                                </span>
                            </div>

                            <div class="last-activity">
                                📍 IP: <?php echo htmlspecialchars($user['derniere_connexion']['ip_address']); ?><br>
                                🕒 Il y a <?php echo $user['derniere_connexion']['minutes_depuis']; ?> minute(s)
                            </div>
                        <?php else: ?>
                            <div class="detail-row">
                                <span class="detail-label">Dernière connexion:</span>
                                <span class="detail-value no-data">Jamais connecté</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($utilisateurs)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <p>Aucun utilisateur trouvé.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterUsers(filter) {
            const cards = document.querySelectorAll('.user-card');
            const buttons = document.querySelectorAll('.filter-btn');

            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Filter cards
            cards.forEach(card => {
                let show = false;

                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'connected':
                        show = card.dataset.status === 'connected';
                        break;
                    case 'offline':
                        show = card.dataset.status === 'offline';
                        break;
                    case 'administrator':
                        show = card.dataset.role === 'administrator';
                        break;
                    case 'benevole':
                        show = card.dataset.role === 'benevole';
                        break;
                }

                card.style.display = show ? 'block' : 'none';
            });
        }

        // Auto-refresh every 2 minutes
        setTimeout(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>