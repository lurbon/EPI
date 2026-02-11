<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'chauffeur','gestionnaire']);

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
    die(json_encode(['error' => 'Erreur de connexion : ' . $e->getMessage()]));
}

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'loadData') {
        try {
            // Fonction pour convertir date MySQL (yyyy-mm-dd) vers format français (dd/mm/yyyy)
            function convertirDateMySQLVersFr($dateMySQL) {
                if (empty($dateMySQL)) return '';
                $parts = explode('-', $dateMySQL);
                if (count($parts) !== 3) return $dateMySQL;
                return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
            }
            
            // Charger les chauffeurs
            $stmt = $conn->query("SELECT id_benevole, nom_chauffeur FROM EPI_chauffeur ORDER BY nom_chauffeur");
            $chauffeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Charger les aidés avec leurs informations complètes
            $stmt = $conn->query("SELECT id_aide, nom, adresse, code_postal, commune, tel_fixe, tel_portable 
                                  FROM EPI_aide 
                                  ORDER BY nom");
            $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Charger les inscriptions existantes
            $stmt = $conn->query("SELECT date, Activite, participant, adresse, cp, commune, 
                                         tel_fixe, tel_mobile, chauffeur 
                                  FROM EPI_minibus 
                                  ORDER BY date, Activite, participant");
            $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convertir les dates au format français
            foreach ($inscriptions as &$inscription) {
                $inscription['date'] = convertirDateMySQLVersFr($inscription['date']);
            }

            echo json_encode([
                'success' => true,
                'chauffeurs' => $chauffeurs,
                'aides' => $aides,
                'inscriptions' => $inscriptions
            ]);
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'saveMinibusGroupe') {
        try {
            $date = $input['date'] ?? '';
            $activite = $input['activite'] ?? '';
            $donnees = $input['donnees'] ?? [];
            
            // Fonction pour convertir date française (dd/mm/yyyy) vers MySQL (yyyy-mm-dd)
            function convertirDateFrVersMySQL_groupe($dateFr) {
                if (empty($dateFr)) return null;
                $parts = explode('/', $dateFr);
                if (count($parts) !== 3) return null;
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            
            $dateMySQL = convertirDateFrVersMySQL_groupe($date);
            
            // Supprimer les anciennes données pour ce groupe date/activité
            $stmtDelete = $conn->prepare("DELETE FROM EPI_minibus WHERE date = :date AND Activite = :activite");
            $stmtDelete->execute([':date' => $dateMySQL, ':activite' => $activite]);
            
            // Préparer la requête d'insertion
            $stmt = $conn->prepare("INSERT INTO EPI_minibus 
                (date, Activite, participant, adresse, cp, commune, tel_fixe, tel_mobile, chauffeur) 
                VALUES (:date, :activite, :participant, :adresse, :cp, :commune, :tel_fixe, :tel_mobile, :chauffeur)");
            
            $inserted = 0;
            foreach ($donnees as $ligne) {
                // Insérer si un participant est sélectionné OU si un chauffeur est sélectionné (même sans participant)
                if (!empty($ligne['participants']) || !empty($ligne['chauffeur'])) {
                    $stmt->execute([
                        ':date' => $dateMySQL,
                        ':activite' => $activite,
                        ':participant' => $ligne['participants'] ?? '',
                        ':adresse' => $ligne['adresse'] ?? '',
                        ':cp' => $ligne['cp'] ?? '',
                        ':commune' => $ligne['ville'] ?? '',
                        ':tel_fixe' => $ligne['telFixe'] ?? '',
                        ':tel_mobile' => $ligne['telMobile'] ?? '',
                        ':chauffeur' => $ligne['chauffeur'] ?? ''
                    ]);
                    $inserted++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "$inserted inscription(s) enregistrée(s) pour $date - $activite"
            ]);
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Note: La fonction 'saveMinibusData' a été supprimée car elle n'était pas utilisée
    // et présentait un risque (DELETE FROM EPI_minibus sans WHERE)
}


// Si ce n'est pas une requête AJAX, afficher le formulaire HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier Minibus 2026</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            text-align: center;
            color: #1a1a1a;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .legend-text {
            font-weight: 500;
            color: #333;
            font-size: 0.75rem;
        }
        
        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }
        
        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 8px 10px;
            text-align: left;
            font-size: 0.7rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        
        th:first-child {
            border-top-left-radius: 16px;
        }
        
        th:last-child {
            border-top-right-radius: 16px;
        }
        
        td {
            padding: 6px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .date-col {
            width: 110px;
            font-weight: 600;
            font-size: 0.7rem;
            vertical-align: top;
            padding-top: 10px;
        }
        
        .jour-col {
            width: 90px;
            font-weight: 600;
            font-size: 0.7rem;
        }
        
        .activity-col {
            width: 70px;
            font-weight: 500;
            font-size: 0.65rem;
            line-height: 1.2;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .name-cell {
            width: 180px;
        }
        
        .chauffeur-col {
            width: 180px;
            vertical-align: top;
            padding-top: 10px;
        }
        
        .action-col {
            width: 100px;
            text-align: center;
        }
        
        .btn-save-group {
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            width: 100%;
        }
        
        .btn-save-group:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-save-group:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-print-group {
            padding: 6px 12px;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            width: 100%;
            margin-top: 8px;
        }
        
        .btn-print-group:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
        }
        
        /* Styles pour l'impression */
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            
            body > *:not(#print-area) {
                display: none !important;
            }
            
            #print-area {
                display: block !important;
                position: relative;
                width: 100%;
                page-break-after: avoid;
            }
            
            .print-header {
                text-align: left;
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
            
            .print-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 3px;
            }
            
            .print-subtitle {
                font-size: 14px;
                color: #333;
                margin-bottom: 3px;
            }
            
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            
            .print-table td {
                border: 1px solid #333;
                padding: 8px;
                text-align: left;
                font-size: 11px;
                line-height: 1.4;
                page-break-inside: avoid;
            }
            
            .print-chauffeur {
                margin-top: 15px;
                padding: 8px;
                border: 2px solid #333;
                font-size: 13px;
                font-weight: bold;
                text-align: left;
                page-break-inside: avoid;
            }
        }
        
        .name-cell {
            width: 100px;
        }
        
        .chauffeur-col {
            width: 100px;
        }
        
        .adresse-col {
            width: 200px;
        }
        
        .adresse-complete {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .adresse-complete input {
            margin: 0;
        }
        
        .adresse-ligne1 {
            font-size: 0.7rem;
        }
        
        .adresse-ligne2 {
            display: flex;
            gap: 4px;
        }
        
        .adresse-ligne2 input:first-child {
            width: 60px;
            flex-shrink: 0;
        }
        
        .adresse-ligne2 input:last-child {
            flex: 1;
        }
        
        .tel-fixe-col {
            width: 100px;
        }
        
        .tel-mobile-col {
            width: 100px;
        }
        
        .mardi {
            background: linear-gradient(135deg, #e3f2fd 0%, #90caf9 100%); /* Bleu clair */
        }
        
        .jeudi {
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); /* Rosé */
        }
        
        .vendredi {
            background: linear-gradient(135deg, #fff9c4 0%, #fff176 100%); /* Jaune vif */
        }
        
        .mercredi {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); /* Violet pastel */
        }
        
        .event-group {
            border-bottom: 3px solid #667eea !important;
        }
        
        input {
            width: 100%;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.8);
            padding: 4px 6px;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        input::placeholder {
            color: #999;
        }
        
        input:hover {
            background: rgba(255, 255, 255, 1);
            border-color: #e0e0e0;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input:read-only {
            background: #f5f5f5;
            cursor: not-allowed;
            border-color: transparent;
        }
        
        input:read-only:hover {
            background: #f5f5f5;
            border-color: transparent;
        }
        
        select {
            width: 100%;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.8);
            padding: 4px 6px;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        select:hover {
            background: rgba(255, 255, 255, 1);
            border-color: #e0e0e0;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .error-duplicate {
            background-color: #ffebee !important;
            border: 3px solid #f44336 !important;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Bouton flottant retour dashboard */
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
            border: none;
            cursor: pointer;
        }

        .back-link:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.7);
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

        /* Responsive mobile */
        @media (max-width: 768px) {
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
    <!-- Bouton flottant retour dashboard -->
    <button onclick="window.location.href='dashboard.php'" class="back-link" title="Retour au tableau de bord">🏠</button>

    <div class="container">
        <h1>🚌 Calendrier Minibus 2026</h1>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color mardi"></div>
                <span class="legend-text">Mardi - Jardin des saveurs</span>
            </div>
            <div class="legend-item">
                <div class="legend-color jeudi"></div>
                <span class="legend-text">Jeudi - Grandes surfaces</span>
            </div>
            <div class="legend-item">
                <div class="legend-color vendredi"></div>
                <span class="legend-text">Vendredi - Cinéma St-Renan</span>
            </div>
            <div class="legend-item">
                <span class="legend-text vacances">🎒 Vacances scolaires</span>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date / Activité</th>
                        <th>Participants</th>
                        <th>Adresse complète</th>
                        <th>Tél fixe</th>
                        <th>Tél mobile</th>
                        <th>Chauffeur</th>
                    </tr>
                </thead>
                <tbody id="calendar-body">
                    <!-- Le contenu sera généré par JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Zone d'impression -->
    <div id="print-area"></div>

    <style>
        @media screen {
            #print-area {
                display: none;
            }
        }
    </style>

    <script>
        // Variables globales
        let listeChauffeurs = [];
        let listeAides = [];
        let aidesMap = new Map(); // Pour accès rapide aux infos des aidés
        
        // Calendrier 2026 avec activités
        const calendrier2026 = [
            // Janvier
            { date: '09/01/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '13/01/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '15/01/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '16/01/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '20/01/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '22/01/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '23/01/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '27/01/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '29/01/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '30/01/2026', jour: 'Vendredi', activite: 'Cinéma' },
            
            // Février
            { date: '03/02/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '05/02/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '06/02/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '10/02/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '12/02/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '13/02/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '17/02/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '19/02/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '20/02/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '24/02/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '26/02/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '27/02/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            
            // Mars
            { date: '03/03/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '05/03/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '06/03/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '10/03/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '12/03/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '13/03/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '17/03/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '19/03/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '20/03/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '24/03/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '26/03/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '27/03/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '31/03/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '02/04/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            
            // Avril
            { date: '03/04/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '07/04/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '09/04/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '10/04/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '14/04/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '16/04/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '17/04/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '21/04/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '23/04/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '24/04/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '28/04/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '30/04/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            
            // Mai
            { date: '01/05/2026', jour: 'Vendredi', activite: 'Férié' },
            { date: '05/05/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '07/05/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '08/05/2026', jour: 'Vendredi', activite: 'Férié' },
            { date: '12/05/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '14/05/2026', jour: 'Jeudi', activite: 'Férié' },
            { date: '15/05/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '19/05/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '21/05/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '22/05/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '25/05/2026', jour: 'Lundi', activite: 'Férié' },
            { date: '26/05/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '28/05/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '29/05/2026', jour: 'Vendredi', activite: 'Cinéma' },
            
            // Juin
            { date: '02/06/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '04/06/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '05/06/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '09/06/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '11/06/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '12/06/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '16/06/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '18/06/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '19/06/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '23/06/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '25/06/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '26/06/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '30/06/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            
            // Juillet
            { date: '02/07/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '03/07/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '07/07/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '09/07/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '10/07/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '14/07/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '16/07/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '17/07/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '21/07/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '23/07/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '24/07/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '28/07/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '30/07/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '31/07/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            
            // Août
            { date: '04/08/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '06/08/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '07/08/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '11/08/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '13/08/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '14/08/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '18/08/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '20/08/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '21/08/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '25/08/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '27/08/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '28/08/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            
            // Septembre
            { date: '01/09/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '03/09/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '04/09/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '08/09/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '10/09/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '11/09/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '15/09/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '17/09/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '18/09/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '22/09/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '24/09/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '25/09/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '29/09/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            
            // Octobre
            { date: '01/10/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '02/10/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '06/10/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '08/10/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '09/10/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '13/10/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '15/10/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '16/10/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '20/10/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '22/10/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '23/10/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '27/10/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '29/10/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '30/10/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            
            // Novembre
            { date: '03/11/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '05/11/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '06/11/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '10/11/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '11/11/2026', jour: 'Mercredi', activite: 'Férié' },
            { date: '12/11/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '13/11/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '17/11/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '19/11/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '20/11/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '24/11/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '26/11/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '27/11/2026', jour: 'Vendredi', activite: 'Cinéma' },
            
            // Décembre
            { date: '01/12/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '03/12/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '04/12/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '08/12/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '10/12/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '11/12/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '15/12/2026', jour: 'Mardi', activite: 'Jardin des saveurs' },
            { date: '17/12/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces' },
            { date: '18/12/2026', jour: 'Vendredi', activite: 'Cinéma' },
            { date: '22/12/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '24/12/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true },
            { date: '25/12/2026', jour: 'Vendredi', activite: 'Cinéma', vacances: true },
            { date: '29/12/2026', jour: 'Mardi', activite: 'Jardin des saveurs', vacances: true },
            { date: '31/12/2026', jour: 'Jeudi', activite: 'Courses grandes surfaces', vacances: true }
        ];
        
        // Charger les données depuis la base
        async function chargerDonnees() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action: 'loadData' })
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                listeChauffeurs = data.chauffeurs || [];
                listeAides = data.aides || [];
                
                // Créer une map pour accès rapide aux infos des aidés
                listeAides.forEach(aide => {
                    aidesMap.set(aide.nom, {
                        adresse: aide.adresse || '',
                        cp: aide.code_postal || '',
                        ville: aide.commune || '',
                        telFixe: aide.tel_fixe || '',
                        telMobile: aide.tel_portable || ''
                    });
                });
                
                console.log('✅ Données chargées:', listeChauffeurs.length, 'chauffeurs,', listeAides.length, 'aidés');
                
                genererCalendrier();
                
                // Injecter les inscriptions existantes
                if (data.inscriptions && data.inscriptions.length > 0) {
                    injecterInscriptions(data.inscriptions);
                }
                
            } catch (error) {
                console.error('❌ Erreur:', error);
                afficherMessage('❌ Erreur de chargement: ' + error.message, 'error');
            }
        }
        
        function genererCalendrier() {
            const tbody = document.getElementById('calendar-body');
            tbody.innerHTML = '';
            
            // Filtrer le calendrier : afficher seulement 2,5 mois à partir d'aujourd'hui
            //const aujourdhui = new Date();
			const hier = new Date();
			hier.setDate(hier.getDate() - 1);
            const dans2moisEtDemi = new Date();
            dans2moisEtDemi.setMonth(dans2moisEtDemi.getMonth() + 1);
            dans2moisEtDemi.setDate(dans2moisEtDemi.getDate() + 15); // Ajouter 15 jours pour faire 2,5 mois
            
            const calendrierFiltre = calendrier2026.filter(event => {
                // Convertir la date dd/mm/yyyy en objet Date
                const [jour, mois, annee] = event.date.split('/');
                const dateEvent = new Date(annee, mois - 1, jour);
                
                // Masquer les vendredis pendant les vacances
                if (event.vacances && event.jour === 'Vendredi') {
                    return false;
                }
                
                // Garder seulement les événements entre aujourd'hui et dans 2,5 mois
                return dateEvent >= hier && dateEvent <= dans2moisEtDemi;
            });
            
            console.log(`📅 Affichage de ${calendrierFiltre.length} événements (du ${hier.toLocaleDateString('fr-FR')} au ${dans2moisEtDemi.toLocaleDateString('fr-FR')})`);
            
            calendrierFiltre.forEach((event, index) => {
                const classeJour = event.jour.toLowerCase();
                const estVacances = event.vacances || false;
                
                // Créer 8 lignes par événement (1 chauffeur + 8 participants max)
                for (let i = 0; i < 8; i++) {
                    const tr = document.createElement('tr');
                    tr.dataset.date = event.date;
                    tr.dataset.activite = event.activite;
                    
                    // Appliquer la couleur selon le jour
                    if (classeJour === 'mardi') tr.classList.add('mardi');
                    if (classeJour === 'mercredi') tr.classList.add('mercredi');
                    if (classeJour === 'jeudi') tr.classList.add('jeudi');
                    if (classeJour === 'vendredi') tr.classList.add('vendredi');
                    
                    // Marquer les lignes de vacances
                    if (estVacances) {
                        tr.classList.add('ligne-vacances');
                    }
                    
                    // Marquer la dernière ligne de chaque groupe
                    if (i === 7) {
                        tr.classList.add('event-group');
                    }
                    
                    // Date et Activité dans la même cellule (seulement sur la première ligne)
                    if (i === 0) {
                        const tdDate = document.createElement('td');
                        tdDate.className = 'date-col';
                        tdDate.rowSpan = 8;
                        
                        // Créer un conteneur pour date + activité
                        const containerDate = document.createElement('div');
                        containerDate.style.display = 'flex';
                        containerDate.style.flexDirection = 'column';
                        containerDate.style.gap = '4px';
                        
                        // Date en haut avec jour de la semaine
                        const divDate = document.createElement('div');
                        divDate.style.fontWeight = '600';
                        divDate.style.fontSize = '0.7rem';
                        divDate.style.paddingBottom = '4px';
                        divDate.style.borderBottom = '1px solid #ccc';
                        divDate.textContent = event.jour + ' ' + event.date; // Ajouter le jour devant la date
                        containerDate.appendChild(divDate);
                        
                        // Activité en bas avec icône
                        const divActivite = document.createElement('div');
                        divActivite.style.fontSize = '0.65rem';
                        divActivite.style.lineHeight = '1.2';
                        
                        let activiteAffichee = event.activite;
                        let icone = '';
                        
                        if (event.activite === 'Courses grandes surfaces') {
                            activiteAffichee = 'Grandes surfaces';
                            icone = '🛒 ';
                        } else if (event.activite === 'Jardin des saveurs') {
                            activiteAffichee = 'Jardin des saveurs';
                            icone = '🌿 ';
                        } else if (event.activite === 'Cinéma') {
                            activiteAffichee = 'Cinéma St-Renan';
                            icone = '🎬 ';
                        }
                        
                        divActivite.textContent = icone + activiteAffichee;
                        containerDate.appendChild(divActivite);
                        
                        // Si vacances, ajouter une ligne "🎒 Vacances" en dessous
                        if (estVacances) {
                            const divVacances = document.createElement('div');
                            divVacances.style.fontSize = '0.6rem';
                            divVacances.style.lineHeight = '1.2';
                            divVacances.style.paddingTop = '4px';
                            divVacances.style.fontStyle = 'italic';
                            divVacances.style.color = '#666';
                            divVacances.textContent = '🎒 Vacances';
                            containerDate.appendChild(divVacances);
                        }
                        
                        tdDate.appendChild(containerDate);
                        tr.appendChild(tdDate);
                    }
                    
                    // Liste déroulante des participants
                    const tdNom = document.createElement('td');
                    tdNom.className = 'name-cell';
                    const selectParticipant = document.createElement('select');
                    selectParticipant.className = 'participant-select';
                    
                    const optionVide = document.createElement('option');
                    optionVide.value = '';
                    optionVide.textContent = '-- Sélectionner --';
                    selectParticipant.appendChild(optionVide);
                    
                    listeAides.forEach(aide => {
                        const option = document.createElement('option');
                        option.value = aide.nom;
                        option.textContent = aide.nom;
                        selectParticipant.appendChild(option);
                    });
                    
                    // Remplir automatiquement les champs lors de la sélection
                    selectParticipant.addEventListener('change', function() {
                        const nomAide = this.value;
                        remplirInfosAide(tr, nomAide);
                        verifierDoublons(event.date, event.activite);
                    });
                    
                    tdNom.appendChild(selectParticipant);
                    tr.appendChild(tdNom);
                    
                    // Adresse complète (adresse + CP + ville sur 2 lignes) - EN LECTURE SEULE
                    const tdAdresse = document.createElement('td');
                    tdAdresse.className = 'adresse-col';
                    
                    const divAdresse = document.createElement('div');
                    divAdresse.className = 'adresse-complete';
                    
                    // Ligne 1 : Adresse (lecture seule)
                    const inputAdresse = document.createElement('input');
                    inputAdresse.type = 'text';
                    inputAdresse.placeholder = 'Adresse';
                    inputAdresse.className = 'adresse-ligne1';
                    inputAdresse.readOnly = true;
                    divAdresse.appendChild(inputAdresse);
                    
                    // Ligne 2 : CP + Ville (lecture seule)
                    const divLigne2 = document.createElement('div');
                    divLigne2.className = 'adresse-ligne2';
                    
                    const inputCP = document.createElement('input');
                    inputCP.type = 'text';
                    inputCP.placeholder = 'CP';
                    inputCP.maxLength = 5;
                    inputCP.readOnly = true;
                    divLigne2.appendChild(inputCP);
                    
                    const inputVille = document.createElement('input');
                    inputVille.type = 'text';
                    inputVille.placeholder = 'Commune';
                    inputVille.readOnly = true;
                    divLigne2.appendChild(inputVille);
                    
                    divAdresse.appendChild(divLigne2);
                    tdAdresse.appendChild(divAdresse);
                    tr.appendChild(tdAdresse);
                    
                    // Tél fixe (lecture seule)
                    const tdTelFixe = document.createElement('td');
                    tdTelFixe.className = 'tel-fixe-col';
                    const inputTelFixe = document.createElement('input');
                    inputTelFixe.type = 'tel';
                    inputTelFixe.placeholder = 'Tél fixe';
                    inputTelFixe.readOnly = true;
                    tdTelFixe.appendChild(inputTelFixe);
                    tr.appendChild(tdTelFixe);
                    
                    // Tél mobile (lecture seule)
                    const tdTelMobile = document.createElement('td');
                    tdTelMobile.className = 'tel-mobile-col';
                    const inputTelMobile = document.createElement('input');
                    inputTelMobile.type = 'tel';
                    inputTelMobile.placeholder = 'Tél mobile';
                    inputTelMobile.readOnly = true;
                    tdTelMobile.appendChild(inputTelMobile);
                    tr.appendChild(tdTelMobile);
                    
                    // Chauffeur (seulement sur la première ligne)
                    if (i === 0) {
                        const tdChauffeur = document.createElement('td');
                        tdChauffeur.className = 'chauffeur-col';
                        tdChauffeur.rowSpan = 8;
                        
                        // Créer un conteneur pour le select et le bouton
                        const containerChauffeur = document.createElement('div');
                        containerChauffeur.style.display = 'flex';
                        containerChauffeur.style.flexDirection = 'column';
                        containerChauffeur.style.gap = '20px'; // Augmenté de 8px à 20px
                        
                        const selectChauffeur = document.createElement('select');
                        selectChauffeur.className = 'chauffeur-select';
                        
                        const optionVideChauffeur = document.createElement('option');
                        optionVideChauffeur.value = '';
                        optionVideChauffeur.textContent = '-- Chauffeur --';
                        selectChauffeur.appendChild(optionVideChauffeur);
                        
                        listeChauffeurs.forEach(chauffeur => {
                            const option = document.createElement('option');
                            option.value = chauffeur.nom_chauffeur;
                            option.textContent = chauffeur.nom_chauffeur;
                            selectChauffeur.appendChild(option);
                        });
                        
                        containerChauffeur.appendChild(selectChauffeur);
                        
                        // Bouton Enregistrer sous le select
                        const btnSave = document.createElement('button');
                        btnSave.className = 'btn-save-group';
                        btnSave.textContent = '💾 Enregistrer';
                        btnSave.dataset.date = event.date;
                        btnSave.dataset.activite = event.activite;
                        btnSave.addEventListener('click', function() {
                            sauvegarderGroupe(event.date, event.activite, this);
                        });
                        
                        containerChauffeur.appendChild(btnSave);
                        
                        // Bouton Imprimer sous le bouton Enregistrer
                        const btnPrint = document.createElement('button');
                        btnPrint.className = 'btn-print-group';
                        btnPrint.textContent = '🖨️ Imprimer';
                        btnPrint.dataset.date = event.date;
                        btnPrint.dataset.activite = event.activite;
                        btnPrint.dataset.jour = event.jour;
                        btnPrint.addEventListener('click', function() {
                            imprimerParticipants(event.date, event.activite, event.jour, selectChauffeur.value);
                        });
                        
                        containerChauffeur.appendChild(btnPrint);
                        tdChauffeur.appendChild(containerChauffeur);
                        tr.appendChild(tdChauffeur);
                    }
                    
                    tbody.appendChild(tr);
                }
            });
        }
        
        function remplirInfosAide(tr, nomAide) {
            if (!nomAide) {
                // Vider les champs si aucun aidé sélectionné
                tr.querySelector('.adresse-ligne1').value = '';
                tr.querySelector('.adresse-ligne2 input:first-child').value = '';
                tr.querySelector('.adresse-ligne2 input:last-child').value = '';
                tr.querySelector('.tel-fixe-col input').value = '';
                tr.querySelector('.tel-mobile-col input').value = '';
                return;
            }
            
            const infos = aidesMap.get(nomAide);
            if (infos) {
                tr.querySelector('.adresse-ligne1').value = infos.adresse;
                tr.querySelector('.adresse-ligne2 input:first-child').value = infos.cp;
                tr.querySelector('.adresse-ligne2 input:last-child').value = infos.ville;
                tr.querySelector('.tel-fixe-col input').value = infos.telFixe;
                tr.querySelector('.tel-mobile-col input').value = infos.telMobile;
            }
        }
        
        function verifierDoublons(date, activite) {
            const rows = document.querySelectorAll(`tr[data-date="${date}"][data-activite="${activite}"]`);
            const participants = new Map();
            let hasDoublon = false;
            
            rows.forEach(row => {
                const select = row.querySelector('.participant-select');
                const nomParticipant = select.value;
                
                // Retirer le style d'erreur
                select.classList.remove('error-duplicate');
                select.style.border = '';
                
                if (nomParticipant) {
                    if (participants.has(nomParticipant)) {
                        // Doublon détecté
                        hasDoublon = true;
                        select.classList.add('error-duplicate');
                        select.style.border = '3px solid #f44336';
                        participants.get(nomParticipant).classList.add('error-duplicate');
                        participants.get(nomParticipant).style.border = '3px solid #f44336';
                    } else {
                        participants.set(nomParticipant, select);
                    }
                }
            });
            
            return hasDoublon;
        }
        
        function injecterInscriptions(inscriptions) {
            console.log('📥 Injection de', inscriptions.length, 'inscriptions');
            
            // Grouper les inscriptions par date||activite
            const groupes = {};
            inscriptions.forEach(inscription => {
                const key = `${inscription.date}||${inscription.Activite}`;
                if (!groupes[key]) {
                    groupes[key] = [];
                }
                groupes[key].push(inscription);
            });
            
            // Injecter chaque groupe
            Object.keys(groupes).forEach(key => {
                const [date, activite] = key.split('||');
                const lignesData = groupes[key];
                const rows = document.querySelectorAll(`tr[data-date="${date}"][data-activite="${activite}"]`);
                
                lignesData.forEach((ligne, index) => {
                    if (index >= rows.length) return;
                    
                    const tr = rows[index];
                    
                    // Sélectionner le participant
                    const participantSelect = tr.querySelector('.participant-select');
                    if (participantSelect && ligne.participant) {
                        participantSelect.value = ligne.participant;
                        participantSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    
                    // Remplir les autres champs (ils sont déjà remplis par le change event, mais on force au cas où)
                    if (ligne.adresse) tr.querySelector('.adresse-ligne1').value = ligne.adresse;
                    if (ligne.cp) tr.querySelector('.adresse-ligne2 input:first-child').value = ligne.cp;
                    if (ligne.commune) tr.querySelector('.adresse-ligne2 input:last-child').value = ligne.commune;
                    if (ligne.tel_fixe) tr.querySelector('.tel-fixe-col input').value = ligne.tel_fixe;
                    if (ligne.tel_mobile) tr.querySelector('.tel-mobile-col input').value = ligne.tel_mobile;
                    
                    // Sélectionner le chauffeur (seulement sur la première ligne)
                    if (index === 0) {
                        const chauffeurSelect = tr.querySelector('.chauffeur-select');
                        if (chauffeurSelect && ligne.chauffeur) {
                            chauffeurSelect.value = ligne.chauffeur;
                        }
                    }
                });
            });
            
            console.log('✅ Inscriptions injectées');
        }
        
        function collecterDonneesGroupe(date, activite) {
            const donnees = [];
            const rows = document.querySelectorAll(`tr[data-date="${date}"][data-activite="${activite}"]`);
            
            let chauffeur = '';
            let ligneAvecParticipant = false;
            
            rows.forEach((row, index) => {
                // Récupérer le chauffeur (première ligne)
                const chauffeurCell = row.querySelector('.chauffeur-col select');
                if (chauffeurCell) {
                    chauffeur = chauffeurCell.value;
                }
                
                const participantSelect = row.querySelector('.participant-select');
                const participant = participantSelect ? participantSelect.value : '';
                
                // Ajouter si un participant est sélectionné
                if (participant) {
                    donnees.push({
                        date: date,
                        activite: activite,
                        participants: participant,
                        adresse: row.querySelector('.adresse-ligne1')?.value || '',
                        cp: row.querySelector('.adresse-ligne2 input:first-child')?.value || '',
                        ville: row.querySelector('.adresse-ligne2 input:last-child')?.value || '',
                        telFixe: row.querySelector('.tel-fixe-col input')?.value || '',
                        telMobile: row.querySelector('.tel-mobile-col input')?.value || '',
                        chauffeur: chauffeur
                    });
                    ligneAvecParticipant = true;
                }
            });
            
            // Si chauffeur mais aucun participant, ajouter quand même
            if (chauffeur && !ligneAvecParticipant) {
                donnees.push({
                    date: date,
                    activite: activite,
                    participants: '',
                    adresse: '',
                    cp: '',
                    ville: '',
                    telFixe: '',
                    telMobile: '',
                    chauffeur: chauffeur
                });
            }
            
            return donnees;
        }
        
        async function sauvegarderGroupe(date, activite, button) {
            // Vérifier les doublons avant de sauvegarder
            const hasDoublon = verifierDoublons(date, activite);
            if (hasDoublon) {
                afficherMessage('❌ Erreur : Un participant ne peut s\'inscrire qu\'une seule fois à cette activité', 'error');
                return;
            }
            
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '⏳ En cours...';
            
            try {
                const donnees = collecterDonneesGroupe(date, activite);
                
                console.log('📦 Envoi de', donnees.length, 'lignes pour', date, activite);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'saveMinibusGroupe',
                        date: date,
                        activite: activite,
                        donnees: donnees
                    })
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                console.log('✅ Sauvegarde réussie');
                afficherMessage('✅ ' + data.message, 'success');
                
            } catch (error) {
                console.error('❌ Erreur:', error);
                afficherMessage('❌ ' + error.message, 'error');
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
        
        function imprimerParticipants(date, activite, jour, chauffeur) {
            // Collecter les participants pour cette activité
            const rows = document.querySelectorAll(`tr[data-date="${date}"][data-activite="${activite}"]`);
            const participants = [];
            
            rows.forEach(row => {
                const participantSelect = row.querySelector('.participant-select');
                const participant = participantSelect ? participantSelect.value : '';
                
                if (participant) {
                    participants.push({
                        nom: participant,
                        adresse: row.querySelector('.adresse-ligne1')?.value || '',
                        cp: row.querySelector('.adresse-ligne2 input:first-child')?.value || '',
                        ville: row.querySelector('.adresse-ligne2 input:last-child')?.value || '',
                        telFixe: row.querySelector('.tel-fixe-col input')?.value || '',
                        telMobile: row.querySelector('.tel-mobile-col input')?.value || ''
                    });
                }
            });
            
            if (participants.length === 0) {
                afficherMessage('⚠️ Aucun participant à imprimer pour cette activité', 'warning');
                return;
            }
            
            // Créer le contenu d'impression
            const printArea = document.getElementById('print-area');
            
            // Déterminer l'icône selon l'activité
            let icone = '';
            let activiteAffichee = activite;
            if (activite === 'Courses grandes surfaces') {
                activiteAffichee = 'Grandes surfaces';
                icone = '🛒';
            } else if (activite === 'Jardin des saveurs') {
                activiteAffichee = 'Jardin des saveurs';
                icone = '🌿';
            } else if (activite === 'Cinéma') {
                activiteAffichee = 'Cinéma St-Renan';
                icone = '🎬';
            }
            
            let html = `
                <div class="print-header">
                    <div class="print-title">${jour} ${date}</div>
                    <div class="print-subtitle">${icone} ${activiteAffichee}</div>
                </div>
            `;
            
            html += `
                <table class="print-table">
                    <tbody>
            `;
            
            participants.forEach((p, index) => {
                html += `
                    <tr>
                        <td style="width: 200px; vertical-align: top;"><strong>${p.nom}</strong></td>
                        <td style="vertical-align: top;">
                            ${p.adresse}<br>
                            ${p.cp} ${p.ville}<br>
                            ${p.telFixe ? 'Fixe : ' + p.telFixe : ''}${p.telFixe && p.telMobile ? '<br>' : ''}${p.telMobile ? 'Mobile : ' + p.telMobile : ''}
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            if (chauffeur) {
                html += `<div class="print-chauffeur">Chauffeur : ${chauffeur}</div>`;
            }
            
            printArea.innerHTML = html;
            
            // Lancer l'impression
            window.print();
        }
        
        function afficherMessage(message, type) {
            const existingMessage = document.querySelector('.success-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'success-message';
            messageDiv.textContent = message;
            
            if (type === 'error') {
                messageDiv.style.background = '#f44336';
            } else if (type === 'warning') {
                messageDiv.style.background = '#ff9800';
            }
            
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
        }
        
        // Initialisation
        window.addEventListener('DOMContentLoaded', async () => {
            await chargerDonnees();
        });
    </script>
</body>
</html>
