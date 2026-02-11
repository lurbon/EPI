<?php
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'benevole', 'chauffeur', 'gestionnaire']);

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

// Trouver les id_benevole correspondant à l'email de l'utilisateur connecté
// (un couple peut partager le même email → plusieurs id_benevole)
$userEmail = $_SESSION['user']['email'] ?? '';
$idsBenevoleConnecte = [];
$nomsBenevole = [];

if (!empty($userEmail)) {
    $stmtBen = $conn->prepare("SELECT id_benevole, nom FROM EPI_benevole WHERE courriel = :email");
    $stmtBen->execute([':email' => $userEmail]);
    $benevoleRows = $stmtBen->fetchAll(PDO::FETCH_ASSOC);
    foreach ($benevoleRows as $row) {
        $idsBenevoleConnecte[] = $row['id_benevole'];
        $nomsBenevole[] = $row['nom'];
    }
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

// Récupérer les missions du bénévole connecté
$missions = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    if (!empty($idsBenevoleConnecte)) {
        // Construire les placeholders pour IN (...)
        $placeholders = [];
        $params = [];
        foreach ($idsBenevoleConnecte as $i => $id) {
            $placeholders[] = ":id_ben_$i";
            $params[":id_ben_$i"] = $id;
        }
        $inClause = implode(', ', $placeholders);

        $sql = "SELECT m.*, a.tel_fixe, a.tel_portable, a.commentaires as comment, b.nom as nom_benevole
                FROM EPI_mission m
                LEFT JOIN EPI_aide a ON m.id_aide = a.id_aide
                LEFT JOIN EPI_benevole b ON m.id_benevole = b.id_benevole
                WHERE m.id_benevole IN ($inClause)";

        if ($search) {
            $sql .= " AND (m.date_mission LIKE :search OR m.aide LIKE :search OR m.adresse_destination LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $sql .= " ORDER BY m.date_mission ASC, m.heure_rdv ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Grouper les missions par mois
$missionsByMonth = [];
foreach($missions as $mission) {
    $monthKey = date('Y-m', strtotime($mission['date_mission']));
    $monthLabel = getMoisFrancais($mission['date_mission']);

    if (!isset($missionsByMonth[$monthKey])) {
        $missionsByMonth[$monthKey] = [
            'label' => $monthLabel,
            'missions' => [],
            'km_total' => 0,
            'duree_total_minutes' => 0,
            'count' => 0
        ];
    }

    $missionsByMonth[$monthKey]['missions'][] = $mission;
    $km = $mission['km_saisi'] ?: $mission['km_calcule'] ?: 0;
    $missionsByMonth[$monthKey]['km_total'] += $km;

    // Cumuler la durée
    if (!empty($mission['duree'])) {
        list($h, $m, $s) = explode(':', $mission['duree']);
        $missionsByMonth[$monthKey]['duree_total_minutes'] += ($h * 60) + $m;
    }

    $missionsByMonth[$monthKey]['count']++;
}

// Trier les mois par ordre croissant de date
ksort($missionsByMonth);

// Statistiques globales
$total_km = 0;
$total_duree_minutes = 0;
$total_missions = count($missions);
foreach($missions as $m) {
    $km = $m['km_saisi'] ?: $m['km_calcule'] ?: 0;
    $total_km += $km;
    if (!empty($m['duree'])) {
        list($h, $mi, $s) = explode(':', $m['duree']);
        $total_duree_minutes += ($h * 60) + $mi;
    }
}
$total_heures = floor($total_duree_minutes / 60);
$total_minutes = $total_duree_minutes % 60;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos Missions</title>
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

        .filters input {
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

        .filters input:focus {
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
            background: #e3f2fd;
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

        tbody tr.mission-aujourd-hui {
            background-color: #ffe4f0 !important;
            border-left: 4px solid #ff69b4;
        }

        tbody tr.mission-aujourd-hui:hover {
            background-color: #ffd1e8 !important;
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
            background: #d1ecf1;
            color: #0c5460;
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

            .filters input[type="text"] {
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
    <a href="dashboard.php" class="back-link" title="Retour au tableau de bord">&#x1F3E0;</a>

    <div class="container">
        <h1>&#x1F4CB; Vos Missions<?php if (!empty($nomsBenevole)): ?> - <?php echo htmlspecialchars(implode(' & ', $nomsBenevole)); ?><?php endif; ?></h1>

        <?php if (empty($idsBenevoleConnecte)): ?>
            <div class="no-results">
                &#x26A0;&#xFE0F; Votre compte n'est pas rattach&eacute; &agrave; un b&eacute;n&eacute;vole. Veuillez contacter l'administrateur.
            </div>
        <?php else: ?>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="&#x1F50D; Rechercher par date, aid&eacute;, destination..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Filtrer</button>
            <?php if($search): ?>
                <a href="vos_missions.php" style="padding: 8px 12px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 12px;">R&eacute;initialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo $total_missions; ?></div>
                <div class="label">Mission<?php echo $total_missions > 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #28a745;"><?php echo number_format($total_km, 0, ',', ' '); ?> km</div>
                <div class="label">Kilom&egrave;tres totaux</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #ff9800;"><?php echo $total_heures; ?>h<?php echo str_pad($total_minutes, 2, '0', STR_PAD_LEFT); ?></div>
                <div class="label">Dur&eacute;e totale</div>
            </div>
        </div>

        <?php if(empty($missions)): ?>
            <div class="no-results">
                Aucune mission trouv&eacute;e.
            </div>
        <?php else: ?>
            <div class="tabs-container">
                <div class="tabs">
                    <?php
                    $currentMonthKey = date('Y-m'); // Mois en cours au format YYYY-MM
                    foreach($missionsByMonth as $monthKey => $monthData):
                        $isCurrentMonth = ($monthKey === $currentMonthKey);
                    ?>
                        <div class="tab <?php echo $isCurrentMonth ? 'active' : ''; ?>" onclick="switchTab('<?php echo $monthKey; ?>')">
                            <?php echo $monthData['label']; ?>
                            <span class="tab-badge"><?php echo $monthData['count']; ?></span>
                        </div>
                    <?php
                    endforeach;
                    ?>
                </div>

                <?php
                foreach($missionsByMonth as $monthKey => $monthData):
                    $isCurrentMonth = ($monthKey === $currentMonthKey);
                    $mHeures = floor($monthData['duree_total_minutes'] / 60);
                    $mMinutes = $monthData['duree_total_minutes'] % 60;
                ?>
                    <div id="tab-<?php echo $monthKey; ?>" class="tab-content <?php echo $isCurrentMonth ? 'active' : ''; ?>">
                        <div class="month-stats">
                            <div class="month-stat-item">
                                <div class="number"><?php echo $monthData['count']; ?></div>
                                <div class="label">Mission<?php echo $monthData['count'] > 1 ? 's' : ''; ?></div>
                            </div>
                            <div class="month-stat-item">
                                <div class="number"><?php echo number_format($monthData['km_total'], 0, ',', ' '); ?> km</div>
                                <div class="label">Total kilom&egrave;tres</div>
                            </div>
                            <div class="month-stat-item">
                                <div class="number"><?php echo $mHeures; ?>h<?php echo str_pad($mMinutes, 2, '0', STR_PAD_LEFT); ?></div>
                                <div class="label">Dur&eacute;e totale</div>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure RDV</th>
                                        <th>Aid&eacute;</th>
                                        <th>Adresse Aid&eacute;</th>
                                        <th>Destination</th>
                                        <th>Nature</th>
                                        <th>KM saisi</th>
                                        <th>KM calculé</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $dateAujourdhui = date('Y-m-d');
                                    foreach($monthData['missions'] as $mission): 
                                        $estAujourdhui = ($mission['date_mission'] === $dateAujourdhui);
                                        $classeAujourdhui = $estAujourdhui ? ' mission-aujourd-hui' : '';
                                    ?>
                                        <tr class="<?php echo $classeAujourdhui; ?>" onclick='showDetails(<?php echo json_encode($mission, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <td><strong><?php echo date('d/m/Y', strtotime($mission['date_mission'])); ?></strong></td>
                                            <td><?php echo $mission['heure_rdv'] ? substr($mission['heure_rdv'], 0, 5) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($mission['aide']); ?></td>
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
                                            <td>
                                                <?php
                                                if($mission['km_saisi'] !== null && $mission['km_saisi'] !== ''): ?>
												<strong style="color: #667eea;"><?php echo intval($mission['km_saisi']); ?> km</strong>												                                                
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($mission['km_calcule'] !== null && $mission['km_calcule'] !== ''): ?>
												<strong style="color: #28a745;"><?php echo intval($mission['km_calcule']); ?> km</strong>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php
                endforeach;
                ?>
            </div>
        <?php endif; ?>
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
        function formatTelephone(tel) {
            if (!tel) return '-';
            tel = tel.replace(/[\s.-]/g, '');
            if (tel.length === 10) {
                return tel.match(/.{1,2}/g).join(' ');
            }
            return tel;
        }

        function formatDateFrancais(dateStr) {
            const jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
            const mois = ['janvier', 'f\u00e9vrier', 'mars', 'avril', 'mai', 'juin',
                         'juillet', 'ao\u00fbt', 'septembre', 'octobre', 'novembre', 'd\u00e9cembre'];

            const date = new Date(dateStr + 'T00:00');
            const jour = jours[date.getDay()];
            const numJour = date.getDate();
            const nomMois = mois[date.getMonth()];
            const annee = date.getFullYear();

            return jour.charAt(0).toUpperCase() + jour.slice(1) + ' ' + numJour + ' ' + nomMois + ' ' + annee;
        }

        function switchTab(monthKey) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            event.target.classList.add('active');
            document.getElementById('tab-' + monthKey).classList.add('active');
        }

        function showDetails(mission) {
            const modal = document.getElementById('detailModal');
            const modalTitre = document.getElementById('modalTitre');
            const modalBody = document.getElementById('modalBody');

            const dateFormattee = formatDateFrancais(mission.date_mission);

            modalTitre.textContent = '\uD83D\uDE97 Mission du ' + dateFormattee + ' \u00e0 ' + (mission.heure_rdv ? mission.heure_rdv.substring(0, 5) : 'Non renseign\u00e9e');

            let html = '';

            // Bénévole
            html += '<div class="detail-section"><h4>👤 Bénévole</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Nom</strong><span>' + (mission.nom_benevole || '-') + '</span></div>';
            html += '</div></div>';

            // Aid\u00e9
            html += '<div class="detail-section"><h4>\uD83E\uDD1D Aid\u00e9</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Nom</strong><span>' + (mission.aide || '-') + '</span></div>';
            html += '<div class="detail-item"><strong>T\u00e9l\u00e9phone fixe</strong><span>' + formatTelephone(mission.tel_fixe) + '</span></div>';
            html += '<div class="detail-item"><strong>T\u00e9l\u00e9phone portable</strong><span>' + formatTelephone(mission.tel_portable) + '</span></div>';
            html += '<div class="detail-item"><strong>Adresse</strong><span>' + (mission.adresse_aide || '-') + '</span></div>';
            html += '<div class="detail-item"><strong>Ville</strong><span>' + (mission.commune_aide ? mission.cp_aide + ' ' + mission.commune_aide : '-') + '</span></div>';
            html += '<div class="detail-item"><strong>Commentaires</strong><span>' + (mission.comment || '-') + '</span></div>';
            html += '</div></div>';

            // D\u00e9tails mission
            html += '<div class="detail-section"><h4>\uD83D\uDCCB D\u00e9tails de la mission</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Adresse destination</strong><span>' + (mission.adresse_destination || 'Non renseign\u00e9e') + '</span></div>';
            html += '<div class="detail-item"><strong>Ville destination</strong><span>' + (mission.commune_destination ? mission.cp_destination + ' ' + mission.commune_destination : 'Non renseign\u00e9e') + '</span></div>';
            html += '<div class="detail-item"><strong>Nature intervention</strong><span>' + (mission.nature_intervention || 'Non renseign\u00e9e') + '</span></div>';

            // KM et dur\u00e9e
            const km = mission.km_saisi || mission.km_calcule || '-';
            html += '<div class="detail-item"><strong>Kilom\u00e8tres</strong><span>' + (km !== '-' ? parseInt(km) + ' km' : '-') + '</span></div>';

            if (mission.duree) {
                const parts = mission.duree.split(':');
                const dureeStr = parseInt(parts[0]) > 0 ? parts[0] + 'h' + parts[1] : parseInt(parts[1]) + 'min';
                html += '<div class="detail-item"><strong>Dur\u00e9e</strong><span>' + dureeStr + '</span></div>';
            }

            html += '</div>';
            if (mission.commentaires) {
                html += '<div class="detail-item" style="margin-top: 15px;"><strong>Commentaires</strong><span>' + mission.commentaires + '</span></div>';
            }
            html += '</div>';

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