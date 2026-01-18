<?php
require_once('wp-config.php');
require_once('auth.php');
verifierRole('administrator');

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$parPage = 50;
$offset = ($page - 1) * $parPage;

// Filtres
$filtreUsername = isset($_GET['username']) ? trim($_GET['username']) : '';
$filtreStatut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filtreDateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$filtreDateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construction de la requête
$whereConditions = [];
$params = [];

if ($filtreUsername) {
    $whereConditions[] = "username LIKE ?";
    $params[] = "%$filtreUsername%";
}

if ($filtreStatut) {
    $whereConditions[] = "statut = ?";
    $params[] = $filtreStatut;
}

if ($filtreDateDebut) {
    $whereConditions[] = "DATE(date_connexion) >= ?";
    $params[] = $filtreDateDebut;
}

if ($filtreDateFin) {
    $whereConditions[] = "DATE(date_connexion) <= ?";
    $params[] = $filtreDateFin;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Compter le total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM connexions_log $whereClause");
$stmtCount->execute($params);
$totalLogs = $stmtCount->fetchColumn();
$totalPages = ceil($totalLogs / $parPage);

// Récupérer les logs
$sql = "SELECT * FROM connexions_log $whereClause ORDER BY date_connexion DESC LIMIT $parPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'success' THEN 1 ELSE 0 END) as reussies,
        SUM(CASE WHEN statut = 'failed' THEN 1 ELSE 0 END) as echouees,
        AVG(duree_session) as duree_moyenne
    FROM connexions_log
    WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Connexions</title>
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
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
            z-index: 1000;
        }

        .back-link:hover {
            transform: translateY(-2px);
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 30px;
            max-width: 1400px;
            margin: 80px auto 20px;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }

        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-filter:hover {
            background: #5568d3;
        }

        .btn-reset {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-admin {
            background: #fff3cd;
            color: #856404;
        }

        .badge-benevole {
            background: #cfe2ff;
            color: #084298;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            align-items: center;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 60px 10px 10px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au dashboard</a>

    <div class="container">
        <h1>📊 Logs de Connexions</h1>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Connexions (30j)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['reussies']); ?></div>
                <div class="stat-label">Réussies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['echouees']); ?></div>
                <div class="stat-label">Échouées</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    if ($stats['duree_moyenne']) {
                        echo gmdate("H:i:s", (int)$stats['duree_moyenne']);
                    } else {
                        echo "N/A";
                    }
                    ?>
                </div>
                <div class="stat-label">Durée moyenne</div>
            </div>
        </div>

        <!-- Filtres -->
        <form method="GET" class="filters">
            <div class="filters-grid">
                <div class="form-group">
                    <label>Utilisateur</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($filtreUsername); ?>" placeholder="Nom d'utilisateur">
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut">
                        <option value="">Tous</option>
                        <option value="success" <?php echo $filtreStatut === 'success' ? 'selected' : ''; ?>>Réussi</option>
                        <option value="failed" <?php echo $filtreStatut === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date début</label>
                    <input type="date" name="date_debut" value="<?php echo htmlspecialchars($filtreDateDebut); ?>">
                </div>
                <div class="form-group">
                    <label>Date fin</label>
                    <input type="date" name="date_fin" value="<?php echo htmlspecialchars($filtreDateFin); ?>">
                </div>
            </div>
            <div>
                <button type="submit" class="btn-filter">🔍 Filtrer</button>
                <a href="logs_connexions.php" class="btn-reset" style="text-decoration: none; display: inline-block;">🔄 Réinitialiser</a>
            </div>
        </form>

        <!-- Tableau des logs -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Connexion</th>
                        <th>Déconnexion</th>
                        <th>Durée</th>
                        <th>IP</th>
                        <th>Statut</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $log['user_role'] === 'administrator' ? 'admin' : 'benevole'; ?>">
                                <?php echo htmlspecialchars($log['user_role']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['date_connexion'])); ?></td>
                        <td>
                            <?php 
                            if ($log['date_deconnexion']) {
                                echo date('d/m/Y H:i:s', strtotime($log['date_deconnexion']));
                            } else {
                                echo '<em style="color: #999;">En cours</em>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($log['duree_session']) {
                                echo gmdate("H:i:s", $log['duree_session']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><code style="font-size: 11px;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                        <td>
                            <span class="badge badge-<?php echo $log['statut'] === 'success' ? 'success' : 'danger'; ?>">
                                <?php echo $log['statut'] === 'success' ? '✓ Réussi' : '✗ Échoué'; ?>
                            </span>
                        </td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['message']); ?>">
                            <?php echo htmlspecialchars($log['message']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                            Aucun log trouvé
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $filtreUsername ? '&username=' . urlencode($filtreUsername) : ''; ?><?php echo $filtreStatut ? '&statut=' . $filtreStatut : ''; ?>">« Précédent</a>
            <?php endif; ?>
            
            <span class="active"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $filtreUsername ? '&username=' . urlencode($filtreUsername) : ''; ?><?php echo $filtreStatut ? '&statut=' . $filtreStatut : ''; ?>">Suivant »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
