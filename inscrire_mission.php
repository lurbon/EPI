<?php
// Charger la configuration
require_once('config.php');

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
 // Utiliser la clé définie dans config.php, sinon erreur
    if (!defined('EPI_MISSION_SECRET_KEY')) {
        error_log("SECURITE: EPI_MISSION_SECRET_KEY non définie dans config.php");
        die("Erreur de configuration. Contactez l'administrateur.");
    }
	    $secretKey = EPI_MISSION_SECRET_KEY;
    return hash('sha256', $missionId . '|' . $benevoleEmail . '|' . $secretKey);
}

// Fonction pour formater les dates
function formatDate($date) {
    if (empty($date)) return 'Non précisée';
    $timestamp = strtotime($date);
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = date('w', $timestamp);
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp);
    return $jours[$j] . ' ' . $d . ' ' . $mois[$m] . ' ' . $y;
}

function formatPhone($phone) {
    if (empty($phone)) return '';
    $cleaned = preg_replace('/\s+/', '', $phone);
    if (strlen($cleaned) == 10) {
        return chunk_split($cleaned, 2, ' ');
    }
    return $phone;
}

// Récupérer les paramètres
$missionId = isset($_GET['mission']) ? intval($_GET['mission']) : 0;
$email = isset($_GET['email']) ? $_GET['email'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$confirmed = isset($_GET['confirmed']) ? $_GET['confirmed'] : '0';
$emetteurEmail = isset($_GET['emetteur']) ? $_GET['emetteur'] : '';
$selectedBenevoleId = isset($_GET['benevole_id']) ? intval($_GET['benevole_id']) : 0; // NOUVEAU : ID du bénévole choisi

$status = '';
$message = '';
$missionDetails = null;
$showConfirmation = false;
$showBenevoleChoice = false; // NOUVEAU : Flag pour afficher le choix de bénévole
$benevoles = []; // NOUVEAU : Liste des bénévoles avec le même email

// Vérifier que tous les paramètres sont présents
if ($missionId && $email && $token) {
    // Vérifier le token
    $expectedToken = generateSecureToken($missionId, $email);
    
    if ($token !== $expectedToken) {
        $status = 'error';
        $message = 'Lien invalide ou expiré. Veuillez contacter l\'administrateur.';
    } else {
        try {
            // Récupérer TOUS les bénévoles avec cet email (sans LIMIT)
            $sqlBenevoles = "SELECT 
                                id_benevole, 
                                nom, 
                                adresse, 
                                code_postal, 
                                commune,
                                secteur
                            FROM EPI_benevole 
                            WHERE courriel = :email
                            ORDER BY nom";
            $stmtBenevoles = $conn->prepare($sqlBenevoles);
            $stmtBenevoles->execute(['email' => $email]);
            $benevoles = $stmtBenevoles->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($benevoles) === 0) {
                $status = 'error';
                $message = 'Bénévole non trouvé. Votre email n\'est peut-être pas enregistré dans notre système.';
            } elseif (count($benevoles) > 1 && $selectedBenevoleId === 0) {
                // NOUVEAU : Plusieurs bénévoles avec le même email et aucun n'a été sélectionné
                $showBenevoleChoice = true;
                $status = 'choose_benevole';
                $message = 'Plusieurs profils sont associés à cet email. Veuillez sélectionner votre nom :';
                
                // Récupérer les détails de la mission pour affichage
                $sqlMission = "SELECT 
                                    m.id_mission,
                                    m.date_mission,
                                    m.heure_rdv,
                                    m.nature_intervention,
                                    m.adresse_destination,
                                    m.cp_destination,
                                    m.commune_destination,
                                    m.commentaires,
                                    m.id_benevole,
                                    a.nom as aide_nom,
                                    a.adresse as aide_adresse,
                                    a.code_postal as aide_cp,
                                    a.commune as aide_commune,
                                    a.tel_fixe as aide_tel_fixe,
                                    a.tel_portable as aide_tel_portable,
                                    a.secteur as aide_secteur,
                                    a.commentaires as aide_commentaires
                                FROM EPI_mission m
                                INNER JOIN EPI_aide a ON m.id_aide = a.id_aide
                                WHERE m.id_mission = :mission_id";
                
                $stmtMission = $conn->prepare($sqlMission);
                $stmtMission->execute(['mission_id' => $missionId]);
                $missionDetails = $stmtMission->fetch(PDO::FETCH_ASSOC);
            } else {
                // Un seul bénévole OU un bénévole a été sélectionné
                if ($selectedBenevoleId > 0) {
                    // Vérifier que l'ID sélectionné correspond bien à un des bénévoles avec cet email
                    $benevole = null;
                    foreach ($benevoles as $b) {
                        if ($b['id_benevole'] == $selectedBenevoleId) {
                            $benevole = $b;
                            break;
                        }
                    }
                    if (!$benevole) {
                        $status = 'error';
                        $message = 'Sélection invalide. Veuillez réessayer.';
                    }
                } else {
                    // Un seul bénévole, on le prend directement
                    $benevole = $benevoles[0];
                }
                
                if (isset($benevole)) {
                    // Vérifier que la mission existe et est toujours disponible
                    $sqlMission = "SELECT 
                                        m.id_mission,
                                        m.date_mission,
                                        m.heure_rdv,
                                        m.nature_intervention,
                                        m.adresse_destination,
                                        m.cp_destination,
                                        m.commune_destination,
                                        m.commentaires,
                                        m.id_benevole,
                                        a.nom as aide_nom,
                                        a.adresse as aide_adresse,
                                        a.code_postal as aide_cp,
                                        a.commune as aide_commune,
                                        a.tel_fixe as aide_tel_fixe,
                                        a.tel_portable as aide_tel_portable,
                                        a.secteur as aide_secteur,
                                        a.commentaires as aide_commentaires
                                    FROM EPI_mission m
                                    INNER JOIN EPI_aide a ON m.id_aide = a.id_aide
                                    WHERE m.id_mission = :mission_id";
                    
                    $stmtMission = $conn->prepare($sqlMission);
                    $stmtMission->execute(['mission_id' => $missionId]);
                    $mission = $stmtMission->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$mission) {
                        $status = 'error';
                        $message = 'Mission non trouvée.';
                    } elseif ($mission['id_benevole'] && $mission['id_benevole'] != 0) {
                        // Mission déjà pourvue
                        $status = 'warning';
                        $message = 'Cette mission a déjà été attribuée à un autre bénévole.';
                        $missionDetails = $mission;
                    } else {
                        // Mission disponible
                        $missionDetails = $mission;
                        
                        // Si pas encore confirmé, afficher la popup
                        if ($confirmed !== '1') {
                            $showConfirmation = true;
                            $status = 'pending';
                        } else {
                            // Confirmation reçue, on procède à l'inscription
                            $nomComplet = $benevole['nom'];
                            
                            $sqlUpdate = "UPDATE EPI_mission SET 
                                            id_benevole = :benevole_id,
                                            benevole = :benevole_nom,
                                            adresse_benevole = :adresse_benevole,
                                            cp_benevole = :cp_benevole,
                                            commune_benevole = :commune_benevole,
                                            secteur_benevole = :secteur_benevole,
                                            email_inscript = :email_inscript,
                                            date_inscript = NOW()
                                          WHERE id_mission = :mission_id";
                            
                            $stmtUpdate = $conn->prepare($sqlUpdate);
                            $stmtUpdate->execute([
                                'benevole_id' => $benevole['id_benevole'],
                                'benevole_nom' => $nomComplet,
                                'adresse_benevole' => $benevole['adresse'] ?? '',
                                'cp_benevole' => $benevole['code_postal'] ?? '',
                                'commune_benevole' => $benevole['commune'] ?? '',
                                'secteur_benevole' => $benevole['secteur'] ?? '',
                                'email_inscript' => $email,
                                'mission_id' => $missionId
                            ]);
                            
                            $status = 'success';
                            $message = 'Félicitations ! Vous avez été inscrit(e) avec succès à cette mission.';
                            
                            // Email de confirmation
                            $domaine = $_SERVER['HTTP_HOST'];
                            $headers = "From: Entraide Plus Iroise <noreply@{$domaine}>\r\n";
                            $headers .= "Reply-To: Entraide Plus Iroise <contact@{$domaine}>\r\n";
                            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                            
                            $aideNomComplet = $mission['aide_nom'];
                            
                            $confirmationEmail = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmation d\'inscription</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">✅ Votre inscription est confirmée</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333;">
                                Bonjour <strong>' . htmlspecialchars($nomComplet) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333;">
                                Votre inscription à la mission suivante a bien été prise en compte :
                            </p>
                            
                            <div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;">
                                <h3 style="margin: 0 0 15px 0; color: #667eea;">📋 Détails de la mission</h3>
                                
                                <p style="margin: 5px 0;"><strong>👤 Personne accompagnée :</strong> ' . htmlspecialchars($aideNomComplet) . '</p>
                                <p style="margin: 5px 0;"><strong>📅 Date :</strong> ' . formatDate($mission['date_mission']) . '</p>
                                <p style="margin: 5px 0;"><strong>⏰ Heure :</strong> ' . (!empty($mission['heure_rdv']) ? substr($mission['heure_rdv'], 0, 5) : 'Non précisée') . '</p>
                                
                                <p style="margin: 5px 0;"><strong>🏠 Adresse de départ :</strong><br>' . 
                                    htmlspecialchars($mission['aide_adresse']) . '<br>' . 
                                    htmlspecialchars($mission['aide_cp']) . ' ' . 
                                    htmlspecialchars($mission['aide_commune']) . '</p>
                                
                                ' . (!empty($mission['adresse_destination']) || !empty($mission['commune_destination']) ? 
                                    '<p style="margin: 5px 0;"><strong>🎯 Destination :</strong><br>' . 
                                    (!empty($mission['adresse_destination']) ? htmlspecialchars($mission['adresse_destination']) . '<br>' : '') . 
                                    (!empty($mission['commune_destination']) ? htmlspecialchars($mission['cp_destination']) . ' ' . htmlspecialchars($mission['commune_destination']) : '') . 
                                    '</p>' : '') . '
                                
                                ' . (!empty($mission['nature_intervention']) ? 
                                    '<p style="margin: 5px 0;"><strong>📖 Nature :</strong> ' . htmlspecialchars($mission['nature_intervention']) . '</p>' : '') . '
                                
                                <p style="margin: 5px 0;"><strong>📞 Contact :</strong><br>';
                            
                            if (!empty($mission['aide_tel_fixe'])) {
                                $confirmationEmail .= 'Fixe : ' . formatPhone($mission['aide_tel_fixe']) . '<br>';
                            }
                            if (!empty($mission['aide_tel_portable'])) {
                                $confirmationEmail .= 'Mobile : ' . formatPhone($mission['aide_tel_portable']);
                            }
                            
                            $confirmationEmail .= '</p>';
                            
                            if (!empty($mission['aide_commentaires'])) {
                                $confirmationEmail .= '<p style="margin: 15px 0 5px 0;"><strong>ℹ️ Informations :</strong><br>' . 
                                    nl2br(htmlspecialchars($mission['aide_commentaires'])) . '</p>';
                            }
                            
                            if (!empty($mission['commentaires'])) {
                                $confirmationEmail .= '<p style="margin: 15px 0 5px 0;"><strong>💬 Commentaires :</strong><br>' . 
                                    nl2br(htmlspecialchars($mission['commentaires'])) . '</p>';
                            }
                            
                            $confirmationEmail .= '
                            </div>
                            
                            <p style="margin: 25px 0 20px 0; font-size: 16px; line-height: 1.6; color: #dc3545; font-weight: bold; text-align: center;">
                                ⚠️ Merci de bien vouloir contacter la personne accompagnée la veille ou l\'avant-veille de votre mission
                            </p>
                            
                            <div style="background-color: #e9ecef; padding: 20px; border-radius: 8px; text-align: center; margin-top: 20px;">
                                <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #495057; font-weight: bold;">
                                    Merci de votre engagement !<br>
                                    <span style="font-size: 14px; font-weight: normal;">L\'équipe d\'Entraide Plus Iroise</span>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                            <p style="margin: 0;">Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
                            
                            // Envoyer l'email de confirmation au bénévole
                            mail($email, 'Confirmation d\'inscription à la mission', $confirmationEmail, $headers);
                            
                            // Email de notification à l'émetteur (si renseigné)
                            if (!empty($emetteurEmail) && filter_var($emetteurEmail, FILTER_VALIDATE_EMAIL)) {
                                $notificationEmail = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inscription confirmée</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background: #28a745; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">✅ Bénévole inscrit</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6;">
                                <strong>' . htmlspecialchars($nomComplet) . '</strong> s\'est inscrit(e) à la mission du ' . 
                                formatDate($mission['date_mission']) . ' avec ' . htmlspecialchars($aideNomComplet) . '.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
                                
                                mail($emetteurEmail, 'Inscription confirmée - ' . $nomComplet, $notificationEmail, $headers);
                            }
                        }
                    }
                }
            }
        } catch(PDOException $e) {
            $status = 'error';
            $message = 'Erreur technique : ' . $e->getMessage();
        }
    }
} else {
    $status = 'error';
    $message = 'Paramètres manquants. Veuillez utiliser le lien fourni dans l\'email.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription à une mission - Entraide Plus Iroise</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.95;
        }

        .content {
            padding: 30px;
        }

        .message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.6;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* NOUVEAU : Styles pour le choix de bénévole */
        .benevole-selection {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .benevole-option {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .benevole-option:hover {
            border-color: #667eea;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .benevole-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .benevole-info-select {
            flex: 1;
        }

        .benevole-name-select {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .benevole-details-select {
            font-size: 14px;
            color: #666;
        }

        .mission-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }

        .mission-details h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .aide-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #667eea;
        }

        .detail-item strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }

        .aide-commentaires {
            background: #e8f4f8;
            border-left-color: #17a2b8;
        }

        .btn-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
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
            background-color: rgba(0, 0, 0, 0.7);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
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
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .modal-header h1 {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .modal-header h2 {
            font-size: 24px;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
        }

        .confirmation-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .confirmation-details p {
            margin: 10px 0;
            font-size: 16px;
        }

        .modal-footer {
            padding: 20px 30px 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .content {
                padding: 20px;
            }

            .btn-container {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤝 Entraide Plus Iroise</h1>
            <p>Inscription à une mission</p>
        </div>

        <div class="content">
            <?php if ($status === 'choose_benevole'): ?>
                <!-- NOUVEAU : Affichage du choix de bénévole -->
                <div class="message info">
                    <?php echo htmlspecialchars($message); ?>
                </div>

                <div class="benevole-selection">
                    <form id="benevoleForm" method="get">
                        <input type="hidden" name="mission" value="<?php echo htmlspecialchars($missionId); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <?php if (!empty($emetteurEmail)): ?>
                            <input type="hidden" name="emetteur" value="<?php echo htmlspecialchars($emetteurEmail); ?>">
                        <?php endif; ?>

                        <?php foreach ($benevoles as $benevole): ?>
                            <label class="benevole-option">
                                <input type="radio" name="benevole_id" value="<?php echo $benevole['id_benevole']; ?>" required>
                                <div class="benevole-info-select">
                                    <div class="benevole-name-select"><?php echo htmlspecialchars($benevole['nom']); ?></div>
                                    <div class="benevole-details-select">
                                        <?php if (!empty($benevole['commune'])): ?>
                                            📍 <?php echo htmlspecialchars($benevole['commune']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($benevole['secteur'])): ?>
                                            • Secteur: <?php echo htmlspecialchars($benevole['secteur']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>

                        <div class="btn-container">
                            <button type="submit" class="btn">✅ Continuer avec ce profil</button>
                        </div>
                    </form>
                </div>

                <?php if ($missionDetails): ?>
                    <!-- Aperçu de la mission -->
                    <div class="mission-details">
                        <h3>📋 Aperçu de la mission</h3>
                        
                        <div class="aide-name">
                            👤 <?php echo htmlspecialchars($missionDetails['aide_nom']); ?>
                        </div>

                        <div class="detail-item">
                            <strong>📅 Date et heure</strong>
                            <?php echo formatDate($missionDetails['date_mission']); ?>
                            <?php if (!empty($missionDetails['heure_rdv'])): ?>
                                à <?php echo substr($missionDetails['heure_rdv'], 0, 5); ?>
                            <?php endif; ?>
                        </div>

                        <div class="detail-item">
                            <strong>🏠 Lieu</strong>
                            <?php echo htmlspecialchars($missionDetails['aide_commune']); ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (!$showConfirmation): ?>
                <div class="message <?php echo $status; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($missionDetails && !$showBenevoleChoice): ?>
                <div class="mission-details">
                    <h3>📋 Détails de la mission</h3>
                    
                    <div class="aide-name">
                        👤 <?php 
                        $aideNomComplet = $missionDetails['aide_nom'];
                        echo htmlspecialchars($aideNomComplet); 
                        ?>
                    </div>

                    <div class="detail-item">
                        <strong>📅 Date et heure</strong>
                        <?php echo formatDate($missionDetails['date_mission']); ?>
                        <?php if (!empty($missionDetails['heure_rdv'])): ?>
                            à <?php echo substr($missionDetails['heure_rdv'], 0, 5); ?>
                        <?php endif; ?>
                    </div>

                    <div class="detail-item">
                        <strong>🏠 Adresse de départ</strong>
                        <?php echo htmlspecialchars($missionDetails['aide_adresse']); ?><br>
                        <?php echo htmlspecialchars($missionDetails['aide_cp']); ?> 
                        <?php echo htmlspecialchars($missionDetails['aide_commune']); ?>
                    </div>

                    <div class="detail-item">
                        <strong>📞 Contact</strong>
                        <?php if (!empty($missionDetails['aide_tel_fixe'])): ?>
                            Fixe: <?php echo formatPhone($missionDetails['aide_tel_fixe']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($missionDetails['aide_tel_portable'])): ?>
                            Mobile: <?php echo formatPhone($missionDetails['aide_tel_portable']); ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($missionDetails['aide_commentaires'])): ?>
                        <div class="detail-item aide-commentaires">
                            <strong>ℹ️ Informations sur la personne accompagnée</strong>
                            <?php echo nl2br(htmlspecialchars($missionDetails['aide_commentaires'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missionDetails['adresse_destination']) || !empty($missionDetails['commune_destination'])): ?>
                        <div class="detail-item" style="background: #fff3cd;">
                            <strong>🎯 Destination</strong>
                            <?php if (!empty($missionDetails['adresse_destination'])): ?>
                                <?php echo htmlspecialchars($missionDetails['adresse_destination']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($missionDetails['commune_destination'])): ?>
                                <?php echo htmlspecialchars($missionDetails['cp_destination']); ?> 
                                <?php echo htmlspecialchars($missionDetails['commune_destination']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missionDetails['nature_intervention'])): ?>
                        <div class="detail-item">
                            <strong>📖 Nature de l'intervention</strong>
                            <?php echo htmlspecialchars($missionDetails['nature_intervention']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missionDetails['commentaires'])): ?>
                        <div class="detail-item">
                            <strong>💬 Commentaires sur la mission</strong>
                            <?php echo nl2br(htmlspecialchars($missionDetails['commentaires'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($status === 'success'): ?>
                    <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 20px;">
                        <p style="margin: 0; color: #155724;">
                            📧 Un email de confirmation avec tous les détails de la mission vous a été envoyé.
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($showConfirmation): ?>
                    <div class="btn-container">
                        <button onclick="confirmInscription()" class="btn">✅ Confirmer mon inscription</button>
                        <button onclick="window.close()" class="btn btn-secondary">❌ Annuler</button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($status === 'warning' || $status === 'error'): ?>
                <div class="btn-container">
                    <p style="margin-bottom: 15px; color: #666;">
                        Pour voir d'autres missions disponibles, contactez votre coordinateur.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation -->
    <?php if ($showConfirmation): ?>
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h1>⚠️</h1>
                <h2>Confirmer votre inscription</h2>
            </div>
            <div class="modal-body">
                <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                    Vous êtes sur le point de vous inscrire à cette mission. Veuillez confirmer que vous avez bien pris connaissance de tous les détails.
                </p>
                <div class="confirmation-details">
                    <p><strong>📅 Date :</strong> <?php echo formatDate($missionDetails['date_mission']); ?></p>
                    <p><strong>⏰ Heure :</strong> <?php echo !empty($missionDetails['heure_rdv']) ? substr($missionDetails['heure_rdv'], 0, 5) : 'Non précisée'; ?></p>
                    <p><strong>👤 Personne accompagnée :</strong> <?php echo htmlspecialchars($missionDetails['aide_nom']); ?></p>
                    <p><strong>🏠 Lieu de départ :</strong> <?php echo htmlspecialchars($missionDetails['aide_commune']); ?></p>
                </div>
                <p style="font-size: 14px; color: #666; margin-top: 20px;">
                    Une fois confirmée, cette mission vous sera attribuée et vous recevrez un email de confirmation avec tous les détails.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="proceedInscription()" class="btn">✅ Oui, je confirme</button>
                <button onclick="closeModal()" class="btn btn-secondary">❌ Annuler</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        <?php if ($showConfirmation): ?>
        // Afficher la modal au chargement de la page
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmModal').classList.add('active');
        });

        function confirmInscription() {
            document.getElementById('confirmModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        function proceedInscription() {
            // Ajouter le paramètre confirmed=1 à l'URL et recharger
            const url = new URL(window.location.href);
            url.searchParams.set('confirmed', '1');
            window.location.href = url.toString();
        }

        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        <?php endif; ?>

        <?php if ($status === 'choose_benevole'): ?>
        // Améliorer l'UX des options radio
        document.querySelectorAll('.benevole-option').forEach(option => {
            option.addEventListener('click', function() {
                // Désélectionner tous les autres
                document.querySelectorAll('.benevole-option').forEach(opt => {
                    opt.style.borderColor = '#e0e0e0';
                    opt.style.backgroundColor = 'white';
                });
                // Sélectionner celui-ci
                this.style.borderColor = '#667eea';
                this.style.backgroundColor = '#f0f4ff';
                // Cocher le radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
