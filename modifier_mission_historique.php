<?php
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

$message = "";
$messageType = "";
$mission = null;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les bénévoles
$benevoles = [];
try {
    $stmt = $conn->query("SELECT id_benevole, nom, adresse, code_postal, commune, tel_fixe, tel_mobile FROM EPI_benevole ORDER BY nom");
    $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Renommer tel_mobile en tel_portable pour compatibilité avec le code
    foreach($benevoles as &$b) {
        $b['tel_portable'] = isset($b['tel_mobile']) ? $b['tel_mobile'] : '';
    }
    unset($b); // Détruire la référence pour éviter les problèmes
} catch(PDOException $e) {
    // Si erreur (champs manquants), essayer avec seulement les champs de base
    try {
        $stmt = $conn->query("SELECT id_benevole, nom, adresse FROM EPI_benevole ORDER BY nom");
        $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Ajouter les champs manquants vides
        foreach($benevoles as &$b) {
            if (!isset($b['code_postal'])) $b['code_postal'] = '';
            if (!isset($b['commune'])) $b['commune'] = '';
            if (!isset($b['tel_fixe'])) $b['tel_fixe'] = '';
            $b['tel_portable'] = '';
        }
        unset($b); // Détruire la référence pour éviter les problèmes
    } catch(PDOException $e2) {
        // En dernier recours, juste id et nom
        try {
            $stmt = $conn->query("SELECT id_benevole, nom FROM EPI_benevole ORDER BY nom");
            $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($benevoles as &$b) {
                $b['adresse'] = '';
                $b['code_postal'] = '';
                $b['commune'] = '';
                $b['tel_fixe'] = '';
                $b['tel_portable'] = '';
            }
            unset($b); // Détruire la référence pour éviter les problèmes
        } catch(PDOException $e3) {}
    }
}

// Récupérer les aidés
$aides = [];
try {
    $stmt = $conn->query("SELECT id_aide, nom, adresse, code_postal, commune, tel_fixe, tel_portable FROM EPI_aide ORDER BY nom");
    $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Si erreur (champs manquants), essayer avec seulement les champs de base
    try {
        $stmt = $conn->query("SELECT id_aide, nom, adresse FROM EPI_aide ORDER BY nom");
        $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Ajouter les champs manquants vides
        foreach($aides as &$a) {
            if (!isset($a['code_postal'])) $a['code_postal'] = '';
            if (!isset($a['commune'])) $a['commune'] = '';
            if (!isset($a['tel_fixe'])) $a['tel_fixe'] = '';
            if (!isset($a['tel_portable'])) $a['tel_portable'] = '';
        }
        unset($a); // Détruire la référence pour éviter les problèmes
    } catch(PDOException $e2) {
        // En dernier recours, juste id et nom
        try {
            $stmt = $conn->query("SELECT id_aide, nom FROM EPI_aide ORDER BY nom");
            $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($aides as &$a) {
                $a['adresse'] = '';
                $a['code_postal'] = '';
                $a['commune'] = '';
                $a['tel_fixe'] = '';
                $a['tel_portable'] = '';
            }
            unset($a); // Détruire la référence pour éviter les problèmes
        } catch(PDOException $e3) {}
    }
}

// Récupérer les villes
$villes = [];
try {
    $stmt = $conn->query("SELECT ville, cp FROM EPI_ville ORDER BY ville");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Récupérer les natures d'intervention depuis la table EPI_intervention
$natures_intervention = [];
try {
    $stmt = $conn->query("SELECT DISTINCT Nature_intervention FROM EPI_intervention ORDER BY Nature_intervention");
    $natures_intervention = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Récupérer les infos complètes de l'aidé pour la mission en cours
$aideInfo = null;
$benevoleInfo = null;
if (isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT m.id_aide, m.id_benevole FROM EPI_mission m WHERE m.id_mission = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $missionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($missionData && $missionData['id_aide']) {
            $stmt = $conn->prepare("SELECT adresse, code_postal, commune, tel_fixe, tel_portable FROM EPI_aide WHERE id_aide = :id");
            $stmt->execute([':id' => $missionData['id_aide']]);
            $aideInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($missionData && $missionData['id_benevole']) {
            $stmt = $conn->prepare("SELECT adresse, code_postal, commune, tel_fixe, tel_mobile FROM EPI_benevole WHERE id_benevole = :id");
            $stmt->execute([':id' => $missionData['id_benevole']]);
            $benevoleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            // Renommer tel_mobile en tel_portable pour compatibilité
            if ($benevoleInfo && isset($benevoleInfo['tel_mobile'])) {
                $benevoleInfo['tel_portable'] = $benevoleInfo['tel_mobile'];
            }
        }
    } catch(PDOException $e) {}
}

// Calculer le premier jour du mois courant
$premierJourMoisCourant = date('Y-m-01');

// Récupérer TOUTES les missions pour le filtre avec recherche (AUCUNE RESTRICTION DE DATE)
$missions = [];
$searchAide = isset($_GET['search_aide']) ? $_GET['search_aide'] : '';
$searchBenevole = isset($_GET['search_benevole']) ? $_GET['search_benevole'] : '';
$searchDate = isset($_GET['search_date']) ? $_GET['search_date'] : '';

try {
    // Construire la requête SQL dynamiquement selon les filtres actifs
    $sql = "SELECT id_mission, date_mission, aide, benevole 
            FROM EPI_mission 
            WHERE 1=1";
    
    $params = [];
    
    if ($searchAide) {
        $sql .= " AND aide LIKE :search_aide";
        $params[':search_aide'] = "%$searchAide%";
    }
    
    if ($searchBenevole) {
        $sql .= " AND benevole LIKE :search_benevole";
        $params[':search_benevole'] = "%$searchBenevole%";
    }
    
    if ($searchDate) {
        $sql .= " AND date_mission = :search_date";
        $params[':search_date'] = $searchDate;
    }
    
    $sql .= " ORDER BY date_mission DESC LIMIT 200";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Traitement de la suppression (SANS RESTRICTION DE DATE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id_mission'])) {
    try {
        $stmt = $conn->prepare("SELECT date_mission, aide FROM EPI_mission WHERE id_mission = :id");
        $stmt->execute([':id' => $_POST['id_mission']]);
        $missionToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$missionToDelete) {
            $errorMsg = urlencode("Mission introuvable");
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg);
            exit();
        }
        
        // SUPPRESSION DE LA RESTRICTION DE DATE - toutes les missions peuvent être supprimées
        
        $stmt = $conn->prepare("DELETE FROM EPI_mission WHERE id_mission = :id");
        $stmt->execute([':id' => $_POST['id_mission']]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
        exit();
        
    } catch(PDOException $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg);
        exit();
    }
}

// Traitement de la modification (SANS RESTRICTION DE DATE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_mission']) && (!isset($_POST['action']) || $_POST['action'] != 'delete')) {
    
    // SUPPRESSION DE LA VÉRIFICATION DE DATE - toutes les missions peuvent être modifiées
    
    try {
        // Calculer la durée
        $duree = null;
        if (!empty($_POST['heure_depart_mission']) && !empty($_POST['heure_retour_mission'])) {
            $depart = new DateTime($_POST['heure_depart_mission']);
            $retour = new DateTime($_POST['heure_retour_mission']);
            $interval = $depart->diff($retour);
            $duree = $interval->format('%H:%I:%S');
        }
        
        // Si aucun bénévole n'est sélectionné (id_benevole vide), on efface toutes les infos du bénévole
        $id_benevole = !empty($_POST['id_benevole']) ? $_POST['id_benevole'] : null;
        $benevole = null;
        $adresse_benevole = null;
        $cp_benevole = null;
        $commune_benevole = null;
        $secteur_benevole = null;
        
        // Si un bénévole est sélectionné, on garde ses infos
        if ($id_benevole) {
            $benevole = !empty($_POST['benevole']) ? $_POST['benevole'] : null;
            $adresse_benevole = !empty($_POST['adresse_benevole']) ? $_POST['adresse_benevole'] : null;
            $cp_benevole = !empty($_POST['cp_benevole']) ? $_POST['cp_benevole'] : null;
            $commune_benevole = !empty($_POST['commune_benevole']) ? $_POST['commune_benevole'] : null;
            $secteur_benevole = !empty($_POST['secteur_benevole']) ? $_POST['secteur_benevole'] : null;
        }
        
        $sql = "UPDATE EPI_mission SET 
                date_mission = :date_mission, heure_depart_mission = :heure_depart_mission,
                heure_retour_mission = :heure_retour_mission, duree = :duree, km_saisi = :km_saisi,
                id_benevole = :id_benevole, benevole = :benevole,
                adresse_benevole = :adresse_benevole, cp_benevole = :cp_benevole,
                commune_benevole = :commune_benevole, secteur_benevole = :secteur_benevole,
                id_aide = :id_aide, aide = :aide, adresse_aide = :adresse_aide,
                cp_aide = :cp_aide, commune_aide = :commune_aide, secteur_aide = :secteur_aide,
                adresse_destination = :adresse_destination, cp_destination = :cp_destination,
                commune_destination = :commune_destination, heure_rdv = :heure_rdv,
                nature_intervention = :nature_intervention, commentaires = :commentaires
                WHERE id_mission = :id_mission";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':date_mission' => !empty($_POST['date_mission']) ? $_POST['date_mission'] : null,
            ':heure_depart_mission' => !empty($_POST['heure_depart_mission']) ? $_POST['heure_depart_mission'] : null,
            ':heure_retour_mission' => !empty($_POST['heure_retour_mission']) ? $_POST['heure_retour_mission'] : null,
            ':duree' => $duree,
            ':km_saisi' => isset($_POST['km_saisi']) && $_POST['km_saisi'] !== '' ? $_POST['km_saisi'] : null,
            ':id_benevole' => $id_benevole,
            ':benevole' => $benevole,
            ':adresse_benevole' => $adresse_benevole,
            ':cp_benevole' => $cp_benevole,
            ':commune_benevole' => $commune_benevole,
            ':secteur_benevole' => $secteur_benevole,
            ':id_aide' => !empty($_POST['id_aide']) ? $_POST['id_aide'] : null,
            ':aide' => !empty($_POST['aide']) ? $_POST['aide'] : null,
            ':adresse_aide' => !empty($_POST['adresse_aide']) ? $_POST['adresse_aide'] : null,
            ':cp_aide' => !empty($_POST['cp_aide']) ? $_POST['cp_aide'] : null,
            ':commune_aide' => !empty($_POST['commune_aide']) ? $_POST['commune_aide'] : null,
            ':secteur_aide' => !empty($_POST['secteur_aide']) ? $_POST['secteur_aide'] : null,
            ':adresse_destination' => !empty($_POST['adresse_destination']) ? $_POST['adresse_destination'] : null,
            ':cp_destination' => !empty($_POST['cp_destination']) ? $_POST['cp_destination'] : null,
            ':commune_destination' => !empty($_POST['commune_destination']) ? $_POST['commune_destination'] : null,
            ':heure_rdv' => !empty($_POST['heure_rdv']) ? $_POST['heure_rdv'] : null,
            ':nature_intervention' => !empty($_POST['nature_intervention']) ? $_POST['nature_intervention'] : null,
            ':commentaires' => !empty($_POST['commentaires']) ? $_POST['commentaires'] : null,
            ':id_mission' => $_POST['id_mission']
        ]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&id=" . $_POST['id_mission']);
        exit();
        
    } catch(PDOException $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg . "&id=" . $_POST['id_mission']);
        exit();
    }
}

// Charger la mission sélectionnée
if (isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM EPI_mission WHERE id_mission = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mission) {
            $message = "❌ Mission introuvable";
            $messageType = "error";
        }
        // SUPPRESSION DE LA RESTRICTION DE DATE - toutes les missions peuvent être chargées et modifiées
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

if (isset($_GET['success'])) {
    $message = "✅ Mission modifiée avec succès !";
    $messageType = "success";
} elseif (isset($_GET['deleted'])) {
    $message = "✅ Mission supprimée avec succès !";
    $messageType = "success";
} elseif (isset($_GET['error'])) {
    $message = "❌ Erreur : " . urldecode($_GET['error']);
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Mission Historique - Entraide Plus Iroise</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
            font-size: 24px;
        }

        h3 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }

        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .search-box label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .search-row {
            display: flex;
            gap: 10px;
        }

        .search-row input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-row button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-box select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
        }

        .info-notice {
            background: #fff3cd;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 15px;
            font-size: 13px;
            color: #856404;
        }

        .mission-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .mission-summary h2 {
            font-size: 18px;
            margin-bottom: 12px;
            text-align: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .summary-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 12px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .summary-item strong {
            display: block;
            font-size: 11px;
            opacity: 0.9;
            margin-bottom: 4px;
        }

        .summary-item span {
            font-size: 15px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-color: white;
            padding-right: 40px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[readonly] {
            background-color: #f5f5f5;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .button-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .btn-delete {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        }

        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .no-selection {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .info-box {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
            margin-bottom: 15px;
            font-size: 13px;
            color: #1565c0;
        }

        .duree-display {
            background: #d4edda;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-top: 10px;
            font-size: 14px;
            color: #155724;
            font-weight: 600;
        }

        /* Modal de confirmation */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease;
            text-align: center;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #dc3545;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .modal-body {
            margin-bottom: 25px;
            color: #666;
            font-size: 14px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-confirm {
            background: #dc3545;
            color: white;
        }

        @media (max-width: 768px) {
            .row, .search-row, .summary-grid, .button-row {
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
        <h1>✏️ Modifier Mission Historique</h1>

        <div class="info-notice" style="background: #ffe6e6; border-left: 4px solid #dc3545; color: #721c24;">
            ⚠️ <strong>MODE HISTORIQUE :</strong> Cette page permet de modifier TOUTES les missions, y compris les missions passées. À utiliser avec précaution ! Pour la modification normale (mois courant et ultérieur uniquement), utilisez <a href="modifier_mission.php" style="color: #0056b3; text-decoration: underline;">modifier_mission.php</a>.
        </div>

        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_GET['id'])): ?>
        <div class="search-box">
            <label>🔍 Rechercher une mission</label>
            <form method="GET" style="margin-bottom: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                    <div>
                        <input type="text" name="search_aide" placeholder="Nom de l'aidé..." 
                               value="<?php echo htmlspecialchars($searchAide); ?>"
                               title="Rechercher dans les noms des aidés">
                    </div>
                    <div>
                        <input type="date" name="search_date" 
                               value="<?php echo htmlspecialchars($searchDate); ?>"
                               title="Filtrer par date exacte">
                    </div>
                    <div>
                        <input type="text" name="search_benevole" placeholder="Nom du bénévole..." 
                               value="<?php echo htmlspecialchars($searchBenevole); ?>"
                               title="Rechercher dans les noms de bénévoles">
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="flex: 1;">🔍 Rechercher</button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" 
                       style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; text-align: center; text-decoration: none; display: block;">
                        🔄 Réinitialiser
                    </a>
                </div>
            </form>

            <?php if ($missions): ?>
            <select onchange="if(this.value) { 
                var url = '?id=' + this.value;
                <?php if ($searchAide): ?>url += '&search_aide=<?php echo urlencode($searchAide); ?>';<?php endif; ?>
                <?php if ($searchBenevole): ?>url += '&search_benevole=<?php echo urlencode($searchBenevole); ?>';<?php endif; ?>
                <?php if ($searchDate): ?>url += '&search_date=<?php echo urlencode($searchDate); ?>';<?php endif; ?>
                window.location.href = url;
            }">
                <option value="">-- Sélectionnez une mission (<?php echo count($missions); ?> trouvée(s)) --</option>
                <?php foreach($missions as $m): ?>
                    <option value="<?php echo $m['id_mission']; ?>" 
                            <?php echo (isset($_GET['id']) && $_GET['id'] == $m['id_mission']) ? 'selected' : ''; ?>>
                        <?php echo date('d/m/Y', strtotime($m['date_mission'])); ?> - 
                        <?php echo htmlspecialchars($m['aide']); ?>
                        <?php if ($m['benevole']): ?>
                            (Bénévole: <?php echo htmlspecialchars($m['benevole']); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php elseif ($searchAide || $searchBenevole || $searchDate): ?>
                <p style="text-align: center; color: #666; margin-top: 15px; background: #fff3cd; padding: 12px; border-radius: 8px;">
                    ⚠️ Aucune mission ne correspond à vos critères de recherche.
                </p>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin-top: 15px;">
                    Aucune mission trouvée.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($mission): ?>
        
        <!-- Résumé de la mission -->
        <div class="mission-summary">
            <h2>📋 Résumé de la mission</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <strong>📅 Date et heure</strong>
                    <span><?php echo date('d/m/Y', strtotime($mission['date_mission'])); ?>
                    <?php if($mission['heure_rdv']): ?>
                        à <?php echo substr($mission['heure_rdv'], 0, 5); ?>
                    <?php endif; ?>
                    </span>
                </div>
                <div class="summary-item">
                    <strong>🤝 Personne aidée</strong>
                    <span><?php echo htmlspecialchars($mission['aide']); ?></span>
                </div>
                <div class="summary-item">
                    <strong>👤 Bénévole assigné</strong>
                    <span><?php echo htmlspecialchars($mission['benevole'] ?: 'Non assigné'); ?></span>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="mainForm">
            <input type="hidden" name="id_mission" value="<?php echo $mission['id_mission']; ?>">

            <h3>📅 Date et Heure de rendez-vous</h3>
            <div class="row">
                <div class="form-group">
                    <label for="date_mission">Date de la mission *</label>
                    <input type="date" id="date_mission" name="date_mission" required 
                           value="<?php echo $mission['date_mission']; ?>">
                </div>
                <div class="form-group">
                    <label for="heure_rdv">Heure de rendez-vous</label>
                    <input type="time" id="heure_rdv" name="heure_rdv" 
                           value="<?php echo $mission['heure_rdv']; ?>">
                </div>
            </div>

            <h3>🤝 Personne Aidée</h3>
            <div class="form-group">
                <label for="id_aide">Sélectionner un aidé</label>
                <select id="id_aide" name="id_aide" onchange="chargerInfosAide(this.value)">
                    <option value="">-- Sélectionner un aidé --</option>
                    <?php foreach ($aides as $a): ?>
                        <option value="<?php echo $a['id_aide']; ?>"
                                data-nom="<?php echo htmlspecialchars($a['nom']); ?>"
                                data-adresse="<?php echo htmlspecialchars($a['adresse']); ?>"
                                data-cp="<?php echo htmlspecialchars($a['code_postal']); ?>"
                                data-commune="<?php echo htmlspecialchars($a['commune']); ?>"
                                data-tel-fixe="<?php echo htmlspecialchars($a['tel_fixe']); ?>"
                                data-tel-portable="<?php echo htmlspecialchars($a['tel_portable']); ?>"
                                <?php echo ($mission['id_aide'] == $a['id_aide']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Affichage dynamique des informations de l'aidé (mis à jour via JavaScript) -->
            <div id="infos_aide_display" style="display: none; margin-top: -10px; margin-bottom: 20px; padding: 15px; background-color: #f0f8ff; border-left: 4px solid #667eea; border-radius: 4px;">
                <div id="aide_adresse_display" style="color: #555; margin-bottom: 8px;"></div>
                <div id="aide_telephones_display" style="color: #555;"></div>
            </div>

            <!-- Affichage initial des informations de l'aidé (au chargement de la page) -->
            <?php 
            // Utiliser l'adresse de EPI_mission, sinon celle de EPI_aide
            $adresseAide = !empty($mission['adresse_aide']) ? $mission['adresse_aide'] : ($aideInfo['adresse'] ?? '');
            $cpAide = !empty($mission['cp_aide']) ? $mission['cp_aide'] : ($aideInfo['code_postal'] ?? '');
            $communeAide = !empty($mission['commune_aide']) ? $mission['commune_aide'] : ($aideInfo['commune'] ?? '');
            
            if ($mission['id_aide'] && (!empty($adresseAide) || !empty($aideInfo['tel_fixe']) || !empty($aideInfo['tel_portable']))): 
            ?>
            <div id="infos_aide_initial" style="margin-top: -10px; margin-bottom: 20px; padding: 15px; background-color: #f0f8ff; border-left: 4px solid #667eea; border-radius: 4px;">
                <?php if (!empty($adresseAide)): ?>
                <div style="color: #555; margin-bottom: 8px;">
                    📍 <?php echo htmlspecialchars($adresseAide); 
                        echo !empty($cpAide) ? ', ' . htmlspecialchars($cpAide) : ''; 
                        echo !empty($communeAide) ? ' ' . htmlspecialchars($communeAide) : ''; ?>
                </div>
                <?php endif; ?>
                <div style="color: #555;">
                    <?php if (!empty($aideInfo['tel_fixe'])): ?>
                        ☎️ Fixe : <strong><?php echo htmlspecialchars($aideInfo['tel_fixe']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($aideInfo['tel_fixe']) && !empty($aideInfo['tel_portable'])): ?>
                        <span style="margin: 0 10px;">|</span>
                    <?php endif; ?>
                    <?php if (!empty($aideInfo['tel_portable'])): ?>
                        📱 Portable : <strong><?php echo htmlspecialchars($aideInfo['tel_portable']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <input type="hidden" id="aide" name="aide" value="<?php echo htmlspecialchars($mission['aide']); ?>">
            <input type="hidden" id="adresse_aide" name="adresse_aide" value="<?php echo htmlspecialchars($mission['adresse_aide']); ?>">
            <input type="hidden" id="cp_aide" name="cp_aide" value="<?php echo htmlspecialchars($mission['cp_aide']); ?>">
            <input type="hidden" id="commune_aide" name="commune_aide" value="<?php echo htmlspecialchars($mission['commune_aide']); ?>">
            <input type="hidden" id="secteur_aide" name="secteur_aide" value="<?php echo htmlspecialchars($mission['secteur_aide']); ?>">

            <h3>📝 Nature de la Prestation</h3>
            <div class="form-group">
                <label for="nature_intervention">Nature de l'intervention</label>
                <select id="nature_intervention" name="nature_intervention">
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach($natures_intervention as $nature): ?>
                        <option value="<?php echo htmlspecialchars($nature['Nature_intervention']); ?>" 
                                <?php echo ($mission['nature_intervention'] == $nature['Nature_intervention']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nature['Nature_intervention']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h3>📍 Adresse de Destination</h3>
            <div class="form-group">
                <label for="adresse_destination">Adresse de destination</label>
                <input type="text" id="adresse_destination" name="adresse_destination"
                       value="<?php echo htmlspecialchars($mission['adresse_destination']); ?>">
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="commune_destination">Commune destination</label>
                    <select id="commune_destination" name="commune_destination" onchange="updateCPFromVille()">
                        <option value="">-- Sélectionnez une ville --</option>
                        <?php foreach($villes as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['ville']); ?>" 
                                    data-cp="<?php echo htmlspecialchars($v['cp']); ?>"
                                    <?php echo ($mission['commune_destination'] == $v['ville']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['ville']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cp_destination">Code postal destination</label>
                    <input type="text" id="cp_destination" name="cp_destination" readonly
                           value="<?php echo htmlspecialchars($mission['cp_destination']); ?>">
                </div>
            </div>

            <h3>👤 Bénévole Assigné</h3>
            <div class="form-group">
                <label for="id_benevole">Bénévole *</label>
                <select id="id_benevole" name="id_benevole" onchange="chargerInfosBenevole(this.value)">
                    <option value="">-- Choisissez --</option>
                    <?php foreach($benevoles as $b): ?>
                        <option value="<?php echo $b['id_benevole']; ?>"
                                data-nom="<?php echo htmlspecialchars($b['nom']); ?>"
                                data-adresse="<?php echo htmlspecialchars($b['adresse']); ?>"
                                data-cp="<?php echo htmlspecialchars($b['code_postal']); ?>"
                                data-commune="<?php echo htmlspecialchars($b['commune']); ?>"
                                data-tel-fixe="<?php echo htmlspecialchars($b['tel_fixe']); ?>"
                                data-tel-portable="<?php echo htmlspecialchars($b['tel_portable']); ?>"
                                <?php echo ($mission['id_benevole'] == $b['id_benevole']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Affichage des informations du bénévole -->
            <div id="infos_benevole_display" style="display: none; margin-top: -10px; margin-bottom: 20px; padding: 15px; background-color: #fff3e6; border-left: 4px solid #ff9800; border-radius: 4px;">
                <div id="benevole_adresse_display" style="color: #555; margin-bottom: 8px;"></div>
                <div id="benevole_telephones_display" style="color: #555;"></div>
            </div>

            <input type="hidden" id="benevole" name="benevole" value="<?php echo htmlspecialchars($mission['benevole']); ?>">
            <input type="hidden" id="adresse_benevole" name="adresse_benevole" value="<?php echo htmlspecialchars($mission['adresse_benevole']); ?>">
            <input type="hidden" id="cp_benevole" name="cp_benevole" value="<?php echo htmlspecialchars($mission['cp_benevole']); ?>">
            <input type="hidden" id="commune_benevole" name="commune_benevole" value="<?php echo htmlspecialchars($mission['commune_benevole']); ?>">
            <input type="hidden" id="secteur_benevole" name="secteur_benevole" value="<?php echo htmlspecialchars($mission['secteur_benevole']); ?>">

            <h3>💬 Commentaires Mission</h3>
            <div class="form-group">
                <label for="commentaires">Commentaires</label>
                <textarea id="commentaires" name="commentaires"><?php echo htmlspecialchars($mission['commentaires']); ?></textarea>
            </div>

            <h3>🚗 Kilométrage et Horaires</h3>
            <div class="row">
                <div class="form-group">
                    <label for="km_saisi">Km saisis</label>
                    <input type="number" step="0.01" id="km_saisi" name="km_saisi"
                           value="<?php echo $mission['km_saisi']; ?>">
                </div>
                <div class="form-group">
                    <label for="km_calcule">Km calculés</label>
                    <input type="number" step="0.01" id="km_calcule" name="km_calcule"
                           value="<?php echo $mission['km_calcule']; ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="heure_depart_mission">Heure de départ</label>
                    <input type="time" id="heure_depart_mission" name="heure_depart_mission"
                           onchange="calculerDuree()"
                           value="<?php echo $mission['heure_depart_mission']; ?>">
                </div>
                <div class="form-group">
                    <label for="heure_retour_mission">Heure de retour</label>
                    <input type="time" id="heure_retour_mission" name="heure_retour_mission"
                           onchange="calculerDuree()"
                           value="<?php echo $mission['heure_retour_mission']; ?>">
                </div>
            </div>

            <div id="dureeDisplay" class="duree-display" style="display: none;">
                ⏱️ Durée calculée : <span id="dureeText"></span>
            </div>

            <div class="button-row">
                <button type="submit" class="btn-submit">💾 Enregistrer les modifications</button>
                <button type="button" class="btn-delete" onclick="confirmerSuppression()">🗑️ Supprimer</button>
            </div>
        </form>

        <!-- Formulaire caché pour la suppression -->
        <form method="POST" action="" id="deleteForm" style="display: none;">
            <input type="hidden" name="id_mission" value="<?php echo $mission['id_mission']; ?>">
            <input type="hidden" name="action" value="delete">
        </form>

        <?php elseif (!isset($_GET['id']) || !$mission): ?>
            <?php if (!isset($_GET['id'])): ?>
            <div class="no-selection">
                <p>👆 Utilisez la recherche ci-dessus pour trouver une mission</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚠️ Confirmer la suppression</h2>
            </div>
            <div class="modal-body">
                <p><strong>Êtes-vous sûr de vouloir supprimer cette mission ?</strong></p>
                <p style="margin-top: 10px;">Cette action est irréversible.</p>
                <?php if($mission): ?>
                <p style="margin-top: 15px; color: #667eea; font-weight: 600;">
                    Mission du <?php echo date('d/m/Y', strtotime($mission['date_mission'])); ?> - 
                    <?php echo htmlspecialchars($mission['aide']); ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="fermerModal()">Annuler</button>
                <button class="modal-btn modal-btn-confirm" onclick="supprimerMission()">Supprimer</button>
            </div>
        </div>
    </div>

    <script>
        async function chargerInfosBenevole(id) {
            if (!id) {
                // Si on désélectionne le bénévole, vider les champs
                document.getElementById('benevole').value = '';
                document.getElementById('adresse_benevole').value = '';
                document.getElementById('cp_benevole').value = '';
                document.getElementById('commune_benevole').value = '';
                document.getElementById('secteur_benevole').value = '';
                document.getElementById('infos_benevole_display').style.display = 'none';
                return;
            }
            
            // Récupérer les données depuis les attributs data- de l'option sélectionnée
            const select = document.getElementById('id_benevole');
            const option = select.options[select.selectedIndex];
            
            const nom = option.dataset.nom || '';
            const adresse = option.dataset.adresse || '';
            const cp = option.dataset.cp || '';
            const commune = option.dataset.commune || '';
            const telFixe = option.dataset.telFixe || '';
            const telPortable = option.dataset.telPortable || '';
            
            try {
                const response = await fetch('get_benevole.php?id=' + id);
                const data = await response.json();
                if (data.success) {
                    document.getElementById('benevole').value = data.nom || nom;
                    document.getElementById('adresse_benevole').value = data.adresse || adresse;
                    document.getElementById('cp_benevole').value = data.code_postal || cp;
                    document.getElementById('commune_benevole').value = data.commune || commune;
                    document.getElementById('secteur_benevole').value = data.secteur || '';
                    
                    // Afficher l'adresse complète
                    const adresseAffichage = (data.adresse || adresse) + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage) {
                        document.getElementById('benevole_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    // Afficher les téléphones avec des icônes distinctes
                    let telephonesHTML = '';
                    if (telFixe) {
                        telephonesHTML = '☎️ Fixe : <strong>' + telFixe + '</strong>';
                    }
                    if (telPortable) {
                        telephonesHTML += (telFixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + telPortable + '</strong>';
                    }
                    if (telephonesHTML) {
                        document.getElementById('benevole_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_benevole_display').style.display = 'block';
                    }
                } else {
                    // Fallback: utiliser les données du data-* si l'API échoue
                    document.getElementById('benevole').value = nom;
                    
                    const adresseAffichage = adresse + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage) {
                        document.getElementById('benevole_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    let telephonesHTML = '';
                    if (telFixe) {
                        telephonesHTML = '☎️ Fixe : <strong>' + telFixe + '</strong>';
                    }
                    if (telPortable) {
                        telephonesHTML += (telFixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + telPortable + '</strong>';
                    }
                    if (telephonesHTML) {
                        document.getElementById('benevole_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_benevole_display').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                // Fallback: utiliser les données du data-* si erreur réseau
                document.getElementById('benevole').value = nom;
                
                const adresseAffichage = adresse + 
                                       (cp ? ', ' + cp : '') + 
                                       (commune ? ' ' + commune : '');
                if (adresseAffichage) {
                    document.getElementById('benevole_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                }
                
                let telephonesHTML = '';
                if (telFixe) {
                    telephonesHTML = '☎️ Fixe : <strong>' + telFixe + '</strong>';
                }
                if (telPortable) {
                    telephonesHTML += (telFixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                     '📱 Portable : <strong>' + telPortable + '</strong>';
                }
                if (telephonesHTML) {
                    document.getElementById('benevole_telephones_display').innerHTML = telephonesHTML;
                    document.getElementById('infos_benevole_display').style.display = 'block';
                }
            }
        }

        async function chargerInfosAide(id) {
            // Cacher l'affichage initial
            const initialDisplay = document.getElementById('infos_aide_initial');
            if (initialDisplay) {
                initialDisplay.style.display = 'none';
            }

            if (!id) {
                // Si on désélectionne l'aidé, vider les champs
                document.getElementById('aide').value = '';
                document.getElementById('adresse_aide').value = '';
                document.getElementById('cp_aide').value = '';
                document.getElementById('commune_aide').value = '';
                document.getElementById('secteur_aide').value = '';
                document.getElementById('infos_aide_display').style.display = 'none';
                return;
            }
            
            // Récupérer les données depuis les attributs data- de l'option sélectionnée
            const select = document.getElementById('id_aide');
            const option = select.options[select.selectedIndex];
            
            const nom = option.dataset.nom || '';
            const adresse = option.dataset.adresse || '';
            const cp = option.dataset.cp || '';
            const commune = option.dataset.commune || '';
            const telFixe = option.dataset.telFixe || '';
            const telPortable = option.dataset.telPortable || '';
            
            try {
                const response = await fetch('get_aide.php?id=' + id);
                const data = await response.json();
                if (data.success) {
                    document.getElementById('aide').value = data.nom || nom;
                    document.getElementById('adresse_aide').value = data.adresse || adresse;
                    document.getElementById('cp_aide').value = data.code_postal || cp;
                    document.getElementById('commune_aide').value = data.commune || commune;
                    document.getElementById('secteur_aide').value = data.secteur || '';
                    
                    // Afficher l'adresse complète
                    const adresseAffichage = (data.adresse || adresse) + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage.trim()) {
                        document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    // Afficher les téléphones avec des icônes distinctes
                    let telephonesHTML = '';
                    const fixe = data.tel_fixe || telFixe;
                    const portable = data.tel_portable || telPortable;
                    
                    if (fixe) {
                        telephonesHTML = '☎️ Fixe : <strong>' + fixe + '</strong>';
                    }
                    if (portable) {
                        telephonesHTML += (fixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + portable + '</strong>';
                    }
                    
                    if (telephonesHTML || adresseAffichage.trim()) {
                        document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_aide_display').style.display = 'block';
                    }
                } else {
                    // Fallback: utiliser les données du data-* si l'API échoue
                    document.getElementById('aide').value = nom;
                    
                    const adresseAffichage = adresse + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage.trim()) {
                        document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    let telephonesHTML = '';
                    if (telFixe) {
                        telephonesHTML = '☎️ Fixe : <strong>' + telFixe + '</strong>';
                    }
                    if (telPortable) {
                        telephonesHTML += (telFixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + telPortable + '</strong>';
                    }
                    
                    if (telephonesHTML || adresseAffichage.trim()) {
                        document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_aide_display').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                // Fallback: utiliser les données du data-* si erreur réseau
                document.getElementById('aide').value = nom;
                
                const adresseAffichage = adresse + 
                                       (cp ? ', ' + cp : '') + 
                                       (commune ? ' ' + commune : '');
                if (adresseAffichage.trim()) {
                    document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                }
                
                let telephonesHTML = '';
                if (telFixe) {
                    telephonesHTML = '☎️ Fixe : <strong>' + telFixe + '</strong>';
                }
                if (telPortable) {
                    telephonesHTML += (telFixe ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                     '📱 Portable : <strong>' + telPortable + '</strong>';
                }
                
                if (telephonesHTML || adresseAffichage.trim()) {
                    document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                    document.getElementById('infos_aide_display').style.display = 'block';
                }
            }
        }

        function updateCPFromVille() {
            const selectVille = document.getElementById('commune_destination');
            const inputCP = document.getElementById('cp_destination');
            const selectedOption = selectVille.options[selectVille.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const cp = selectedOption.getAttribute('data-cp');
                inputCP.value = cp || '';
            } else {
                inputCP.value = '';
            }
        }

        function calculerDuree() {
            const depart = document.getElementById('heure_depart_mission').value;
            const retour = document.getElementById('heure_retour_mission').value;
            
            if (depart && retour) {
                const [hD, mD] = depart.split(':').map(Number);
                const [hR, mR] = retour.split(':').map(Number);
                
                let minutesDepart = hD * 60 + mD;
                let minutesRetour = hR * 60 + mR;
                
                if (minutesRetour < minutesDepart) {
                    minutesRetour += 24 * 60;
                }
                
                const diffMinutes = minutesRetour - minutesDepart;
                const heures = Math.floor(diffMinutes / 60);
                const minutes = diffMinutes % 60;
                
                const dureeText = `${heures.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                document.getElementById('dureeText').textContent = dureeText;
                document.getElementById('dureeDisplay').style.display = 'block';
            } else {
                document.getElementById('dureeDisplay').style.display = 'none';
            }
        }

        function confirmerSuppression() {
            document.getElementById('confirmModal').style.display = 'block';
        }

        function fermerModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function supprimerMission() {
            document.getElementById('deleteForm').submit();
        }

        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                fermerModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fermerModal();
            }
        });

        window.addEventListener('DOMContentLoaded', function() {
            calculerDuree();
            
            // Charger les infos du bénévole si un bénévole est déjà sélectionné
            const idBenevoleSelect = document.getElementById('id_benevole');
            if (idBenevoleSelect && idBenevoleSelect.value) {
                chargerInfosBenevole(idBenevoleSelect.value);
            }
        });
    </script>
</body>
</html>