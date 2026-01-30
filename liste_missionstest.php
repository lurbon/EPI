<?php
require_once('wp-config.php');
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
            padding: 40px;
            max-width: 1600px;
            margin: 100px auto 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 30px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .search-filters input,
        .search-filters select,
        .search-filters button {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-filters input:focus,
        .search-filters select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-filters button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .search-filters button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .search-filters input[type="search"] {
            flex: 1;
            min-width: 250px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .stat-card h3 {
            font-size: 1em;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .stat-value {
            font-size: 2.5em;
            font-weight: bold;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .tab {
            padding: 12px 25px;
            background: #f5f5f5;
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #666;
        }

        .tab:hover {
            background: #e0e0e0;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 -4px 15px rgba(102, 126, 234, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
        }

        .month-stats {
            display: flex;
            gap: 30px;
        }

        .month-stat {
            text-align: center;
        }

        .month-stat-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .month-stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
        }

        .missions-table {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th:first-child {
            border-radius: 15px 0 0 0;
        }

        th:last-child {
            border-radius: 0 15px 0 0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
            cursor: pointer;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-secteur {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-nature {
            background: #fff3e0;
            color: #f57c00;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8em;
        }

        .close {
            color: white;
            float: right;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: all 0.3s ease;
        }

        .close:hover {
            transform: scale(1.2);
        }

        #modalBody {
            padding: 30px;
        }

        .detail-section {
            margin-bottom: 30px;
        }

        .detail-section h4 {
            color: #667eea;
            font-size: 1.3em;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .detail-item strong {
            display: block;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .detail-item span {
            display: block;
            color: #333;
            font-size: 1.1em;
        }

        .no-missions {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-missions svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 80px 10px 20px;
            }

            .search-filters {
                flex-direction: column;
            }

            .search-filters input,
            .search-filters select {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            table {
                font-size: 0.9em;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">←</a>

    <div class="container">
        <h1>📋 Liste des Missions</h1>

        <form method="GET" class="search-filters">
            <input type="search" name="search" placeholder="🔍 Rechercher par bénévole, aidé ou destination..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="secteur">
                <option value="">📍 Tous les secteurs</option>
                <?php foreach($secteurs as $secteur): ?>
                    <option value="<?php echo htmlspecialchars($secteur); ?>" <?php echo $secteur_filter === $secteur ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($secteur); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrer</button>
            <?php if($search || $secteur_filter): ?>
                <a href="liste_missions.php" style="padding: 12px 20px; text-decoration: none; color: #dc3545; border: 2px solid #dc3545; border-radius: 10px; font-weight: 600;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total des missions</h3>
                <div class="stat-value"><?php echo $total_missions; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total des kilomètres</h3>
                <div class="stat-value"><?php echo number_format($total_km, 0, ',', ' '); ?> km</div>
            </div>
            <div class="stat-card">
                <h3>Moyenne km/mission</h3>
                <div class="stat-value"><?php echo $total_missions > 0 ? number_format($total_km / $total_missions, 1, ',', ' ') : '0'; ?> km</div>
            </div>
        </div>

        <?php if(empty($missions)): ?>
            <div class="no-missions">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 2a1 1 0 0 0-.894.553L7.382 4H4a1 1 0 0 0-1 1v3a1 1 0 0 0 .553.894L5 9.618V20a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9.618l1.447-.724A1 1 0 0 0 21 8V5a1 1 0 0 0-1-1h-3.382l-.724-1.447A1 1 0 0 0 15 2H9zm0 2h6l.618 1.236A1 1 0 0 0 16.382 6H20v1.382l-1.447.724a1 1 0 0 0-.553.894V20H6V8.999a1 1 0 0 0-.553-.894L4 7.382V6h3.618a1 1 0 0 0 .765-.447L9 4z"/>
                </svg>
                <h3>Aucune mission trouvée</h3>
                <p>Essayez de modifier vos critères de recherche</p>
            </div>
        <?php else: ?>
            <div class="tabs">
                <?php 
                $isFirstTab = true;
                foreach($missionsByMonth as $monthKey => $monthData): 
                ?>
                    <button class="tab <?php echo $isFirstTab ? 'active' : ''; ?>" onclick="switchTab('<?php echo $monthKey; ?>')">
                        <?php echo $monthData['label']; ?> (<?php echo $monthData['count']; ?>)
                    </button>
                <?php 
                    $isFirstTab = false;
                endforeach; 
                ?>
            </div>

            <div class="tab-contents">
                <?php 
                $isFirst = true;
                foreach($missionsByMonth as $monthKey => $monthData): 
                ?>
                    <div id="tab-<?php echo $monthKey; ?>" class="tab-content <?php echo $isFirst ? 'active' : ''; ?>">
                        <div class="month-header">
                            <h2><?php echo $monthData['label']; ?></h2>
                            <div class="month-stats">
                                <div class="month-stat">
                                    <div class="month-stat-label">Missions</div>
                                    <div class="month-stat-value"><?php echo $monthData['count']; ?></div>
                                </div>
                                <div class="month-stat">
                                    <div class="month-stat-label">Kilomètres</div>
                                    <div class="month-stat-value"><?php echo number_format($monthData['km_total'], 0, ',', ' '); ?> km</div>
                                </div>
                            </div>
                        </div>

                        <div class="missions-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure RDV</th>
                                        <th>Bénévole</th>
                                        <th>Aidé</th>
                                        <th>Secteur</th>
                                        <th>Adresse aidé</th>
                                        <th>Destination</th>
                                        <th>Nature</th>
                                        <th>Commentaires</th>
                                        <th>Km saisis</th>
                                        <th>Km calculés</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($monthData['missions'] as $mission): ?>
                                        <tr onclick='showDetails(<?php echo json_encode($mission, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <td><?php echo date('d/m/Y', strtotime($mission['date_mission'])); ?></td>
                                            <td><?php echo $mission['heure_rdv'] ? substr($mission['heure_rdv'], 0, 5) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($mission['benevole'] ?: ''); ?></td>
                                            <td><?php echo htmlspecialchars($mission['aide']); ?></td>
                                            <td>
                                                <?php if($mission['secteur_aide']): ?>
                                                    <span class="badge badge-secteur"><?php echo htmlspecialchars($mission['secteur_aide']); ?></span>
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
                                                    <strong style="color: #667eea;"><?php echo htmlspecialchars($mission['km_saisi']); ?> km</strong>
                                                <?php else: ?>
                                                    <span style="color: #999;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($mission['km_calcule'] !== null && $mission['km_calcule'] !== ''): ?>
                                                    <span style="color: #28a745;"><?php echo htmlspecialchars($mission['km_calcule']); ?> km</span>
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
    </script>
</body>
</html>
