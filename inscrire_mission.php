<?php
// Charger la configuration WordPress
require_once('wp-config.php');

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
    $secretKey = 'VOTRE_CLE_SECRETE_A_CHANGER';
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

$status = '';
$message = '';
$missionDetails = null;

// Vérifier que tous les paramètres sont présents
if ($missionId && $email && $token) {
    // Vérifier le token
    $expectedToken = generateSecureToken($missionId, $email);
    
    if ($token !== $expectedToken) {
        $status = 'error';
        $message = 'Lien invalide ou expiré. Veuillez contacter l\'administrateur.';
    } else {
        try {
            // Récupérer TOUTES les informations du bénévole
            $sqlBenevole = "SELECT 
                                id_benevole, 
                                nom, 
                                adresse, 
                                code_postal, 
                                commune,
                                secteur
                            FROM EPI_benevole 
                            WHERE courriel = :email 
                            LIMIT 1";
            $stmtBenevole = $conn->prepare($sqlBenevole);
            $stmtBenevole->execute(['email' => $email]);
            $benevole = $stmtBenevole->fetch(PDO::FETCH_ASSOC);
            
            if (!$benevole) {
                $status = 'error';
                $message = 'Bénévole non trouvé. Votre email n\'est peut-être pas enregistré dans notre système.';
            } else {
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
                    // Mission disponible, on l'attribue avec TOUTES les informations du bénévole
                    $nomComplet = $benevole['nom'];
                    
                    $sqlUpdate = "UPDATE EPI_mission SET 
                                    id_benevole = :benevole_id,
                                    benevole = :benevole_nom,
                                    adresse_benevole = :adresse_benevole,
                                    cp_benevole = :cp_benevole,
                                    commune_benevole = :commune_benevole,
                                    secteur_benevole = :secteur_benevole
                                  WHERE id_mission = :mission_id";
                    
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    $stmtUpdate->execute([
                        'benevole_id' => $benevole['id_benevole'],
                        'benevole_nom' => $nomComplet,
                        'adresse_benevole' => $benevole['adresse'] ?? '',
                        'cp_benevole' => $benevole['code_postal'] ?? '',
                        'commune_benevole' => $benevole['commune'] ?? '',
                        'secteur_benevole' => $benevole['secteur'] ?? '',
                        'mission_id' => $missionId
                    ]);
                    
                    $status = 'success';
                    $message = 'Félicitations ! Vous avez été inscrit(e) avec succès à cette mission.';
                    $missionDetails = $mission;
                    
                    // Email de confirmation COMPLET avec toutes les informations
                    $headers = "From: noreply@votre-association.fr\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    
                    $aideNomComplet = $mission['aide_nom'];
                    
                    // Construction de l'email avec TABLE pour compatibilité maximale
                    $confirmationEmail = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Inscription confirmée</title>
    <style type="text/css">
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        p { display: block; margin: 13px 0; }
    </style>
    <!--[if mso]>
    <style type="text/css">
    body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); background-color: #667eea;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); background-color: #667eea; min-height: 100vh;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <!-- Container principal -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); background-color: #28a745; padding: 30px; border-radius: 20px 20px 0 0;">
                            <h1 style="margin: 0; font-size: 48px; margin-bottom: 10px; color: #ffffff; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">✅</h1>
                            <h2 style="margin: 0; font-size: 24px; font-weight: normal; color: #ffffff; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">Inscription réussie !</h2>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                            <!-- Greeting -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="font-size: 18px; color: #333; margin-bottom: 30px; line-height: 1.6;">
                                        Bonjour <strong>' . htmlspecialchars($benevole['nom']) . '</strong>,<br><br>
                                        Votre inscription à la mission du <strong>' . formatDate($mission['date_mission']) . '</strong> a bien été enregistrée.
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Mission Details Block -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 8px; margin-top: 20px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="color: #667eea; margin: 0 0 15px 0; font-size: 18px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">📋 Détails de votre mission</h3>
                                        
                                        <!-- Aide Name -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); background-color: #667eea; border-radius: 8px; margin: 20px 0;">
                                            <tr>
                                                <td align="center" style="padding: 15px; color: #ffffff; font-size: 20px; font-weight: bold; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    👤 ' . htmlspecialchars($aideNomComplet) . '
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Date et heure -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">📅 Date et heure</strong>
                                                    <span style="color: #666;">' . formatDate($mission['date_mission']) . 
                                                    (!empty($mission['heure_rdv']) ? ' à ' . substr($mission['heure_rdv'], 0, 5) : '') . '</span>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Adresse de départ -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">🏠 Adresse de départ</strong>
                                                    <span style="color: #666;">' . htmlspecialchars($mission['aide_adresse'] ?? '') . '<br>' .
                                                    htmlspecialchars($mission['aide_cp'] ?? '') . ' ' . htmlspecialchars($mission['aide_commune'] ?? '') . '</span>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Contact -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">📞 Contact</strong>
                                                    <span style="color: #666;">';
                    if (!empty($mission['aide_tel_fixe'])) {
                        $confirmationEmail .= 'Fixe: ' . formatPhone($mission['aide_tel_fixe']) . '<br>';
                    }
                    if (!empty($mission['aide_tel_portable'])) {
                        $confirmationEmail .= 'Mobile: ' . formatPhone($mission['aide_tel_portable']);
                    }
                    if (empty($mission['aide_tel_fixe']) && empty($mission['aide_tel_portable'])) {
                        $confirmationEmail .= 'Non renseigné';
                    }
                    $confirmationEmail .= '</span>
                                                </td>
                                            </tr>
                                        </table>';

                    // AJOUT : Commentaires de l'aidé
                    if (!empty($mission['aide_commentaires'])) {
                        $confirmationEmail .= '
                                        <!-- Commentaires aidé -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #e7f3ff; border-radius: 6px; border-left: 4px solid #2196F3; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">ℹ️ Informations sur la personne accompagnée</strong>
                                                    <span style="color: #666;">' . nl2br(htmlspecialchars($mission['aide_commentaires'])) . '</span>
                                                </td>
                                            </tr>
                                        </table>';
                    }

                    // Destination
                    if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])) {
                        $confirmationEmail .= '
                                        <!-- Destination -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fff3cd; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">🎯 Destination</strong>
                                                    <span style="color: #666;">';
                        if (!empty($mission['adresse_destination'])) {
                            $confirmationEmail .= htmlspecialchars($mission['adresse_destination']) . '<br>';
                        }
                        if (!empty($mission['commune_destination'])) {
                            $confirmationEmail .= htmlspecialchars($mission['cp_destination'] ?? '') . ' ' . htmlspecialchars($mission['commune_destination']);
                        }
                        $confirmationEmail .= '</span>
                                                </td>
                                            </tr>
                                        </table>';
                    }

                    // Nature intervention
                    if (!empty($mission['nature_intervention'])) {
                        $confirmationEmail .= '
                                        <!-- Nature intervention -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">📖 Nature de l\'intervention</strong>
                                                    <span style="color: #666;">' . htmlspecialchars($mission['nature_intervention']) . '</span>
                                                </td>
                                            </tr>
                                        </table>';
                    }

                    // Commentaires mission
                    if (!empty($mission['commentaires'])) {
                        $confirmationEmail .= '
                                        <!-- Commentaires mission -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; border-radius: 6px; margin: 12px 0;">
                                            <tr>
                                                <td style="padding: 10px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                                    <strong style="color: #333; display: block; margin-bottom: 5px;">💬 Commentaires sur la mission</strong>
                                                    <span style="color: #666;">' . nl2br(htmlspecialchars($mission['commentaires'])) . '</span>
                                                </td>
                                            </tr>
                                        </table>';
                    }

                    $confirmationEmail .= '
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Note -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #d4edda; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; color: #155724; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                                        ⚠️ Merci de contacter la personne accompagnée pour confirmer le rendez-vous et les détails pratiques de la mission ⚠️
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f8f9fa; padding: 20px; color: #666; font-size: 13px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; border-radius: 0 0 20px 20px;">
                            Cet email a été envoyé automatiquement, merci de ne pas y répondre.<br>
                            Pour toute question, contactez votre coordinateur.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
                    
                    mail($email, 'Confirmation d\'inscription - Mission du ' . date('d/m/Y', strtotime($mission['date_mission'])), $confirmationEmail, $headers);
                }
            }
        } catch(PDOException $e) {
            $status = 'error';
            $message = 'Erreur lors de l\'inscription : ' . $e->getMessage();
        }
    }
} else {
    $status = 'error';
    $message = 'Paramètres manquants dans le lien.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription à la mission</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            padding: 30px;
            text-align: center;
            color: white;
        }

        .header.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .header.warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }

        .header.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .header h1 {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .header h2 {
            font-size: 24px;
            font-weight: normal;
        }

        .content {
            padding: 30px;
        }

        .message {
            font-size: 18px;
            color: #333;
            margin-bottom: 30px;
            line-height: 1.6;
            text-align: center;
        }

        .mission-details {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .mission-details h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .detail-item {
            margin: 12px 0;
            padding: 10px;
            background: white;
            border-radius: 6px;
        }

        .detail-item strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }

        .aide-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
        }

        .aide-commentaires {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
        }

        .btn-container {
            text-align: center;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .header h1 {
                font-size: 36px;
            }

            .header h2 {
                font-size: 20px;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header <?php echo $status; ?>">
            <h1>
                <?php if ($status === 'success'): ?>
                    ✅
                <?php elseif ($status === 'warning'): ?>
                    ⚠️
                <?php else: ?>
                    ❌
                <?php endif; ?>
            </h1>
            <h2>
                <?php if ($status === 'success'): ?>
                    Inscription confirmée !
                <?php elseif ($status === 'warning'): ?>
                    Mission déjà pourvue
                <?php else: ?>
                    Erreur
                <?php endif; ?>
            </h2>
        </div>

        <div class="content">
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>

            <?php if ($missionDetails): ?>
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
</body>
</html>