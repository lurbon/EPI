<?php
// Charger la configuration WordPress
require_once('wp-config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

// Connexion à la base de données
$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

// Connexion PDO
try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Erreur de connexion BDD statistiques_benevoles: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez contacter l'administrateur.");
}

// Paramètres de filtre
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
$benevoleFiltre = isset($_GET['benevole']) ? $_GET['benevole'] : '';

// Récupérer les années disponibles
$annees = [];
try {
    $stmt = $conn->query("SELECT DISTINCT YEAR(date_mission) as annee FROM EPI_mission WHERE date_mission IS NOT NULL ORDER BY annee DESC");
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

if (empty($annees)) {
    $annees = [date('Y')];
}

// Récupérer la liste des bénévoles
$benevoles = [];
try {
    $stmt = $conn->query("SELECT DISTINCT benevole FROM EPI_mission WHERE benevole IS NOT NULL AND TRIM(benevole) != '' ORDER BY benevole");
    $benevoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Récupérer les statistiques par bénévole et par mois
$statistiques = [];
try {
    $sql = "SELECT
                benevole,
                MONTH(date_mission) as mois,
                YEAR(date_mission) as annee,
                SUM(COALESCE(km_saisi, 0)) as total_km,
                SEC_TO_TIME(SUM(TIME_TO_SEC(COALESCE(duree, '00:00:00')))) as total_duree,
                COUNT(*) as nb_missions
            FROM EPI_mission
            WHERE benevole IS NOT NULL
            AND TRIM(benevole) != ''
            AND YEAR(date_mission) = :annee";

    $params = [':annee' => $annee];

    if (!empty($benevoleFiltre)) {
        $sql .= " AND benevole = :benevole";
        $params[':benevole'] = $benevoleFiltre;
    }

    $sql .= " GROUP BY benevole, YEAR(date_mission), MONTH(date_mission)
              ORDER BY benevole, mois";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données par bénévole
    foreach ($resultats as $row) {
        $nom = $row['benevole'];
        if (!isset($statistiques[$nom])) {
            $statistiques[$nom] = [
                'mois' => array_fill(1, 12, ['km' => 0, 'duree' => '00:00:00', 'missions' => 0]),
                'total_km' => 0,
                'total_duree_sec' => 0,
                'total_missions' => 0
            ];
        }
        $statistiques[$nom]['mois'][$row['mois']] = [
            'km' => floatval($row['total_km']),
            'duree' => $row['total_duree'],
            'missions' => intval($row['nb_missions'])
        ];
        $statistiques[$nom]['total_km'] += floatval($row['total_km']);

        // Convertir la durée en secondes pour le total
        $parts = explode(':', $row['total_duree']);
        $seconds = (intval($parts[0]) * 3600) + (intval($parts[1]) * 60) + (isset($parts[2]) ? intval($parts[2]) : 0);
        $statistiques[$nom]['total_duree_sec'] += $seconds;
        $statistiques[$nom]['total_missions'] += intval($row['nb_missions']);
    }

    // Convertir le total des secondes en format HH:MM
    foreach ($statistiques as &$stat) {
        $heures = floor($stat['total_duree_sec'] / 3600);
        $minutes = floor(($stat['total_duree_sec'] % 3600) / 60);
        $stat['total_duree'] = sprintf('%02d:%02d', $heures, $minutes);
    }
    unset($stat); // Important: libérer la référence pour éviter les bugs

} catch(PDOException $e) {
    error_log("Erreur récupération statistiques: " . $e->getMessage());
}

// Noms des mois en français
$nomsMois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Fonction pour formater la durée
function formaterDuree($duree) {
    if (empty($duree) || $duree === '00:00:00') return '-';
    $parts = explode(':', $duree);
    $heures = intval($parts[0]);
    $minutes = intval($parts[1]);
    if ($heures === 0 && $minutes === 0) return '-';
    if ($heures === 0) return $minutes . 'min';
    if ($minutes === 0) return $heures . 'h';
    return $heures . 'h' . sprintf('%02d', $minutes);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Bénévoles - KM et Durées</title>
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
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            transition: all 0.3s ease;
            z-index: 1000;
            border: none;
            cursor: pointer;
        }

        .back-link:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.7);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding-top: 30px;
        }

        .header-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filters {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            min-width: 200px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .stats-table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            position: sticky;
            top: 0;
        }

        th:first-child {
            text-align: left;
            padding-left: 15px;
            border-radius: 10px 0 0 0;
        }

        th:last-child {
            border-radius: 0 10px 0 0;
        }

        td:first-child {
            text-align: left;
            padding-left: 15px;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .month-cell {
            font-size: 0.85rem;
        }

        .km-value {
            color: #2196F3;
            font-weight: 600;
        }

        .duree-value {
            color: #4CAF50;
            font-size: 0.8rem;
        }

        .total-row {
            background: rgba(102, 126, 234, 0.1) !important;
            font-weight: 700;
        }

        .total-row td {
            border-top: 2px solid #667eea;
        }

        .cell-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .no-data {
            color: #ccc;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .summary-card .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }

        .summary-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .export-btn {
            margin-top: 15px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .back-link, .filters, .btn {
                display: none !important;
            }
            .header-card, .stats-table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            .back-link {
                top: 15px;
                left: 15px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            h1 {
                font-size: 1.5rem;
            }
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='dashboard.php'" class="back-link" title="Retour au tableau de bord">🏠</button>

    <div class="container">
        <div class="header-card">
            <h1>📊 Statistiques Bénévoles - KM et Durées</h1>

            <form method="GET" class="filters">
                <div class="filter-group">
                    <label for="annee">Année</label>
                    <select name="annee" id="annee" onchange="this.form.submit()">
                        <?php foreach ($annees as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $annee ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="benevole">Bénévole</label>
                    <select name="benevole" id="benevole" onchange="this.form.submit()">
                        <option value="">-- Tous les bénévoles --</option>
                        <?php foreach ($benevoles as $b): ?>
                            <option value="<?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo $b === $benevoleFiltre ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" style="justify-content: flex-end;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        🖨️ Imprimer
                    </button>
                </div>
            </form>
        </div>

        <?php
        // Calculer les totaux globaux
        $totalGlobalKm = 0;
        $totalGlobalDureeSec = 0;
        $totalGlobalMissions = 0;
        $nbBenevoles = count($statistiques);

        foreach ($statistiques as $stat) {
            $totalGlobalKm += $stat['total_km'];
            $totalGlobalDureeSec += $stat['total_duree_sec'];
            $totalGlobalMissions += $stat['total_missions'];
        }

        $totalGlobalHeures = floor($totalGlobalDureeSec / 3600);
        $totalGlobalMinutes = floor(($totalGlobalDureeSec % 3600) / 60);
        ?>

        <div class="summary-cards">
            <div class="summary-card">
                <div class="icon">👥</div>
                <div class="value"><?php echo $nbBenevoles; ?></div>
                <div class="label">Bénévoles actifs</div>
            </div>
            <div class="summary-card">
                <div class="icon">🚗</div>
                <div class="value"><?php echo number_format($totalGlobalKm, 0, ',', ' '); ?></div>
                <div class="label">Kilomètres total</div>
            </div>
            <div class="summary-card">
                <div class="icon">⏱️</div>
                <div class="value"><?php echo $totalGlobalHeures; ?>h<?php echo sprintf('%02d', $totalGlobalMinutes); ?></div>
                <div class="label">Durée totale</div>
            </div>
            <div class="summary-card">
                <div class="icon">📋</div>
                <div class="value"><?php echo $totalGlobalMissions; ?></div>
                <div class="label">Missions réalisées</div>
            </div>
        </div>

        <div class="stats-table-container">
            <?php if (empty($statistiques)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    Aucune donnée disponible pour <?php echo $annee; ?>
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bénévole</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th><?php echo substr($nomsMois[$m], 0, 3); ?></th>
                            <?php endfor; ?>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistiques as $nom => $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="month-cell">
                                        <?php if ($data['mois'][$m]['km'] > 0 || $data['mois'][$m]['missions'] > 0): ?>
                                            <div class="cell-content">
                                                <span class="km-value">
                                                    <?php echo number_format($data['mois'][$m]['km'], 0, ',', ' '); ?> km
                                                </span>
                                                <span class="duree-value">
                                                    <?php echo formaterDuree($data['mois'][$m]['duree']); ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="month-cell">
                                    <div class="cell-content">
                                        <span class="km-value">
                                            <?php echo number_format($data['total_km'], 0, ',', ' '); ?> km
                                        </span>
                                        <span class="duree-value">
                                            <?php echo $data['total_duree']; ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Ligne de total -->
                        <tr class="total-row">
                            <td>TOTAL <?php echo $annee; ?></td>
                            <?php
                            for ($m = 1; $m <= 12; $m++):
                                $kmMois = 0;
                                $dureeMoisSec = 0;
                                foreach ($statistiques as $data) {
                                    $kmMois += $data['mois'][$m]['km'];
                                    $parts = explode(':', $data['mois'][$m]['duree']);
                                    $dureeMoisSec += (intval($parts[0]) * 3600) + (intval($parts[1]) * 60);
                                }
                                $hMois = floor($dureeMoisSec / 3600);
                                $minMois = floor(($dureeMoisSec % 3600) / 60);
                            ?>
                                <td class="month-cell">
                                    <?php if ($kmMois > 0): ?>
                                        <div class="cell-content">
                                            <span class="km-value">
                                                <?php echo number_format($kmMois, 0, ',', ' '); ?> km
                                            </span>
                                            <span class="duree-value">
                                                <?php echo $hMois > 0 ? $hMois . 'h' . sprintf('%02d', $minMois) : '-'; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-data">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="month-cell">
                                <div class="cell-content">
                                    <span class="km-value">
                                        <?php echo number_format($totalGlobalKm, 0, ',', ' '); ?> km
                                    </span>
                                    <span class="duree-value">
                                        <?php echo $totalGlobalHeures; ?>h<?php echo sprintf('%02d', $totalGlobalMinutes); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>