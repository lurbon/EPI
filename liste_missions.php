<?php
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'benevole','chauffeur','gestionnaire']);

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonction pour obtenir le nom du mois en français
function getMoisFrancais($date) {
    $mois = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    $mois_num = (int)date('n', strtotime($date));
    $annee = date('Y', strtotime($date));
    return $mois[$mois_num] . ' ' . $annee;
}

// Récupérer les missions
$missions = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$benevole_filter = isset($_GET['benevole']) ? $_GET['benevole'] : '';
$secteur_filter = isset($_GET['secteur']) ? $_GET['secteur'] : '';

try {
    $sql = "SELECT m.*, a.tel_fixe, a.tel_portable ,a.commentaires as comment
            FROM EPI_mission m 
            LEFT JOIN EPI_aide a ON m.id_aide = a.id_aide
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (m.benevole LIKE :search OR m.aide LIKE :search OR m.adresse_destination LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($benevole_filter) {
        $sql .= " AND m.benevole LIKE :benevole";
        $params[':benevole'] = "%$benevole_filter%";
    }
    
    if ($secteur_filter) {
        $sql .= " AND m.secteur_aide LIKE :secteur";
        $params[':secteur'] = "%$secteur_filter%";
    }
    
    $sql .= " ORDER BY m.date_mission ASC, m.heure_rdv ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Récupérer les secteurs uniques pour le filtre
$secteurs = [];
try {
    $stmt = $conn->query("SELECT DISTINCT secteur_aide FROM EPI_mission WHERE secteur_aide IS NOT NULL AND secteur_aide != '' ORDER BY secteur_aide");
    $secteurs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    // Ignorer l'erreur
}

// Fonction pour générer une couleur unique pour chaque secteur
function getSecteurColor($secteur) {
    // Palette de couleurs pastel distinctives
    $colors = [
        ['bg' => '#FFE5E5', 'text' => '#C41E3A'],  // Rouge pastel
        ['bg' => '#E5F3FF', 'text' => '#0066CC'],  // Bleu pastel
        ['bg' => '#E8F5E9', 'text' => '#2E7D32'],  // Vert pastel
        ['bg' => '#FFF3E0', 'text' => '#E65100'],  // Orange pastel
        ['bg' => '#F3E5F5', 'text' => '#7B1FA2'],  // Violet pastel
        ['bg' => '#FFF9C4', 'text' => '#F57F17'],  // Jaune pastel
        ['bg' => '#E0F2F1', 'text' => '#00695C'],  // Turquoise pastel
        ['bg' => '#FCE4EC', 'text' => '#C2185B'],  // Rose pastel
        ['bg' => '#E1F5FE', 'text' => '#0277BD'],  // Cyan pastel
        ['bg' => '#F1F8E9', 'text' => '#558B2F'],  // Vert lime pastel
        ['bg' => '#FBE9E7', 'text' => '#D84315'],  // Orange brûlé pastel
        ['bg' => '#EDE7F6', 'text' => '#5E35B1'],  // Indigo pastel
    ];
    
    // Générer un index basé sur le hash du nom du secteur
    $hash = crc32($secteur);
    $index = abs($hash) % count($colors);
    
    return $colors[$index];
}

// Grouper les missions par mois
$missionsByMonth = [];
$currentMonthKey = date('Y-m'); // Mois courant au format Y-m
$currentDate = date('Y-m-d'); // Date du jour au format Y-m-d

foreach($missions as $mission) {
    $monthKey = date('Y-m', strtotime($mission['date_mission']));
    $monthLabel = getMoisFrancais($mission['date_mission']);
    
    if (!isset($missionsByMonth[$monthKey])) {
        $missionsByMonth[$monthKey] = [
            'label' => $monthLabel,
            'missions' => [],
            'km_total' => 0,
            'count' => 0
        ];
    }
    
    $missionsByMonth[$monthKey]['missions'][] = $mission;
    $km = $mission['km_saisi'] ?: $mission['km_calcule'] ?: 0;
    $missionsByMonth[$monthKey]['km_total'] += $km;
    $missionsByMonth[$monthKey]['count']++;
}

// Statistiques globales
$total_km = 0;
$total_missions = count($missions);
foreach($missions as $m) {
    $km = $m['km_saisi'] ?: $m['km_calcule'] ?: 0;
    $total_km += $km;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Missions</title>
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 30px;
            max-width: 1800px;
            margin: 0 auto;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
            font-size: 28px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 250px;
        }

        .filters select {
            min-width: 150px;
        }

        .filters input:focus,
        .filters select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filters button {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .filters button:hover {
            transform: translateY(-2px);
        }

        .stats {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-item .label {
            font-size: 12px;
            color: #666;
        }

        /* TABS */
        .tabs-container {
            margin-bottom: 20px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            padding-top: 10px;
            margin-left: -30px;
            margin-right: -30px;
            padding-left: 30px;
            padding-right: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            bottom: -2px;
        }

        .tab:hover {
            background: #e9ecef;
        }

        .tab.active {
            background: white;
            color: #667eea;
            border-color: #667eea;
            border-bottom: 2px solid white;
        }

        .tab.current-month {
            background-color: #e8f5e9;
        }

        .tab.current-month.active {
            background: #e8f5e9;
            color: #28a745;
            border-color: #28a745;
            border-bottom: 2px solid #e8f5e9;
        }

        .tab-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 8px;
        }

        .tab.active .tab-badge {
            background: #764ba2;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .month-stats {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .month-stat-item {
            text-align: center;
        }

        .month-stat-item .number {
            font-size: 18px;
            font-weight: bold;
            color: #1976d2;
        }

        .month-stat-item .label {
            font-size: 11px;
            color: #555;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        tbody tr.today {
            background-color: #e8f5e9;
        }

        tbody tr.today:hover {
            background-color: #c8e6c9;
        }

        .link-modifier {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .link-modifier:hover {
            text-decoration: underline;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-nature {
            background: #fff3cd;
            color: #856404;
        }

        .badge-secteur {
            /* Les couleurs sont définies dynamiquement via inline style */
        }

        .warning-km {
            display: inline-flex;
            align-items: center;
            margin-left: 6px;
            font-size: 14px;
            cursor: default;
            position: relative;
        }

        .warning-km:hover .warning-km-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .warning-km-tooltip {
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s ease;
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #343a40;
            color: #fff;
            font-size: 11px;
            padding: 5px 9px;
            border-radius: 5px;
            white-space: nowrap;
        }

        .warning-km-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #343a40;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeInModal 0.3s ease;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover {
            color: #667eea;
        }

        .modal-header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 20px;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-item {
            font-size: 12px;
        }

        .detail-item strong {
            display: block;
            color: #666;
            font-size: 11px;
            margin-bottom: 3px;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filters input[type="text"],
            .filters select {
                width: 100%;
            }

            .tabs {
                gap: 5px;
            }

            .tab {
                padding: 8px 14px;
                font-size: 11px;
            }

            th, td {
                font-size: 10px;
                padding: 6px 4px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            /* Adapter le bouton flottant sur mobile */
            .back-link {
                top: 20px;
                left: 20px;
                width: 55px;
                height: 55px;
                font-size: 22px;
            }

            .back-link::before {
                left: 65px;
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link" title="Retour au tableau de bord">🏠</a>

    <div class="container">
        <h1>🚗 Liste des Missions</h1>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher bénévole, aidé, destination..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="secteur">
                <option value="">Tous les secteurs</option>
                <?php foreach($secteurs as $secteur): ?>
                    <option value="<?php echo htmlspecialchars($secteur); ?>" <?php echo $secteur_filter === $secteur ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($secteur); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit">Filtrer</button>
            <?php if($search || $secteur_filter): ?>
                <a href="liste_missions.php" style="padding: 8px 12px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 12px;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo $total_missions; ?></div>
                <div class="label">Mission<?php echo $total_missions > 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #28a745;"><?php echo number_format($total_km, 0, ',', ' '); ?> km</div>
                <div class="label">Kilomètres totaux</div>
            </div>
        </div>

        <?php if(empty($missions)): ?>
            <div class="no-results">
                😕 Aucune mission trouvée avec ces critères.
            </div>
        <?php else: ?>
            <div class="tabs-container">
                <!-- Onglets des mois -->
                <div class="tabs">
                    <?php 
                    $isFirst = true;
                    foreach($missionsByMonth as $monthKey => $monthData): 
                        $isCurrentMonth = ($monthKey === $currentMonthKey);
                    ?>
                        <div class="tab <?php echo $isFirst ? 'active' : ''; ?> <?php echo $isCurrentMonth ? 'current-month' : ''; ?>" onclick="switchTab('<?php echo $monthKey; ?>')">
                            <?php echo $monthData['label']; ?>
                            <span class="tab-badge"><?php echo $monthData['count']; ?></span>
                        </div>
                    <?php 
                        $isFirst = false;
                    endforeach; 
                    ?>
                </div>

                <!-- Contenu des onglets -->
                <?php 
                $isFirst = true;
                foreach($missionsByMonth as $monthKey => $monthData): 
                ?>
                    <div id="tab-<?php echo $monthKey; ?>" class="tab-content <?php echo $isFirst ? 'active' : ''; ?>">
                        <!-- Statistiques du mois -->
                        <div class="month-stats">
                            <div class="month-stat-item">
                                <div class="number"><?php echo $monthData['count']; ?></div>
                                <div class="label">Mission<?php echo $monthData['count'] > 1 ? 's' : ''; ?></div>
                            </div>
                            <div class="month-stat-item">
                                <div class="number"><?php echo number_format($monthData['km_total'], 0, ',', ' '); ?> km</div>
                                <div class="label">Total kilomètres</div>
                            </div>
                        </div>

                        <!-- Tableau des missions -->
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure RDV</th>
                                        <th>Bénévole</th>
                                        <th>Aidé</th>
                                        <th>Secteur Aidé</th>
                                        <th>Adresse Aidé</th>
                                        <th>Destination</th>
                                        <th>Nature</th>
                                        <th>Commentaires</th>
                                        <th>KM Saisis</th>
                                        <th>KM Calculés</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($monthData['missions'] as $mission): 
                                        $isToday = ($mission['date_mission'] === $currentDate);
                                    ?>
                                        <tr class="<?php echo $isToday ? 'today' : ''; ?>" onclick='showDetails(<?php echo json_encode($mission, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <td><strong><?php echo date('d/m/Y', strtotime($mission['date_mission'])); ?></strong></td>
                                            <td><?php echo $mission['heure_rdv'] ? substr($mission['heure_rdv'], 0, 5) : '-'; ?></td>
                                            <td><a href="modifier_mission.php?id=<?php echo intval($mission['id_mission']); ?>" class="link-modifier" onclick="event.stopPropagation()"><?php echo htmlspecialchars($mission['benevole'] ?: ''); ?></a></td>
                                            <td><?php echo htmlspecialchars($mission['aide']); ?></td>
                                            <td>
                                                <?php if($mission['secteur_aide']): 
                                                    $secteurColor = getSecteurColor($mission['secteur_aide']);
                                                ?>
                                                    <span class="badge badge-secteur" style="background-color: <?php echo $secteurColor['bg']; ?>; color: <?php echo $secteurColor['text']; ?>;"><?php echo htmlspecialchars($mission['secteur_aide']); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $adresse_complete = [];
                                                if($mission['adresse_aide']) $adresse_complete[] = htmlspecialchars($mission['adresse_aide']);
                                                if($mission['cp_aide'] && $mission['commune_aide']) {
                                                    $adresse_complete[] = htmlspecialchars($mission['cp_aide']) . ' ' . htmlspecialchars($mission['commune_aide']);
                                                }
                                                echo !empty($adresse_complete) ? implode(', ', $adresse_complete) : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $destination_complete = [];
                                                if($mission['adresse_destination']) $destination_complete[] = htmlspecialchars($mission['adresse_destination']);
                                                if($mission['cp_destination'] && $mission['commune_destination']) {
                                                    $destination_complete[] = htmlspecialchars($mission['cp_destination']) . ' ' . htmlspecialchars($mission['commune_destination']);
                                                }
                                                echo !empty($destination_complete) ? implode(', ', $destination_complete) : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if($mission['nature_intervention']): ?>
                                                    <span class="badge badge-nature"><?php echo htmlspecialchars($mission['nature_intervention']); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $mission['commentaires'] ? htmlspecialchars($mission['commentaires']) : '-'; ?></td>
                                            <td>
                                                <?php if($mission['km_saisi'] !== null && $mission['km_saisi'] !== ''): ?>
                                                    <strong style="color: #667eea;"><?php echo intval($mission['km_saisi']); ?> km</strong>
                                                <?php else: ?>
                                                    <span style="color: #999;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($mission['km_calcule'] !== null && $mission['km_calcule'] !== ''): ?>
                                                    <span style="color: #28a745;"><?php echo intval($mission['km_calcule']); ?> km</span>
                                                    <?php if($mission['km_saisi'] === null): ?>
                                                    <span class="warning-km">
                                                        ⚠️
                                                        <span class="warning-km-tooltip">KM calculé mais pas encore saisi</span>
                                                    </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #999;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php 
                    $isFirst = false;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitre"></h2>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        // Fonction pour formater les numéros de téléphone
        function formatTelephone(tel) {
            if (!tel) return '-';
            // Supprimer tous les espaces, points, tirets existants
            tel = tel.replace(/[\s.-]/g, '');
            // Formater par paires de chiffres
            if (tel.length === 10) {
                return tel.match(/.{1,2}/g).join(' ');
            }
            return tel; // Retourner tel quel si pas 10 chiffres
        }

        // Fonction pour formater la date en français
        function formatDateFrancais(dateStr) {
            const jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
            const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
                         'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            
            const date = new Date(dateStr + 'T00:00');
            const jour = jours[date.getDay()];
            const numJour = date.getDate();
            const nomMois = mois[date.getMonth()];
            const annee = date.getFullYear();
            
            return jour.charAt(0).toUpperCase() + jour.slice(1) + ' ' + numJour + ' ' + nomMois + ' ' + annee;
        }

        function switchTab(monthKey) {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Cacher tout le contenu
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            event.target.classList.add('active');
            document.getElementById('tab-' + monthKey).classList.add('active');
            
            // Faire défiler vers le haut de la page
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function showDetails(mission) {
            const modal = document.getElementById('detailModal');
            const modalTitre = document.getElementById('modalTitre');
            const modalBody = document.getElementById('modalBody');
            
            const dateFormattee = formatDateFrancais(mission.date_mission);
            
            modalTitre.textContent = '🚗 Mission du ' + dateFormattee +' à '+ (mission.heure_rdv ? mission.heure_rdv.substring(0, 5) : 'Non renseignée');
            
            let html = '';
            
            // Aidé
            html += '<div class="detail-section"><h4>🤝 Aidé</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Nom</strong><span>' + (mission.aide || '-') + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone fixe</strong><span>' + formatTelephone(mission.tel_fixe) + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone portable</strong><span>' + formatTelephone(mission.tel_portable) + '</span></div>';
            html += '<div class="detail-item"><strong>Adresse</strong><span>' + (mission.adresse_aide || '-') + '</span></div>';
            html += '<div class="detail-item"><strong>Ville</strong><span>' + (mission.commune_aide ? mission.cp_aide + ' ' + mission.commune_aide : '-') + '</span></div>';
	        html += '<div class="detail-item"><strong>Commentaires</strong><span>' + (mission.comment || '-') + '</span></div>';		
            html += '</div></div>';      
            // Détails mission
            html += '<div class="detail-section"><h4>📋 Détails de la mission</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Adresse destination</strong><span>' + (mission.adresse_destination || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Ville destination</strong><span>' + (mission.commune_destination ? mission.cp_destination + ' ' + mission.commune_destination : 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Nature intervention</strong><span>' + (mission.nature_intervention || 'Non renseignée') + '</span></div>';
            html += '</div>';
            if (mission.commentaires) {
                html += '<div class="detail-item" style="margin-top: 15px;"><strong>Commentaires</strong><span>' + mission.commentaires + '</span></div>';
            }
            html += '</div>';
			
            // Bénévole
            html += '<div class="detail-section"><h4>👤 Bénévole</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Nom</strong><span>' + (mission.benevole || '') + '</span></div>';
            html += '</div></div>';
            
            modalBody.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Activer automatiquement l'onglet du mois en cours au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const currentMonthKey = '<?php echo $currentMonthKey; ?>';
            const currentTab = document.querySelector('.tab[onclick*="' + currentMonthKey + '"]');
            const currentContent = document.getElementById('tab-' + currentMonthKey);
            
            if (currentTab && currentContent) {
                // Désactiver tous les onglets
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Cacher tout le contenu
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Activer l'onglet et le contenu du mois en cours
                currentTab.classList.add('active');
                currentContent.classList.add('active');
            }
        });
    </script>
</body>
</html>