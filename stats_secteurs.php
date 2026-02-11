<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire','chauffeur','benevole']);

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
    error_log("Erreur de connexion BDD statistiques_secteurs: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez contacter l'administrateur.");
}

// Paramètres de filtre
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
$secteurFiltre = isset($_GET['secteur']) ? $_GET['secteur'] : '';

// Récupérer les années disponibles
$annees = [];
try {
    $stmt = $conn->query("SELECT DISTINCT YEAR(date_mission) as annee FROM EPI_mission WHERE date_mission IS NOT NULL ORDER BY annee DESC");
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

if (empty($annees)) {
    $annees = [date('Y')];
}

// Récupérer la liste des secteurs
$secteurs = [];
try {
    $stmt = $conn->query("SELECT DISTINCT secteur_aide FROM EPI_mission WHERE secteur_aide IS NOT NULL AND TRIM(secteur_aide) != '' ORDER BY secteur_aide");
    $secteurs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Récupérer les statistiques par secteur et par mois
$statistiques = [];
try {
    $sql = "SELECT
                secteur_aide,
                MONTH(date_mission) as mois,
                YEAR(date_mission) as annee,
                SUM(COALESCE(km_saisi, 0)) as total_km,
                COUNT(*) as nb_missions,
                COUNT(DISTINCT benevole) as nb_benevoles
            FROM EPI_mission
            WHERE secteur_aide IS NOT NULL
            AND TRIM(secteur_aide) != ''
            AND YEAR(date_mission) = :annee";

    $params = [':annee' => $annee];

    if (!empty($secteurFiltre)) {
        $sql .= " AND secteur_aide = :secteur";
        $params[':secteur'] = $secteurFiltre;
    }

    $sql .= " GROUP BY secteur_aide, YEAR(date_mission), MONTH(date_mission)
              ORDER BY secteur_aide, mois";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données par secteur
    foreach ($resultats as $row) {
        $nom = $row['secteur_aide'];
        if (!isset($statistiques[$nom])) {
            $statistiques[$nom] = [
                'mois' => array_fill(1, 12, ['km' => 0, 'duree' => '00:00:00', 'missions' => 0, 'benevoles' => 0]),
                'total_km' => 0,
                'total_duree_sec' => 0,
                'total_missions' => 0,
                'total_benevoles' => 0
            ];
        }
        $statistiques[$nom]['mois'][$row['mois']] = [
            'km' => floatval($row['total_km']),
            'duree' => '00:00:00',
            'missions' => intval($row['nb_missions']),
            'benevoles' => intval($row['nb_benevoles'])
        ];
        $statistiques[$nom]['total_km'] += floatval($row['total_km']);
        $statistiques[$nom]['total_missions'] += intval($row['nb_missions']);
    }

    // Calculer le nombre total de bénévoles distincts par secteur (sur toute l'année)
    $sql_benevoles = "SELECT
                        secteur_aide,
                        COUNT(DISTINCT benevole) as total_benevoles
                      FROM EPI_mission
                      WHERE secteur_aide IS NOT NULL
                      AND TRIM(secteur_aide) != ''
                      AND YEAR(date_mission) = :annee";
    
    if (!empty($secteurFiltre)) {
        $sql_benevoles .= " AND secteur_aide = :secteur";
    }
    
    $sql_benevoles .= " GROUP BY secteur_aide";
    
    $stmt_benevoles = $conn->prepare($sql_benevoles);
    $stmt_benevoles->execute($params);
    $benevoles_totaux = $stmt_benevoles->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($statistiques as $nom => &$stat) {
        $stat['total_benevoles'] = isset($benevoles_totaux[$nom]) ? $benevoles_totaux[$nom] : 0;
    }
    unset($stat);

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
    <title>Statistiques par Secteur - Missions et KM</title>
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


        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding-left: 90px;
        }

        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 25px;
            text-align: center;
        }

        .filters {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }

        select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
        }

        select:hover {
            border-color: #667eea;
        }

        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .summary-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .stats-table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        th:first-child {
            text-align: left;
            padding-left: 20px;
            min-width: 150px;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9ff;
        }

        td {
            padding: 12px 10px;
            text-align: center;
        }

        td:first-child {
            text-align: left;
            font-weight: 600;
            color: #333;
            padding-left: 20px;
        }

        .month-cell {
            font-size: 0.85rem;
        }

        .cell-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }

        .missions-value {
            color: #667eea;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .km-value {
            color: #999;
            font-size: 0.8rem;
        }

        .benevoles-value {
            color: #28a745;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .no-data {
            color: #ccc;
            font-size: 1.2rem;
        }

        /* Styles pour les lignes KPI */
        .kpi-label {
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 12px;
            text-align: left;
            min-width: 100px;
        }

        .kpi-missions {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
            border-left: 4px solid #1976d2;
        }

        .kpi-km {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f57c00;
            border-left: 4px solid #f57c00;
        }

        .kpi-benevoles {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #388e3c;
            border-left: 4px solid #388e3c;
        }

        /* Valeurs dans les cellules pour chaque KPI */
        .value-missions {
            color: #1976d2;
            font-weight: 600;
        }

        .value-km {
            color: #f57c00;
            font-weight: 600;
        }

        .value-benevoles {
            color: #388e3c;
            font-weight: 600;
        }

        /* Total rows avec couleurs */
        .total-kpi-missions {
            background: #1976d2;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .total-kpi-km {
            background: #f57c00;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .total-kpi-benevoles {
            background: #388e3c;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
        }

        .total-row {
            background-color: #f8f9ff !important;
            font-weight: 700;
        }

        .total-row td {
            padding: 15px 10px;
            border-top: 2px solid #667eea;
        }

        .total-row .missions-value {
            font-size: 1rem;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .back-link {
                display: none;
            }

            .container {
                padding-left: 0;
            }

            .btn {
                display: none;
            }

            .header-card,
            .summary-cards,
            .stats-table-container {
                box-shadow: none;
                break-inside: avoid;
            }

            table {
                min-width: auto;
            }

            th, td {
                padding: 8px 5px;
                font-size: 0.8rem;
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding-left: 20px;
            }

            .back-link {
                top: 20px;
                left: 20px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .back-link::before {
                left: 60px;
                font-size: 12px;
                padding: 6px 10px;
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
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='dashboard.php'" class="back-link">🏠</button>

    <div class="container">
        <div class="header-card">
            <h1>📊 Statistiques par Secteur - Missions et KM</h1>

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
                    <label for="secteur">Secteur</label>
                    <select name="secteur" id="secteur" onchange="this.form.submit()">
                        <option value="">-- Tous les secteurs --</option>
                        <?php foreach ($secteurs as $s): ?>
                            <option value="<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo $s === $secteurFiltre ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php
        // Calculer les totaux globaux
        $totalGlobalKm = 0;
        $totalGlobalDureeSec = 0;
        $totalGlobalMissions = 0;
        $nbSecteurs = count($statistiques);

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
                <div class="icon">🗺️</div>
                <div class="value"><?php echo $nbSecteurs; ?></div>
                <div class="label">Secteurs actifs</div>
            </div>
            <div class="summary-card">
                <div class="icon">📋</div>
                <div class="value"><?php echo $totalGlobalMissions; ?></div>
                <div class="label">Missions réalisées</div>
            </div>
            <div class="summary-card">
                <div class="icon">🚗</div>
                <div class="value"><?php echo number_format($totalGlobalKm, 0, ',', ' '); ?></div>
                <div class="label">Kilomètres total</div>
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
                            <th>Secteur</th>
                            <th style="min-width: 100px;">KPI</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th><?php echo substr($nomsMois[$m], 0, 3); ?></th>
                            <?php endfor; ?>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistiques as $nom => $data): ?>
                            <!-- Ligne Missions -->
                            <tr>
                                <td rowspan="3" style="vertical-align: middle; font-weight: 600;">
                                    <?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="kpi-label kpi-missions">Missions</td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="month-cell" style="text-align: center;">
                                        <?php if ($data['mois'][$m]['missions'] > 0): ?>
                                            <span class="value-missions"><?php echo $data['mois'][$m]['missions']; ?></span>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="month-cell" style="text-align: center;">
                                    <span class="value-missions" style="font-weight: 700; font-size: 1rem;">
                                        <?php echo $data['total_missions']; ?>
                                    </span>
                                </td>
                            </tr>
                            <!-- Ligne KM -->
                            <tr>
                                <td class="kpi-label kpi-km">KM</td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="month-cell" style="text-align: center;">
                                        <?php if ($data['mois'][$m]['km'] > 0): ?>
                                            <span class="value-km"><?php echo number_format($data['mois'][$m]['km'], 0, ',', ' '); ?></span>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="month-cell" style="text-align: center;">
                                    <span class="value-km" style="font-weight: 700; font-size: 1rem;">
                                        <?php echo number_format($data['total_km'], 0, ',', ' '); ?>
                                    </span>
                                </td>
                            </tr>
                            <!-- Ligne Bénévoles -->
                            <tr style="border-bottom: 2px solid #ddd;">
                                <td class="kpi-label kpi-benevoles">Bénévoles</td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="month-cell" style="text-align: center;">
                                        <?php if ($data['mois'][$m]['benevoles'] > 0): ?>
                                            <span class="value-benevoles"><?php echo $data['mois'][$m]['benevoles']; ?></span>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="month-cell" style="text-align: center;">
                                    <span class="value-benevoles" style="font-weight: 700; font-size: 1rem;">
                                        <?php echo $data['total_benevoles']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Lignes de total -->
                        <tr class="total-row">
                            <td rowspan="3" style="vertical-align: middle; font-weight: 700; background: #2c3e50; color: white;">
                                TOTAL <?php echo $annee; ?>
                            </td>
                            <td class="kpi-label total-kpi-missions">Missions</td>
                            <?php
                            for ($m = 1; $m <= 12; $m++):
                                $missionsMois = 0;
                                foreach ($statistiques as $data) {
                                    $missionsMois += $data['mois'][$m]['missions'];
                                }
                            ?>
                                <td class="month-cell" style="text-align: center; background: #e3f2fd;">
                                    <span class="value-missions" style="font-weight: 600;">
                                        <?php echo $missionsMois > 0 ? $missionsMois : '-'; ?>
                                    </span>
                                </td>
                            <?php endfor; ?>
                            <td class="month-cell" style="text-align: center; background: #1976d2; color: white;">
                                <span style="font-weight: 700; font-size: 1rem;">
                                    <?php echo $totalGlobalMissions; ?>
                                </span>
                            </td>
                        </tr>
                        <tr class="total-row">
                            <td class="kpi-label total-kpi-km">KM</td>
                            <?php
                            for ($m = 1; $m <= 12; $m++):
                                $kmMois = 0;
                                foreach ($statistiques as $data) {
                                    $kmMois += $data['mois'][$m]['km'];
                                }
                            ?>
                                <td class="month-cell" style="text-align: center; background: #fff3e0;">
                                    <span class="value-km" style="font-weight: 600;">
                                        <?php echo $kmMois > 0 ? number_format($kmMois, 0, ',', ' ') : '-'; ?>
                                    </span>
                                </td>
                            <?php endfor; ?>
                            <td class="month-cell" style="text-align: center; background: #f57c00; color: white;">
                                <span style="font-weight: 700; font-size: 1rem;">
                                    <?php echo number_format($totalGlobalKm, 0, ',', ' '); ?>
                                </span>
                            </td>
                        </tr>
                        <tr class="total-row">
                            <td class="kpi-label total-kpi-benevoles">Bénévoles</td>
                            <?php
                            for ($m = 1; $m <= 12; $m++):
                                $benevolesMois = 0;
                                foreach ($statistiques as $data) {
                                    $benevolesMois += $data['mois'][$m]['benevoles'];
                                }
                            ?>
                                <td class="month-cell" style="text-align: center; background: #e8f5e9;">
                                    <span class="value-benevoles" style="font-weight: 600;">
                                        <?php echo $benevolesMois > 0 ? $benevolesMois : '-'; ?>
                                    </span>
                                </td>
                            <?php endfor; ?>
                            <td class="month-cell" style="text-align: center; background: #388e3c; color: white;">
                                <span style="font-weight: 700; font-size: 1rem;">
                                    <?php 
                                    $totalGlobalBenevoles = 0;
                                    foreach ($statistiques as $data) {
                                        $totalGlobalBenevoles += $data['total_benevoles'];
                                    }
                                    echo $totalGlobalBenevoles; 
                                    ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Graphique camembert -->
        <?php if (!empty($statistiques)): ?>
        <div class="chart-container" style="margin-top: 40px; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-height: 600px;">
            <h2 style="text-align: center; color: #333; margin-bottom: 30px; font-size: 1.5rem;">
                📊 Répartition des Missions par Secteur (<?php echo $annee; ?>)
            </h2>
            
            <div style="max-width: 600px; margin: 0 auto; display: flex; justify-content: center; align-items: center; text-align: center;">
                <div id="pieChart" style="width: 100%; height: auto; display: inline-block; margin: 0 auto;">
                    <!-- Le graphique SVG sera généré ici -->
                </div>
            </div>
            
            <div id="chartLegend" style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <!-- La légende sera générée par JavaScript -->
            </div>
        </div>
        <?php else: ?>
        <div style="margin-top: 40px; background: white; border-radius: 20px; padding: 30px; text-align: center;">
            <p style="color: #999;">Aucune statistique disponible pour générer le graphique.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
    console.log('Script chargé');
    
    // Fonction pour créer un graphique camembert en SVG pur
    function createPieChart() {
        console.log('Création du graphique camembert...');
        
        // Données pour le graphique
        const secteurData = <?php 
            $chartData = [];
            foreach ($statistiques as $nom => $data) {
                if ($data['total_missions'] > 0) {
                    $chartData[] = [
                        'label' => addslashes($nom),
                        'missions' => intval($data['total_missions'])
                    ];
                }
            }
            echo json_encode($chartData, JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;

        console.log('Données:', secteurData);
        
        if (!secteurData || secteurData.length === 0) {
            console.error('Aucune donnée disponible');
            const container = document.querySelector('.chart-container');
            if (container) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Aucune donnée disponible pour le graphique</p>';
            }
            return;
        }

        // Trier par nombre de missions décroissant
        secteurData.sort((a, b) => b.missions - a.missions);

        // Couleurs
        const colors = [
            '#1976d2', '#f57c00', '#388e3c', '#d32f2f', '#7b1fa2',
            '#0097a7', '#c2185b', '#5d4037', '#303f9f', '#fbc02d',
            '#689f38', '#e64a19', '#455a64', '#00796b', '#afb42b'
        ];

        // Calculer le total
        const total = secteurData.reduce((sum, item) => sum + item.missions, 0);
        
        // Créer le SVG
        const svgContainer = document.getElementById('pieChart');
        if (!svgContainer) {
            console.error('Container pieChart introuvable');
            return;
        }
        
        const size = 400;
        const centerX = size / 2;
        const centerY = size / 2;
        const radius = size / 2 - 20;
        
        let svg = '<svg width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">';
        
        let currentAngle = -90; // Commencer en haut
        
        secteurData.forEach((item, index) => {
            const percentage = (item.missions / total) * 100;
            const angle = (item.missions / total) * 360;
            const color = colors[index % colors.length];
            
            // Calculer les coordonnées du path
            const startAngle = currentAngle * Math.PI / 180;
            const endAngle = (currentAngle + angle) * Math.PI / 180;
            
            const x1 = centerX + radius * Math.cos(startAngle);
            const y1 = centerY + radius * Math.sin(startAngle);
            const x2 = centerX + radius * Math.cos(endAngle);
            const y2 = centerY + radius * Math.sin(endAngle);
            
            const largeArc = angle > 180 ? 1 : 0;
            
            // Créer le chemin
            const path = 'M ' + centerX + ' ' + centerY + ' L ' + x1 + ' ' + y1 + ' A ' + radius + ' ' + radius + ' 0 ' + largeArc + ' 1 ' + x2 + ' ' + y2 + ' Z';
            
            // Ajouter le segment avec tooltip
            svg += '<path d="' + path + '" fill="' + color + '" stroke="#fff" stroke-width="3" class="pie-segment" data-label="' + item.label + '" data-value="' + item.missions + '" data-percentage="' + percentage.toFixed(1) + '" style="cursor: pointer; transition: opacity 0.3s;" onmouseover="this.style.opacity=\'0.8\'; showTooltip(event, \'' + item.label.replace(/'/g, "\\'") + '\', ' + item.missions + ', ' + percentage.toFixed(1) + ')" onmouseout="this.style.opacity=\'1\'; hideTooltip()"></path>';
            
            // Ajouter le texte du pourcentage si > 5%
            if (percentage > 5) {
                // Calculer la position du texte (au milieu de l'arc)
                const midAngle = (startAngle + endAngle) / 2;
                const textRadius = radius * 0.7; // 70% du rayon
                const textX = centerX + textRadius * Math.cos(midAngle);
                const textY = centerY + textRadius * Math.sin(midAngle);
                
                // Ajouter le texte avec fond blanc semi-transparent
                svg += '<text x="' + textX + '" y="' + textY + '" text-anchor="middle" dominant-baseline="middle" style="font-weight: bold; font-size: 14px; fill: #fff; pointer-events: none; text-shadow: 0 0 3px rgba(0,0,0,0.5);">' + percentage.toFixed(1) + '%</text>';
            }
            
            currentAngle += angle;
        });
        
        svg += '</svg>';
        
        svgContainer.innerHTML = svg;
        console.log('✓ Graphique SVG créé!');
        
        // Créer la légende
        const legendContainer = document.getElementById('chartLegend');
        if (!legendContainer) {
            console.error('Container chartLegend introuvable');
            return;
        }
        
        legendContainer.innerHTML = '';
        
        secteurData.forEach((item, index) => {
            const percentage = ((item.missions / total) * 100).toFixed(1);
            const legendItem = document.createElement('div');
            legendItem.style.cssText = 'display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid ' + colors[index % colors.length] + '; transition: transform 0.2s; cursor: pointer;';
            
            legendItem.onmouseover = function() { this.style.transform = 'translateX(5px)'; };
            legendItem.onmouseout = function() { this.style.transform = 'translateX(0)'; };
            
            legendItem.innerHTML = 
                '<div style="width: 24px; height: 24px; background: ' + colors[index % colors.length] + '; border-radius: 6px; flex-shrink: 0;"></div>' +
                '<div style="flex: 1;">' +
                    '<div style="font-weight: 600; color: #333; font-size: 0.95rem;">' + item.label + '</div>' +
                    '<div style="color: #666; font-size: 0.85rem;">' + item.missions + ' missions (' + percentage + '%)</div>' +
                '</div>';
            
            legendContainer.appendChild(legendItem);
        });
        
        console.log('✓ Légende créée!');
    }
    
    // Tooltip
    let tooltip = null;
    
    function showTooltip(event, label, value, percentage) {
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.style.cssText = 'position: fixed; background: rgba(0,0,0,0.9); color: white; padding: 12px 16px; border-radius: 8px; font-size: 14px; pointer-events: none; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
            document.body.appendChild(tooltip);
        }
        
        tooltip.innerHTML = '<strong>' + label + '</strong><br>' + value + ' missions (' + percentage + '%)';
        tooltip.style.display = 'block';
        tooltip.style.left = (event.clientX + 15) + 'px';
        tooltip.style.top = (event.clientY + 15) + 'px';
    }
    
    function hideTooltip() {
        if (tooltip) {
            tooltip.style.display = 'none';
        }
    }
    
    // Initialiser au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createPieChart);
    } else {
        createPieChart();
    }

    function exporterExcel() {
        const annee = <?php echo $annee; ?>;
        const secteur = '<?php echo addslashes($secteurFiltre); ?>';
        // Exporter les statistiques en CSV
        window.location.href = 'export_stats_secteurs_csv.php?annee=' + annee + '&secteur=' + encodeURIComponent(secteur);
    }
    </script>
</body>
</html>
