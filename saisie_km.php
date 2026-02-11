<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin','gestionnaire','chauffeur','benevole']);

// Connexion à la base de données
$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

$message = "";
$messageType = "";

// Connexion PDO
try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Déterminer si l'utilisateur doit voir uniquement ses missions (chauffeurs et bénévoles)
// Seuls admin et gestionnaire voient toutes les missions
$isBenevole = !hasRole('admin') && !hasRole('gestionnaire');
$idsBenevoleConnecte = [];

if ($isBenevole) {
    // Trouver les id_benevole correspondant à l'email de l'utilisateur connecté
    // (un couple peut partager le même email → plusieurs id_benevole)
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (!empty($userEmail)) {
        $stmtBen = $conn->prepare("SELECT id_benevole FROM EPI_benevole WHERE courriel = :email");
        $stmtBen->execute([':email' => $userEmail]);
        $idsBenevoleConnecte = $stmtBen->fetchAll(PDO::FETCH_COLUMN);
        
        // Debug : décommenter pour voir les IDs trouvés dans les logs PHP
        // error_log("Bénévole connecté - Email: $userEmail - IDs trouvés: " . implode(', ', $idsBenevoleConnecte));
        
        // Si aucun ID trouvé, afficher un message à l'utilisateur
        if (empty($idsBenevoleConnecte) && empty($message)) {
            $message = "Attention : Aucune fiche trouvée avec l'email : $userEmail. Contactez un administrateur.";
            $messageType = "error";
        }
    }
}

// Fonction pour convertir la date en français
function dateEnFrancais($date) {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    
    $timestamp = strtotime($date);
    $jour = $jours[date('w', $timestamp)];
    $numeroJour = date('d', $timestamp);
    $nomMois = $mois[intval(date('m', $timestamp))];
    $annee = date('Y', $timestamp);
    
    return "$jour $numeroJour $nomMois $annee";
}

// Traitement de la mise à jour
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_mission'])) {
    try {
        // Sécurité : chauffeurs et bénévoles ne peuvent modifier que leurs propres missions
        if ($isBenevole) {
            $stmtCheck = $conn->prepare("SELECT id_benevole FROM EPI_mission WHERE id_mission = :id_mission");
            $stmtCheck->execute([':id_mission' => $_POST['id_mission']]);
            $missionCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$missionCheck || !in_array($missionCheck['id_benevole'], $idsBenevoleConnecte)) {
                $message = "Accès refusé : cette mission ne vous appartient pas.";
                $messageType = "error";
                goto skipUpdate;
            }
        }

        // Récupérer l'email de l'utilisateur connecté
        $userEmail = $_SESSION['user']['email'] ?? '';

        $sql = "UPDATE EPI_mission SET
                km_saisi = :km_saisi,
                km_calcule = :km_calcule,
                heure_depart_mission = :heure_depart_mission,
                heure_retour_mission = :heure_retour_mission,
                duree = :duree,
                email_km = :email_km,
                date_km = NOW()
                WHERE id_mission = :id_mission";

        $stmt = $conn->prepare($sql);

        // Calcul de la durée si les heures sont fournies
        $duree = null;
        if (!empty($_POST['heure_depart_mission']) && !empty($_POST['heure_retour_mission'])) {
            $depart = new DateTime($_POST['heure_depart_mission']);
            $retour = new DateTime($_POST['heure_retour_mission']);
            $interval = $depart->diff($retour);
            $duree = $interval->format('%H:%I:00');
        }

        $stmt->execute([
            ':km_saisi' => isset($_POST['km_saisi']) && $_POST['km_saisi'] !== '' ? $_POST['km_saisi'] : null,
            ':km_calcule' => !empty($_POST['km_calcule']) ? $_POST['km_calcule'] : null,
            ':heure_depart_mission' => !empty($_POST['heure_depart_mission']) ? $_POST['heure_depart_mission'] : null,
            ':heure_retour_mission' => !empty($_POST['heure_retour_mission']) ? $_POST['heure_retour_mission'] : null,
            ':duree' => $duree,
            ':email_km' => $userEmail,
            ':id_mission' => $_POST['id_mission']
        ]);

        $message = "Mission mise à jour avec succès !";
        $messageType = "success";

        skipUpdate:
    } catch(PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer les paramètres de filtre
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les missions
try {
    $sql = "SELECT id_mission, date_mission, heure_rdv, 
            benevole, adresse_benevole, cp_benevole, commune_benevole,
            aide, adresse_aide, cp_aide, commune_aide,
            adresse_destination, cp_destination, commune_destination,
            nature_intervention, commentaires,
            km_saisi, km_calcule, heure_depart_mission, heure_retour_mission, duree
            FROM EPI_mission 
            WHERE benevole IS NOT NULL 
            AND TRIM(benevole) != ''
            AND km_saisi IS NULL";    
    $params = [];

    // Filtre : chauffeurs et bénévoles ne voient que leurs propres missions
    // (un couple peut partager le même email → plusieurs id_benevole)
    if ($isBenevole && !empty($idsBenevoleConnecte)) {
        $placeholders = [];
        foreach ($idsBenevoleConnecte as $i => $id) {
            $placeholders[] = ":id_ben_$i";
            $params[":id_ben_$i"] = $id;
        }
        $sql .= " AND id_benevole IN (" . implode(', ', $placeholders) . ")";
    } elseif ($isBenevole && empty($idsBenevoleConnecte)) {
        // Chauffeur/bénévole non trouvé en base → aucune mission à afficher
        $sql .= " AND 1=0";
    }

    // Recherche par date, aidé ou bénévole
    if ($search) {
        $sql .= " AND (date_mission LIKE :search OR aide LIKE :search OR benevole LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY date_mission ASC, heure_rdv ASC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug : afficher le nombre de missions trouvées (pour diagnostic)
    // error_log("Missions trouvées pour bénévole : " . count($missions) . " | isBenevole: " . ($isBenevole ? 'OUI' : 'NON') . " | IDs: " . implode(',', $idsBenevoleConnecte));
    
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie KM et Heures</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
            font-size: 28px;
        }

        .message {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .info-banner {
            background: #d1ecf1;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
            font-size: 13px;
            color: #0c5460;
            text-align: center;
        }

        .filter-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 250px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .filter-group input[type="text"],
        .filter-group input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            user-select: none;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-filter {
            padding: 10px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-filter:active {
            transform: translateY(0);
        }

        .mission-grid {
            display: grid;
            gap: 20px;
        }

        .mission-card {
            background: white;
            border: 4px solid #667eea;
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .mission-card:hover {
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
            transform: translateY(-2px);
            border-color: #764ba2;
        }

        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f5;
        }

        .mission-date {
            font-size: 16px;
            font-weight: 700;
            color: #667eea;
        }

        .mission-id {
            font-size: 12px;
            color: #868e96;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 4px;
        }

        .mission-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .detail-section h4 {
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .detail-section p {
            color: #212529;
            font-size: 14px;
            line-height: 1.6;
            margin: 5px 0;
        }

        .detail-section strong {
            color: #495057;
            font-weight: 600;
        }

        .form-section {
            background: #f1f3f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .form-row {
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
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 6px;
        }

        .form-group input {
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .btn-calculate {
            padding: 10px 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-calculate:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.5);
        }

        .btn-calculate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-save {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            width: 100%;
            margin-top: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .calc-message {
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 13px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .calc-message.loading {
            background: #cfe2ff;
            color: #084298;
            border-left: 4px solid #0d6efd;
        }

        .calc-message.success {
            background: #d1e7dd;
            color: #0f5132;
            border-left: 4px solid #198754;
        }

        .calc-message.error {
            background: #f8d7da;
            color: #842029;
            border-left: 4px solid #dc3545;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #868e96;
            font-size: 16px;
        }

        .no-results::before {
            content: "🔍";
            display: block;
            font-size: 48px;
            margin-bottom: 15px;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        @media (max-width: 768px) {
            .mission-details {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
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
        <h1>📊 Saisie des Kilomètres et Heures de Mission</h1>

        <div class="info-banner">
            💡 <strong>Calcul normal :</strong> (Bénévole → Aidé + Aidé → Destination) × 2, arrondi à l'entier supérieur.<br>
            📋 <strong>Mission administrative</strong> (ville aidé = 29840 ADMINISTRATIF) : (Bénévole → Destination) × 2, arrondi à l'entier supérieur.
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filtres de recherche -->
        <div class="filter-box">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>🔎 Rechercher par date, aidé ou bénévole</label>
                        <input type="text" 
                               name="search" 
                               placeholder="Ex: 2024-01-15, Dupont, Martin..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    

                    <button type="submit" class="btn-filter">🔍 Filtrer</button>
                </div>
            </form>
        </div>

        <?php if (empty($missions)): ?>
            <div class="no-results">
                <p>Aucune mission trouvée avec ces critères.</p>
            </div>
        <?php else: ?>
            <div class="mission-grid">
                <?php foreach ($missions as $mission): ?>
                    <div class="mission-card">
                        <div class="mission-header">
                            <div class="mission-date">
                                📅 <?php echo dateEnFrancais($mission['date_mission']); ?>
                                <?php if ($mission['heure_rdv']): ?>
                                    à <?php echo substr($mission['heure_rdv'], 0, 5); ?>
                                <?php endif; ?>
                            </div>
                            <div class="mission-id">Mission #<?php echo $mission['id_mission']; ?></div>
                        </div>

                        <div class="mission-details">
                            <div class="detail-section">
                                <h4>👤 Bénévole</h4>
                                <p><strong><?php echo htmlspecialchars($mission['benevole']); ?></strong></p>
                                <p><?php echo htmlspecialchars($mission['adresse_benevole']); ?></p>
                                <p><?php echo htmlspecialchars($mission['cp_benevole']); ?> <?php echo htmlspecialchars($mission['commune_benevole']); ?></p>
                            </div>

                            <div class="detail-section">
                                <h4>🤝 Aidé</h4>
                                <p><strong><?php echo htmlspecialchars($mission['aide']); ?></strong></p>
                                <p><?php echo htmlspecialchars($mission['adresse_aide']); ?></p>
                                <p><?php echo htmlspecialchars($mission['cp_aide']); ?> <?php echo htmlspecialchars($mission['commune_aide']); ?></p>
                            </div>

                            <div class="detail-section">
                                <h4>📍 Destination</h4>
                                <p><?php echo htmlspecialchars($mission['adresse_destination']); ?></p>
                                <p><?php echo htmlspecialchars($mission['cp_destination']); ?> <?php echo htmlspecialchars($mission['commune_destination']); ?></p>
                                <?php if ($mission['nature_intervention']): ?>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($mission['nature_intervention']); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ($mission['commentaires']): ?>
                                <div class="detail-section" style="grid-column: 1 / -1;">
                                    <h4>💬 Commentaires</h4>
                                    <p><?php echo nl2br(htmlspecialchars($mission['commentaires'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="id_mission" value="<?php echo $mission['id_mission']; ?>">
                            
                            <div class="form-section">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>🕐 Heure Départ</label>
                                        <input type="time" 
                                               id="heure_depart_<?php echo $mission['id_mission']; ?>"
                                               name="heure_depart_mission" 
                                               value="<?php echo $mission['heure_depart_mission'] ? substr($mission['heure_depart_mission'], 0, 5) : ''; ?>"
                                               onchange="calculateDuree(<?php echo $mission['id_mission']; ?>)">
                                    </div>

                                    <div class="form-group">
                                        <label>🕐 Heure Retour</label>
                                        <input type="time" 
                                               id="heure_retour_<?php echo $mission['id_mission']; ?>"
                                               name="heure_retour_mission" 
                                               value="<?php echo $mission['heure_retour_mission'] ? substr($mission['heure_retour_mission'], 0, 5) : ''; ?>"
                                               onchange="calculateDuree(<?php echo $mission['id_mission']; ?>)">
                                    </div>

                                    <div class="form-group">
                                        <label>⏱️ Durée (auto)</label>
                                        <input type="text" 
                                               id="duree_display_<?php echo $mission['id_mission']; ?>"
                                               readonly
                                               value="<?php 
                                                   if ($mission['duree']) {
                                                       list($h, $m, $s) = explode(':', $mission['duree']);
                                                       echo $h > 0 ? $h . 'h' . $m : $m . 'min';
                                                   }
                                               ?>">
                                        <input type="hidden" 
                                               id="duree_<?php echo $mission['id_mission']; ?>"
                                               name="duree">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>🚗 KM Saisi (manuel)</label>
                                        <input type="number" 
                                               name="km_saisi" 
                                               step="1"
                                               placeholder="KM manuel"
                                               value="<?php echo $mission['km_saisi'] !== null ? intval($mission['km_saisi']) : ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>🗺️ KM Calculé (auto)</label>
                                        <input type="number" 
                                               id="km_calcule_<?php echo $mission['id_mission']; ?>"
                                               name="km_calcule" 
                                               step="1"
                                               readonly
                                               value="<?php echo $mission['km_calcule'] !== null ? intval($mission['km_calcule']) : ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" 
                                                class="btn-calculate"
                                                onclick="calculateDistanceGoogleMaps(
                                                    <?php echo $mission['id_mission']; ?>,
                                                    '<?php echo addslashes($mission['aide']); ?>',
                                                    '<?php echo addslashes($mission['adresse_benevole']); ?>',
                                                    '<?php echo addslashes($mission['cp_benevole']); ?>',
                                                    '<?php echo addslashes($mission['commune_benevole']); ?>',
                                                    '<?php echo addslashes($mission['adresse_aide']); ?>',
                                                    '<?php echo addslashes($mission['cp_aide']); ?>',
                                                    '<?php echo addslashes($mission['commune_aide']); ?>',
                                                    '<?php echo addslashes($mission['adresse_destination']); ?>',
                                                    '<?php echo addslashes($mission['cp_destination']); ?>',
                                                    '<?php echo addslashes($mission['commune_destination']); ?>'
                                                )">
                                            🗺️ Calculer KM
                                        </button>
                                    </div>
                                </div>

                                <div id="calc_msg_<?php echo $mission['id_mission']; ?>" class="calc-message" style="display:none;"></div>
                                <button type="submit" class="btn-save">💾 Enregistrer</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showCalcMessage(id, text, type) {
            const msgDiv = document.getElementById('calc_msg_' + id);
            msgDiv.style.display = 'block';
            msgDiv.className = 'calc-message ' + type;
            msgDiv.textContent = text;
        }

        // Fonction de pause (optionnelle, pour espacement entre requêtes si besoin)
        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        async function calculateDistanceGoogleMaps(id, aideNom, benevoleAddr, benevoleCp, benevoleVille, aideAddr, aideCp, aideVille, destAddr, destCp, destVille) {
            const calcBtn = event.target;
            calcBtn.disabled = true;
            calcBtn.textContent = '⏳ Calcul...';
            
            showCalcMessage(id, '🔍 Calcul de la distance...', 'loading');

            try {
                // Validation des adresses
                if (!benevoleAddr || !destAddr) {
                    throw new Error('Adresses incomplètes pour le calcul');
                }

                // Vérifier si c'est une mission administrative (ville aidé = "29840 ADMINISTRATIF")
                const isAdministratif = aideCp && aideVille &&
                    aideCp.trim() === '29840' &&
                    aideVille.trim().toUpperCase() === 'ADMINISTRATIF';
                
                if (isAdministratif) {
                    // CAS ADMINISTRATIF : Seulement Bénévole → Destination × 2
                    showCalcMessage(id, '📋 Mission administrative détectée - Calcul simplifié...', 'loading');
                    
                    // Géocoder uniquement 2 adresses
                    showCalcMessage(id, '📍 Localisation des adresses...', 'loading');
                    const [coordsBenevole, coordsDest] = await Promise.all([
                        geocodeAddress(`${benevoleAddr}, ${benevoleCp} ${benevoleVille}, France`),
                        geocodeAddress(`${destAddr}, ${destCp} ${destVille}, France`)
                    ]);
                    
                    // Calculer la distance directe
                    showCalcMessage(id, '🚗 Calcul de la distance...', 'loading');
                    const distBenevoleVersDest = await calculateRoute(coordsBenevole, coordsDest);
                    
                    // Distance totale = Distance × 2 (aller-retour), arrondi à l'entier supérieur
                    const totalKm = Math.ceil(distBenevoleVersDest * 2);
                    
                    document.getElementById('km_calcule_' + id).value = totalKm;
                    
                    showCalcMessage(id, `✓ Distance calculée (mission administrative) : ${totalKm} km (${Math.round(distBenevoleVersDest)} km × 2, arrondi sup.)
                        [${Math.round(distBenevoleVersDest)}km bénévole→destination direct]`, 'success');
                        
                } else {
                    // CAS NORMAL : Bénévole → Aidé → Destination × 2
                    if (!aideAddr) {
                        throw new Error('Adresse aidé manquante pour le calcul');
                    }
                    
                    // Étape 1 : Géocoder les 3 adresses EN PARALLÈLE
                    showCalcMessage(id, '📍 Localisation des adresses...', 'loading');
                    
                    const [coordsBenevole, coordsAide, coordsDest] = await Promise.all([
                        geocodeAddress(`${benevoleAddr}, ${benevoleCp} ${benevoleVille}, France`),
                        geocodeAddress(`${aideAddr}, ${aideCp} ${aideVille}, France`),
                        geocodeAddress(`${destAddr}, ${destCp} ${destVille}, France`)
                    ]);

                    // Étape 2 : Calculer les 2 distances EN PARALLÈLE
                    showCalcMessage(id, '🚗 Calcul des distances...', 'loading');
                    
                    const [distBenevoleVersAide, distAideVersDest] = await Promise.all([
                        calculateRoute(coordsBenevole, coordsAide),
                        calculateRoute(coordsAide, coordsDest)
                    ]);
                    
                    // Distance aller = Bénévole → Aidé + Aidé → Destination
                    const distanceAller = distBenevoleVersAide + distAideVersDest;
                    
                    // Distance totale = Aller × 2 (aller-retour), arrondi à l'entier supérieur
                    const totalKm = Math.ceil(distanceAller * 2);
                    
                    document.getElementById('km_calcule_' + id).value = totalKm;
                    
                    showCalcMessage(id, `✓ Distance calculée : ${totalKm} km (${Math.round(distanceAller)} km × 2, arrondi sup.)
                        [${Math.round(distBenevoleVersAide)}km bénévole→aidé + ${Math.round(distAideVersDest)}km aidé→dest]`, 'success');
                }

            } catch (error) {
                showCalcMessage(id, '❌ Erreur : ' + error.message, 'error');
                console.error('Erreur détaillée:', error);
            } finally {
                calcBtn.disabled = false;
                calcBtn.textContent = '🗺️ Calculer KM';
            }
        }

        // Fonction pour géocoder une adresse via proxy OpenRouteService
        async function geocodeAddress(address) {
            try {
                console.log('🔍 Geocoding via proxy OpenRouteService:', address);
                
                const response = await fetch('openroute_proxy.php?action=geocode&address=' + encodeURIComponent(address));
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `Erreur HTTP ${response.status}`);
                }
                
                const data = await response.json();
                console.log('✅ Résultat géocodage:', data);
                
                if (data.features && data.features.length > 0) {
                    const coords = data.features[0].geometry.coordinates;
                    return {
                        lon: coords[0],
                        lat: coords[1]
                    };
                } else {
                    throw new Error(`Adresse non trouvée : ${address}`);
                }
            } catch (error) {
                console.error('❌ Erreur géocodage:', error);
                throw new Error(`Géocodage impossible pour "${address}": ${error.message}`);
            }
        }

        // Fonction pour calculer la distance routière via proxy OpenRouteService
        async function calculateRoute(origin, destination) {
            try {
                console.log('🚗 Calcul d\'itinéraire via proxy OpenRouteService');
                
                const response = await fetch('openroute_proxy.php?action=route', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        coordinates: [
                            [origin.lon, origin.lat],
                            [destination.lon, destination.lat]
                        ]
                    })
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `Erreur HTTP ${response.status}`);
                }
                
                const data = await response.json();
                console.log('✅ Résultat itinéraire:', data);
                if (data.routes && data.routes.length > 0) {  // ✅ > 0 au lieu de >= 0
    const distanceMeters = data.routes[0].summary.distance;
    
    // Gérer le cas distance nulle (adresses identiques ou très proches)
    if (!distanceMeters || distanceMeters === 0) {
        console.warn('⚠️ Distance nulle détectée (adresses identiques ou très proches)');
        return 0;  // Retourner 0 au lieu d'une erreur
    }
    
    return distanceMeters / 1000; // Convertir en km
} else {
    throw new Error('Itinéraire non trouvé');
}

            } catch (error) {
                console.error('❌ Erreur calcul itinéraire:', error);
                throw new Error(`Calcul d'itinéraire impossible: ${error.message}`);
            }
        }

        function calculateDuree(id) {
            const heureDepartInput = document.getElementById('heure_depart_' + id);
            const heureRetourInput = document.getElementById('heure_retour_' + id);
            const dureeDisplay = document.getElementById('duree_display_' + id);
            const dureeHidden = document.getElementById('duree_' + id);
            
            const heureDepart = heureDepartInput.value;
            const heureRetour = heureRetourInput.value;
            
            if (!heureDepart || !heureRetour) {
                dureeDisplay.value = '';
                dureeHidden.value = '';
                return;
            }
            
            try {
                const [hDepart, mDepart] = heureDepart.split(':').map(Number);
                const [hRetour, mRetour] = heureRetour.split(':').map(Number);
                
                let minutesDepart = hDepart * 60 + mDepart;
                let minutesRetour = hRetour * 60 + mRetour;
                
                if (minutesRetour < minutesDepart) {
                    minutesRetour += 24 * 60;
                }
                
                const diffMinutes = minutesRetour - minutesDepart;
                const heures = Math.floor(diffMinutes / 60);
                const minutes = diffMinutes % 60;
                
                const dureeFormatted = String(heures).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':00';
                dureeHidden.value = dureeFormatted;
                
                let dureeDisplay_text = '';
                if (heures > 0) {
                    dureeDisplay_text = heures + 'h' + String(minutes).padStart(2, '0');
                } else {
                    dureeDisplay_text = minutes + 'min';
                }
                dureeDisplay.value = dureeDisplay_text;
                
            } catch (e) {
                console.error('Erreur calcul durée:', e);
                dureeDisplay.value = '';
                dureeHidden.value = '';
            }
        }

        // Fermer le message de succès automatiquement après 5 secondes
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>