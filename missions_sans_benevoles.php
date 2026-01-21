<?php
// Charger la configuration WordPress
require_once('wp-config.php');
require_once('auth.php');
verifierRole(['admin']);

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

// Connexion PDO
try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonction pour générer un token sécurisé
function generateSecureToken($missionId, $benevoleEmail) {
    $secretKey = 'VOTRE_CLE_SECRETE_A_CHANGER'; // À personnaliser !
    return hash('sha256', $missionId . '|' . $benevoleEmail . '|' . $secretKey);
}

// Fonction pour formater les dates
function formatDate($date) {
    if (empty($date)) return 'Non précisée';
    $timestamp = strtotime($date);
    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
    return strftime('%d/%m/%Y', $timestamp);
}

// Fonction pour formater les dates en français
function formatDateLong($date) {
    if (empty($date)) return 'Non précisée';
    $timestamp = strtotime($date);
    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = date('w', $timestamp);
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp);
    return $jours[$j] . ' ' . $d . ' ' . $mois[$m] . ' ' . $y;
}

// Fonction pour formater les téléphones
function formatPhone($phone) {
    if (empty($phone)) return '';
    $cleaned = preg_replace('/\s+/', '', $phone);
    if (strlen($cleaned) == 10) {
        return chunk_split($cleaned, 2, ' ');
    }
    return $phone;
}

// Traitement de l'envoi d'email
$emailSent = false;
$emailError = false;
$emailCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $secteur = $_POST['secteur'];
    $subject = $_POST['subject'];
    $selectedBenevoles = isset($_POST['benevoles']) ? $_POST['benevoles'] : [];
    
    if (count($selectedBenevoles) > 0) {
        try {
            // Récupérer les missions du secteur
            $sqlMissions = "SELECT 
                                m.id_mission,
                                m.date_mission,
                                m.heure_rdv,
                                m.nature_intervention,
                                m.adresse_destination,
                                m.commune_destination,
                                m.commentaires,
                                a.nom as aide_nom,
                                a.adresse,
                                a.commune,
                                a.tel_fixe,
                                a.tel_portable
                            FROM EPI_mission m
                            INNER JOIN EPI_aide a ON m.id_aide = a.id_aide
                            WHERE a.secteur = :secteur 
                            AND (m.id_benevole IS NULL OR m.id_benevole = 0)
                            ORDER BY m.date_mission, m.heure_rdv";
            
            $stmtMissions = $conn->prepare($sqlMissions);
            $stmtMissions->execute(['secteur' => $secteur]);
            $missions = $stmtMissions->fetchAll(PDO::FETCH_ASSOC);
            
            $headers = "From: noreply@votre-association.fr\r\n";
            $headers .= "Reply-To: contact@votre-association.fr\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            // URL de base pour les inscriptions (à adapter)
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            
            foreach ($selectedBenevoles as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                
                // Construire le corps de l'email avec les missions
                $missionsHtml = '';
                foreach ($missions as $index => $mission) {
                    $token = generateSecureToken($mission['id_mission'], $email);
                    $inscriptionUrl = $baseUrl . '/inscrire_mission.php?mission=' . $mission['id_mission'] . 
                                     '&email=' . urlencode($email) . '&token=' . $token;
                    
                    $missionsHtml .= '
                    <div style="background: #ffffff; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 20px; border-radius: 8px;">
                        <h3 style="color: #667eea; margin-top: 0;">Mission #' . ($index + 1) . ' - ' . formatDateLong($mission['date_mission']) . '</h3>
                        
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;">
                            <strong style="font-size: 18px;">👤 ' . htmlspecialchars($mission['aide_nom']) . '</strong>
                        </div>
                        
                        <p style="margin: 10px 0;"><strong>📅 Date et heure :</strong> ' . formatDate($mission['date_mission']) . ' à ' . 
                        (!empty($mission['heure_rdv']) ? $mission['heure_rdv'] : 'Heure non précisée') . '</p>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0;">
                            <p style="margin: 5px 0;"><strong>📍 Adresse départ :</strong><br>' . 
                            htmlspecialchars($mission['adresse']) . '<br>' . htmlspecialchars($mission['commune']) . '</p>
                        </div>';
                    
                    if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])) {
                        $missionsHtml .= '
                        <div style="background: #fff3cd; padding: 12px; border-radius: 6px; border-left: 3px solid #ffc107; margin: 10px 0;">
                            <p style="margin: 5px 0;"><strong>🎯 Destination :</strong><br>';
                        if (!empty($mission['adresse_destination'])) {
                            $missionsHtml .= htmlspecialchars($mission['adresse_destination']) . '<br>';
                        }
                        if (!empty($mission['commune_destination'])) {
                            $missionsHtml .= htmlspecialchars($mission['commune_destination']);
                        }
                        $missionsHtml .= '</p></div>';
                    }
                    
                    if (!empty($mission['nature_intervention'])) {
                        $missionsHtml .= '<p style="margin: 10px 0;"><strong>📖 Nature :</strong> ' . 
                        htmlspecialchars($mission['nature_intervention']) . '</p>';
                    }
                    
                    if (!empty($mission['commentaires'])) {
                        $missionsHtml .= '<p style="margin: 10px 0;"><strong>💬 Commentaires :</strong> ' . 
                        htmlspecialchars($mission['commentaires']) . '</p>';
                    }
                    
                    $missionsHtml .= '
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0;">
                            <p style="margin: 5px 0;"><strong>📞 Contact :</strong><br>';
                    if (!empty($mission['tel_fixe'])) {
                        $missionsHtml .= 'Fixe: ' . formatPhone($mission['tel_fixe']) . '<br>';
                    }
                    if (!empty($mission['tel_portable'])) {
                        $missionsHtml .= 'Mobile: ' . formatPhone($mission['tel_portable']);
                    }
                    if (empty($mission['tel_fixe']) && empty($mission['tel_portable'])) {
                        $missionsHtml .= 'Non renseigné';
                    }
                    $missionsHtml .= '</p></div>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="' . $inscriptionUrl . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">
                                ✅ Je m\'inscris à cette mission
                            </a>
                        </div>
                    </div>';
                }
                
                $htmlMessage = '
                <!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;">
                    <div style="max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                        
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; color: white; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">🔔 Nouvelles Missions</h1>
                            <p style="margin: 10px 0 0 0; font-size: 16px;">Secteur : ' . htmlspecialchars($secteur) . '</p>
                        </div>
                        
                        <div style="padding: 30px;">
                            <p style="font-size: 16px; color: #333; line-height: 1.6;">Bonjour,</p>
                            
                            <p style="font-size: 16px; color: #333; line-height: 1.6;">
                                ' . count($missions) . ' nouvelle' . (count($missions) > 1 ? 's' : '') . ' mission' . (count($missions) > 1 ? 's' : '') . ' 
                                ' . (count($missions) > 1 ? 'sont' : 'est') . ' disponible' . (count($missions) > 1 ? 's' : '') . ' sur votre secteur.
                            </p>
                            
                           
                            <div style="border-top: 2px solid #e0e0e0; margin: 30px 0;"></div>
                            
                            ' . $missionsHtml . '
                        </div>
                        
                        <div style="background: #e7f3ff; padding: 20px; text-align: center;">
                            <p style="color: #667eea; font-weight: bold; margin: 0;">Merci de votre engagement !</p>
                        </div>
                        
                    </div>
                </body>
                </html>';
                
                mail($email, $subject, $htmlMessage, $headers);
                $emailCount++;
            }
            
            $emailSent = true;
        } catch(Exception $e) {
            $emailError = "Erreur lors de l'envoi : " . $e->getMessage();
        }
    } else {
        $emailError = "Aucun bénévole sélectionné.";
    }
}

// Fonction pour récupérer les bénévoles par secteur ou tous (pour AJAX)
if (isset($_GET['get_benevoles'])) {
    header('Content-Type: application/json');
    try {
        if (isset($_GET['all']) && $_GET['all'] === '1') {
            // Tous les bénévoles avec flag_mail = 'O'
            $sqlBenevoles = "SELECT id_benevole, nom, courriel, secteur 
                            FROM EPI_benevole 
                            WHERE courriel IS NOT NULL AND courriel != '' AND flag_mail='O'
                            ORDER BY secteur, nom";
            $stmtBenevoles = $conn->prepare($sqlBenevoles);
            $stmtBenevoles->execute();
        } else if (isset($_GET['secteur'])) {
            // Bénévoles du secteur spécifique
            $sqlBenevoles = "SELECT id_benevole, nom, courriel, secteur 
                            FROM EPI_benevole 
                            WHERE secteur = :secteur AND courriel IS NOT NULL AND courriel != '' AND flag_mail='O'
                            ORDER BY nom";
            $stmtBenevoles = $conn->prepare($sqlBenevoles);
            $stmtBenevoles->execute(['secteur' => $_GET['secteur']]);
        } else {
            echo json_encode(['error' => 'Paramètre manquant']);
            exit;
        }
        
        $benevoles = $stmtBenevoles->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($benevoles);
    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Récupérer les missions sans bénévole, groupées par secteur
$missionsBySecteur = [];
try {
    $sql = "SELECT 
                m.id_mission,
                m.date_mission,
                m.heure_rdv,
                m.nature_intervention,
                m.adresse_destination,
                m.commune_destination,
                m.commentaires,
                a.nom as aide_nom,
                a.adresse,
                a.commune,
                a.tel_fixe,
                a.tel_portable,
                a.secteur
            FROM EPI_mission m
            INNER JOIN EPI_aide a ON m.id_aide = a.id_aide
            WHERE (m.id_benevole IS null OR m.id_benevole = 0 )
            ORDER BY a.secteur, m.date_mission, m.heure_rdv";
    
    $stmt = $conn->query($sql);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper par secteur
    foreach($missions as $mission) {
        $secteur = !empty($mission['secteur']) ? $mission['secteur'] : 'Non défini';
        if (!isset($missionsBySecteur[$secteur])) {
            $missionsBySecteur[$secteur] = [];
        }
        $missionsBySecteur[$secteur][] = $mission;
    }
    
    // Trier les secteurs
    ksort($missionsBySecteur);
    
} catch(PDOException $e) {
    die("Erreur lors de la récupération des missions : " . $e->getMessage());
}

// Calculer le total de missions
$totalMissions = array_sum(array_map('count', $missionsBySecteur));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missions sans Bénévoles</title>
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
            display: inline-block;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            transition: transform 0.2s ease;
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
            margin-top: 20px;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }

        .stats-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .stats-banner h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .stats-banner .total {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 12px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab:hover {
            background: #e9ecef;
            color: #667eea;
        }

        .tab.active {
            background: #667eea;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .secteur-header {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .secteur-stats {
            flex: 1;
        }

        .secteur-stats strong {
            color: #667eea;
            font-size: 18px;
        }

        .notify-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .notify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .missions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .mission-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .mission-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .mission-title {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
        }

        .aide-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .mission-card p {
            font-size: 13px;
            color: #555;
            margin: 5px 0;
            line-height: 1.5;
        }

        .mission-card strong {
            color: #333;
        }

        .mission-info {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 8px;
            border: 1px solid #e9ecef;
        }

        .destination-highlight {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin-top: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state svg {
            width: 120px;
            height: 120px;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #999;
            border: none;
            font-size: 18px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
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
            padding: 20px;
            margin: -30px -30px 20px -30px;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .close-modal {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close-modal:hover {
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e9ecef;
            color: #666;
        }

        .btn-secondary:hover {
            background: #dee2e6;
        }

        .recipient-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .recipient-option {
            flex: 1;
            position: relative;
        }

        .recipient-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .recipient-option label {
            display: block;
            padding: 15px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }

        .recipient-option input[type="radio"]:checked + label {
            background: #667eea;
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .recipient-option label:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .benevoles-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
        }

        .benevole-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .benevole-item:hover {
            background: #e7f3ff;
            transform: translateX(5px);
        }

        .benevole-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .benevole-info {
            flex: 1;
        }

        .benevole-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .benevole-email {
            font-size: 12px;
            color: #666;
        }

        .benevole-secteur {
            font-size: 11px;
            color: #999;
            font-style: italic;
        }

        .select-all-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #e7f3ff;
            border-radius: 6px;
        }

        .select-all-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .select-all-btn:hover {
            background: #5568d3;
        }

        .benevole-count {
            color: #667eea;
            font-weight: 600;
        }

        .loading-spinner {
            text-align: center;
            padding: 20px;
            color: #667eea;
        }

        .no-benevoles {
            text-align: center;
            padding: 20px;
            color: #999;
        }

        @media (max-width: 768px) {
            .missions-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                width: 100%;
            }

            .stats-banner .total {
                font-size: 28px;
            }

            .secteur-header {
                flex-direction: column;
                align-items: stretch;
            }

            .notify-btn {
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
            }

            .modal-header {
                margin: -20px -20px 15px -20px;
                padding: 15px;
            }

            .recipient-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au dashboard</a>

    <div class="container">
        <h1>📋 Missions sans Bénévoles Assignés</h1>

        <?php if ($emailSent): ?>
            <div class="alert alert-success">
                ✅ Email envoyé avec succès à <?php echo $emailCount; ?> bénévole<?php echo $emailCount > 1 ? 's' : ''; ?> !
            </div>
        <?php endif; ?>

        <?php if ($emailError): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($emailError); ?>
            </div>
        <?php endif; ?>

        <div class="stats-banner">
            <h2>📊 Total des missions à pourvoir</h2>
            <div class="total"><?php echo $totalMissions; ?></div>
            <p>missions en attente de bénévole</p>
        </div>

        <?php if (empty($missionsBySecteur)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h3>✅ Aucune mission en attente</h3>
                <p>Toutes les missions ont un bénévole assigné !</p>
            </div>
        <?php else: ?>
            <div class="tabs">
                <?php $first = true; ?>
                <?php foreach($missionsBySecteur as $secteur => $missions): ?>
                    <button class="tab <?php echo $first ? 'active' : ''; ?>" 
                            onclick="switchTab('<?php echo htmlspecialchars($secteur); ?>')">
                        <?php echo htmlspecialchars($secteur); ?> (<?php echo count($missions); ?>)
                    </button>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>

            <?php $first = true; ?>
            <?php foreach($missionsBySecteur as $secteur => $missions): ?>
                <div class="tab-content <?php echo $first ? 'active' : ''; ?>" 
                     id="tab-<?php echo htmlspecialchars($secteur); ?>">
                    
                    <div class="secteur-header">
                        <div class="secteur-stats">
                            <p>Secteur : <strong><?php echo htmlspecialchars($secteur); ?></strong></p>
                            <p>Missions à pourvoir : <strong><?php echo count($missions); ?></strong></p>
                        </div>
                        <button class="notify-btn" onclick="openEmailModal('<?php echo htmlspecialchars($secteur); ?>', <?php echo count($missions); ?>)">
                            📧 Notifier les bénévoles
                        </button>
                    </div>

                    <h3>🎯 Missions en attente</h3>
                    <div class="missions-grid">
                        <?php foreach($missions as $index => $mission): ?>
                            <div class="mission-card">
                                <div class="mission-title">
                                    Mission du <?php echo formatDate($mission['date_mission']); ?>
                                </div>
                                
                                <div class="aide-name">
                                    👤 <?php echo htmlspecialchars($mission['aide_nom']); ?>
                                </div>
                                
                                <p><strong>📅 Rendez-vous :</strong> <?php echo formatDate($mission['date_mission']); ?> à <?php echo !empty($mission['heure_rdv']) ? $mission['heure_rdv'] : 'Heure non précisée'; ?></p>
                                
                                <div class="mission-info">
                                    <p><strong>📍 Adresse départ :</strong><br>
                                    <?php echo htmlspecialchars($mission['adresse']); ?><br>
                                    <?php echo htmlspecialchars($mission['commune']); ?></p>
                                </div>

                                <?php if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])): ?>
                                    <div class="destination-highlight">
                                        <p><strong>🎯 Destination :</strong><br>
                                        <?php echo !empty($mission['adresse_destination']) ? htmlspecialchars($mission['adresse_destination']) . '<br>' : ''; ?>
                                        <?php echo !empty($mission['commune_destination']) ? htmlspecialchars($mission['commune_destination']) : ''; ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($mission['nature_intervention'])): ?>
                                    <p><strong>📖 Nature :</strong> <?php echo htmlspecialchars($mission['nature_intervention']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($mission['commentaires'])): ?>
                                    <p><strong>💬 Commentaires :</strong> <?php echo htmlspecialchars($mission['commentaires']); ?></p>
                                <?php endif; ?>

                                <div class="mission-info">
                                    <p><strong>📞 Contact :</strong><br>
                                    <?php if (!empty($mission['tel_fixe'])): ?>
                                        Fixe: <?php echo formatPhone($mission['tel_fixe']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($mission['tel_portable'])): ?>
                                        Mobile: <?php echo formatPhone($mission['tel_portable']); ?>
                                    <?php endif; ?>
                                    <?php if (empty($mission['tel_fixe']) && empty($mission['tel_portable'])): ?>
                                        Non renseigné
                                    <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal d'envoi d'email -->
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal" onclick="closeEmailModal()">&times;</span>
                <h2>📧 Notifier les bénévoles</h2>
            </div>
            <form method="POST" action="" id="emailForm">
                <input type="hidden" name="send_email" value="1">
                <input type="hidden" name="secteur" id="modal-secteur" value="">
                
                <div class="form-group">
                    <label>Secteur concerné :</label>
                    <p style="color: #667eea; font-weight: bold; font-size: 16px;" id="modal-secteur-display"></p>
                </div>

                <div class="form-group">
                    <label>Choisir les destinataires :</label>
                    <div class="recipient-options">
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" id="secteur_only" value="secteur" checked>
                            <label for="secteur_only">
                                📍 Bénévoles du secteur uniquement
                            </label>
                        </div>
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" id="all_benevoles" value="all">
                            <label for="all_benevoles">
                                🌍 Tous les bénévoles
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Sélectionner les bénévoles destinataires :</label>
                    <div id="benevoles-container">
                        <div class="loading-spinner">
                            <p>⏳ Chargement des bénévoles...</p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">Objet de l'email :</label>
                    <input type="text" id="subject" name="subject" required 
                           value="ENTRAIDE : nouvelles missions sur votre secteur">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="sendEmailBtn">📧 Envoyer les notifications</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSecteur = '';

        function switchTab(secteur) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.classList.add('active');
            document.getElementById('tab-' + secteur).classList.add('active');
        }

        function openEmailModal(secteur, nbMissions) {
            const modal = document.getElementById('emailModal');
            currentSecteur = secteur;
            document.getElementById('modal-secteur').value = secteur;
            document.getElementById('modal-secteur-display').textContent = secteur + ' (' + nbMissions + ' mission' + (nbMissions > 1 ? 's' : '') + ')';
            
            // Réinitialiser à "secteur uniquement"
            document.getElementById('secteur_only').checked = true;
            
            // Charger les bénévoles du secteur
            loadBenevoles(false);
            
            modal.classList.add('show');
        }

        // Écouter le changement de type de destinataire
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[name="recipient_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const isAll = this.value === 'all';
                    loadBenevoles(isAll);
                });
            });
        });

        function loadBenevoles(loadAll) {
            const container = document.getElementById('benevoles-container');
            container.innerHTML = '<div class="loading-spinner"><p>⏳ Chargement des bénévoles...</p></div>';
            
            const url = loadAll ? 
                '?get_benevoles=1&all=1' : 
                '?get_benevoles=1&secteur=' + encodeURIComponent(currentSecteur);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = '<div class="no-benevoles"><p>❌ Erreur: ' + data.error + '</p></div>';
                        return;
                    }
                    
                    if (data.length === 0) {
                        container.innerHTML = '<div class="no-benevoles"><p>Aucun bénévole avec email trouvé.</p></div>';
                        return;
                    }
                    
                    let html = '<div class="select-all-container">';
                    html += '<span class="benevole-count">' + data.length + ' bénévole' + (data.length > 1 ? 's' : '') + ' disponible' + (data.length > 1 ? 's' : '') + '</span>';
                    html += '<button type="button" class="select-all-btn" onclick="toggleSelectAll()">✓ Tout sélectionner</button>';
                    html += '</div>';
                    html += '<div class="benevoles-list">';
                    
                    data.forEach(benevole => {
                        html += '<label class="benevole-item">';
                        html += '<input type="checkbox" name="benevoles[]" value="' + benevole.courriel + '">';
                        html += '<div class="benevole-info">';
                        html += '<div class="benevole-name">' + benevole.nom + '</div>';
                        html += '<div class="benevole-email">' + benevole.courriel + '</div>';
                        if (loadAll && benevole.secteur) {
                            html += '<div class="benevole-secteur">Secteur: ' + benevole.secteur + '</div>';
                        }
                        html += '</div>';
                        html += '</label>';
                    });
                    
                    html += '</div>';
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = '<div class="no-benevoles"><p>❌ Erreur de chargement: ' + error + '</p></div>';
                });
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.benevoles-list input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const btn = event.target;
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            
            btn.textContent = allChecked ? '✓ Tout sélectionner' : '✗ Tout désélectionner';
        }

        function closeEmailModal() {
            const modal = document.getElementById('emailModal');
            modal.classList.remove('show');
        }

        // Validation avant envoi
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.benevoles-list input[type="checkbox"]:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('⚠️ Veuillez sélectionner au moins un bénévole destinataire.');
                return false;
            }
            
            const confirmMsg = `📧 Confirmer l'envoi des missions par email à ${checkedBoxes.length} bénévole${checkedBoxes.length > 1 ? 's' : ''} ?`;
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });

        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('emailModal');
            if (event.target === modal) {
                closeEmailModal();
            }
        }

        <?php if ($totalMissions === 0): ?>
            setTimeout(() => {
                console.log('✅ Aucune mission en attente - Excellent travail !');
            }, 500);
        <?php endif; ?>
    </script>
</body>
</html>