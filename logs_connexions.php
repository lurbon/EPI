<?php
require_once('config.php');
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
    error_log("Erreur connexion DB logs_connexions: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// ---------- Pagination ----------
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$parPage = 50;
$offset = ($page - 1) * $parPage;

// ---------- Filtres ----------
$filtreUsername = isset($_GET['username']) ? trim($_GET['username']) : '';
$filtreStatut  = isset($_GET['statut'])   ? $_GET['statut']   : '';
$filtreRole    = isset($_GET['role'])     ? $_GET['role']     : '';
$filtreDateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$filtreDateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : '';

// Construction de la requête avec filtres
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
if ($filtreRole) {
    $whereConditions[] = "user_role = ?";
    $params[] = $filtreRole;
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

// ---------- Total pour pagination ----------
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM connexions_log $whereClause");
$stmtCount->execute($params);
$totalLogs = $stmtCount->fetchColumn();
$totalPages = ceil($totalLogs / $parPage);

// ---------- Récupération des logs (données principales du tableau) ----------
$sql = "SELECT * FROM connexions_log $whereClause ORDER BY date_connexion DESC LIMIT $parPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Statistiques globales (30 derniers jours) ----------
$statsStmt = $pdo->query("
    SELECT
        COUNT(*)                                                        AS total,
        SUM(CASE WHEN statut = 'success' THEN 1 ELSE 0 END)           AS reussies,
        SUM(CASE WHEN statut = 'failed'  THEN 1 ELSE 0 END)           AS echouees,
        COUNT(DISTINCT CASE WHEN statut = 'success' THEN username END) AS utilisateurs_actifs,
        AVG(duree_session)                                              AS duree_moyenne
    FROM connexions_log
    WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Utilisateurs connectés ces 30 dernières minutes
$connectesStmt = $pdo->query("
    SELECT COUNT(DISTINCT username) AS connectes
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
            top: 30px;
            left: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 24px;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 3px solid #dc3545;
        }

        .back-link:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.7);
            border-color: #c82333;
        }

        .back-link:active {
            transform: translateY(-2px) scale(1.05);
        }

        /* Tooltip au survol */
        .back-link::before {
            content: 'Retour au tableau de bord';
            position: absolute;
            left: 70px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .back-link:hover::before {
            opacity: 1;
        }


        /* ---------- Conteneur principal ---------- */
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 30px;
            max-width: 1500px;
            margin: 80px auto 20px;
        }

        h1 {
            color: #667eea;
            margin-bottom: 8px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 25px;
        }

        /* ---------- Stat cards ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 14px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-card.highlight {
            background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
        }
        .stat-number {
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }

        /* ---------- Filtres ---------- */
        .filters {
            background: #f8f9fa;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-filter {
            padding: 8px 18px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s;
        }
        .btn-filter:hover { background: #5568d3; }
        .btn-reset {
            padding: 8px 18px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-reset:hover { background: #5a6268; }

        /* ---------- Tableau ---------- */
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }
        th {
            background: #667eea;
            color: white;
            padding: 11px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        th:first-child { border-radius: 0; }
        td {
            padding: 9px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #333;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f5f7ff; }

        /* ---------- Badges ---------- */
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-success   { background: #d4edda; color: #155724; }
        .badge-danger    { background: #f8d7da; color: #721c24; }
        .badge-admin     { background: #fff3cd; color: #856404; }
        .badge-benevole  { background: #cfe2ff; color: #084298; }
        .badge-other     { background: #e2e3e5; color: #383d41; }

        /* Indicateur en ligne */
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            margin-right: 5px;
            box-shadow: 0 0 6px rgba(76,175,80,0.5);
        }

        /* ---------- Pagination ---------- */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            align-items: center;
            margin-top: 18px;
            flex-wrap: wrap;
        }
        .pagination a,
        .pagination span {
            padding: 7px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            font-size: 13px;
            transition: background 0.2s;
        }
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: 600;
        }
        .pagination a:hover { background: #f0f2ff; }

        /* ---------- État vide ---------- */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 10px; }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .back-link {
                top: 20px; left: 20px;
                width: 55px; height: 55px;
                font-size: 22px;
            }
            .back-link::before { left: 65px; font-size: 12px; padding: 6px 10px; }
            .container { padding: 15px; margin: 60px 10px 10px; }
            .filters-grid { grid-template-columns: 1fr; }
            th, td { padding: 8px; font-size: 12px; }
            .stat-number { font-size: 24px; }
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='dashboard.php'" class="back-link">🏠</button>

    <div class="container">
        <h1>📊 Logs de Connexions</h1>
        <p class="subtitle">Historique détaillé de toutes les connexions et statut des utilisateurs</p>

        <!-- Statistiques (30 derniers jours) -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-number"><?php echo $utilisateursConnectes; ?></div>
                <div class="stat-label">Connectés maintenant</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['utilisateurs_actifs'] ?? 0); ?></div>
                <div class="stat-label">Utilisateurs actifs (30j)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Connexions totales (30j)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['reussies'] ?? 0); ?></div>
                <div class="stat-label">Réussies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['echouees'] ?? 0); ?></div>
                <div class="stat-label">Échouées</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['duree_moyenne'] ? gmdate("H:i:s", (int)$stats['duree_moyenne']) : 'N/A'; ?>
                </div>
                <div class="stat-label">Durée moy. session</div>
            </div>
        </div>

        <!-- Filtres -->
        <form method="GET" class="filters">
            <div class="filters-grid">
                <div class="form-group">
                    <label>Utilisateur</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($filtreUsername); ?>" placeholder="Rechercher...">
                </div>
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role">
                        <option value="">Tous les rôles</option>
                        <option value="administrator" <?php echo $filtreRole === 'administrator' ? 'selected' : ''; ?>>👑 Administrateur</option>
                        <option value="benevole"       <?php echo $filtreRole === 'benevole'       ? 'selected' : ''; ?>>🤝 Bénévole</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut">
                        <option value="">Tous</option>
                        <option value="success" <?php echo $filtreStatut === 'success' ? 'selected' : ''; ?>>✓ Réussi</option>
                        <option value="failed"  <?php echo $filtreStatut === 'failed'  ? 'selected' : ''; ?>>✗ Échoué</option>
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
            <div class="filter-actions">
                <button type="submit" class="btn-filter">🔍 Filtrer</button>
                <a href="logs_connexions.php" class="btn-reset">🔄 Réinitialiser</a>
            </div>
        </form>

        <!-- Tableau principal -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Date connexion</th>
                        <th>Date déconnexion</th>
                        <th>Durée session</th>
                        <th>IP</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Déterminer les utilisateurs connectés ces 30 dernières minutes
                    // pour afficher le point vert dans le tableau
                    $connectesRecents = [];
                    $stmtConnectes = $pdo->query("
                        SELECT DISTINCT username
                        FROM connexions_log
                        WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                          AND statut = 'success'
                    ");
                    foreach ($stmtConnectes->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $connectesRecents[$row['username']] = true;
                    }
                    ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $estConnecte = isset($connectesRecents[$log['username']]);
                        $role        = $log['user_role'] ?? 'other';
                        $roleClass   = ($role === 'administrator') ? 'admin' : (($role === 'benevole') ? 'benevole' : 'other');
                        $roleLabel   = ($role === 'administrator') ? '👑 Administrateur' : (($role === 'benevole') ? '🤝 Bénévole' : ucfirst($role));
                    ?>
                    <tr>
                        <td style="color: #999; font-size: 12px;"><?php echo $log['id']; ?></td>
                        <td>
                            <?php if ($estConnecte): ?><span class="online-dot"></span><?php endif; ?>
                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $roleClass; ?>">
                                <?php echo htmlspecialchars($roleLabel); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $log['statut'] === 'success' ? 'success' : 'danger'; ?>">
                                <?php echo $log['statut'] === 'success' ? '✓ Réussi' : '✗ Échoué'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['date_connexion'])); ?></td>
                        <td>
                            <?php if ($log['date_deconnexion']): ?>
                                <?php echo date('d/m/Y H:i:s', strtotime($log['date_deconnexion'])); ?>
                            <?php else: ?>
                                <em style="color: #4CAF50; font-size: 12px;">En cours</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $log['duree_session'] ? gmdate("H:i:s", $log['duree_session']) : '—'; ?>
                        </td>
                        <td><code style="font-size: 11px; color: #555;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                        <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #666;"
                            title="<?php echo htmlspecialchars($log['message']); ?>">
                            <?php echo htmlspecialchars($log['message']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="icon">🔍</div>
                                <p>Aucun log trouvé pour ces critères.</p>
                            </div>
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
                <a href="?page=<?php echo $page - 1; ?>
                    <?php echo $filtreUsername  ? '&username='   . urlencode($filtreUsername) : ''; ?>
                    <?php echo $filtreStatut    ? '&statut='     . urlencode($filtreStatut)  : ''; ?>
                    <?php echo $filtreRole      ? '&role='       . urlencode($filtreRole)    : ''; ?>
                    <?php echo $filtreDateDebut ? '&date_debut=' . urlencode($filtreDateDebut) : ''; ?>
                    <?php echo $filtreDateFin   ? '&date_fin='   . urlencode($filtreDateFin)   : ''; ?>
                ">« Précédent</a>
            <?php endif; ?>

            <span class="active"><?php echo $page; ?> / <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>
                    <?php echo $filtreUsername  ? '&username='   . urlencode($filtreUsername) : ''; ?>
                    <?php echo $filtreStatut    ? '&statut='     . urlencode($filtreStatut)  : ''; ?>
                    <?php echo $filtreRole      ? '&role='       . urlencode($filtreRole)    : ''; ?>
                    <?php echo $filtreDateDebut ? '&date_debut=' . urlencode($filtreDateDebut) : ''; ?>
                    <?php echo $filtreDateFin   ? '&date_fin='   . urlencode($filtreDateFin)   : ''; ?>
                ">Suivant »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh toutes les 2 minutes
        setTimeout(() => { location.reload(); }, 120000);
    </script>
</body>
</html>
