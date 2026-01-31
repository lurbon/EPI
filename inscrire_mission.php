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
$confirmed = isset($_GET['confirmed']) ? $_GET['confirmed'] : '0'; // Nouveau paramètre pour vérifier la confirmation

$status = '';
$message = '';
$missionDetails = null;
$showConfirmation = false; // Flag pour afficher la popup

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
                        
                        // Email de confirmation (code email inchangé...)
                        $headers = "From: noreply@votre-association.fr\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        
                        $aideNomComplet = $mission['aide_nom'];
                        
                        $confirmationEmail = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Confirmation</title></head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;">
<div style="background:#667eea;padding:40px 20px;">
<div style="max-width:600px;margin:0 auto;background:white;border-radius:10px;overflow:hidden;">
<div style="background:#28a745;color:white;padding:30px;text-align:center;">
<h1 style="margin:0;font-size:36px;">✅</h1>
<h2 style="margin:10px 0 0;">Inscription confirmée !</h2>
</div>
<div style="padding:30px;">
<p>Bonjour ' . htmlspecialchars($nomComplet) . ',</p>
<p>Votre inscription à la mission a bien été enregistrée.</p>
<div style="background:#667eea;color:white;padding:15px;border-radius:8px;text-align:center;margin:20px 0;">
<strong>👤 ' . htmlspecialchars($aideNomComplet) . '</strong>
</div>
<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#667eea;">📅 Date et heure</strong><br>
' . formatDate($mission['date_mission']) . (!empty($mission['heure_rdv']) ? ' à ' . substr($mission['heure_rdv'], 0, 5) : '') . '
</div>
<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#667eea;">🏠 Adresse de départ</strong><br>
' . htmlspecialchars($mission['aide_adresse']) . '<br>' . htmlspecialchars($mission['aide_cp']) . ' ' . htmlspecialchars($mission['aide_commune']) . '
</div>';

if (!empty($mission['aide_tel_fixe']) || !empty($mission['aide_tel_portable'])) {
    $confirmationEmail .= '<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#667eea;">📞 Contact</strong><br>';
    if (!empty($mission['aide_tel_fixe'])) {
        $confirmationEmail .= 'Fixe: ' . formatPhone($mission['aide_tel_fixe']) . '<br>';
    }
    if (!empty($mission['aide_tel_portable'])) {
        $confirmationEmail .= 'Mobile: ' . formatPhone($mission['aide_tel_portable']);
    }
    $confirmationEmail .= '</div>';
}

if (!empty($mission['aide_commentaires'])) {
    $confirmationEmail .= '<div style="background:#e7f3ff;padding:15px;border-radius:8px;margin:10px 0;border-left:4px solid #2196F3;">
<strong style="color:#2196F3;">ℹ️ Informations sur la personne</strong><br>
' . nl2br(htmlspecialchars($mission['aide_commentaires'])) . '
</div>';
}

if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])) {
    $confirmationEmail .= '<div style="background:#fff3cd;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#856404;">🎯 Destination</strong><br>';
    if (!empty($mission['adresse_destination'])) {
        $confirmationEmail .= htmlspecialchars($mission['adresse_destination']) . '<br>';
    }
    if (!empty($mission['commune_destination'])) {
        $confirmationEmail .= htmlspecialchars($mission['cp_destination']) . ' ' . htmlspecialchars($mission['commune_destination']);
    }
    $confirmationEmail .= '</div>';
}

if (!empty($mission['nature_intervention'])) {
    $confirmationEmail .= '<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#667eea;">📖 Nature de l\'intervention</strong><br>
' . htmlspecialchars($mission['nature_intervention']) . '
</div>';
}

if (!empty($mission['commentaires'])) {
    $confirmationEmail .= '<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:10px 0;">
<strong style="color:#667eea;">💬 Commentaires</strong><br>
' . nl2br(htmlspecialchars($mission['commentaires'])) . '
</div>';
}

$confirmationEmail .= '<p style="margin-top:20px;">Merci pour votre engagement ! 🙏</p>
</div>
<div style="background:#f8f9fa;padding:20px;text-align:center;">
<small style="color:#999;">Cet email a été envoyé automatiquement</small>
</div>
</div>
</div>
</body>
</html>';
                        
                        mail($email, '✅ Confirmation d\'inscription à votre mission', $confirmationEmail, $headers);
                    }
                }
            }
        } catch(PDOException $e) {
            $status = 'error';
            $message = 'Une erreur est survenue : ' . $e->getMessage();
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
            max-width: 800px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            padding: 40px 30px;
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

        .header.pending {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .header h1 {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .header h2 {
            font-size: 24px;
            font-weight: 500;
        }

        .content {
            padding: 40px 30px;
        }

        .message {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
        }

        .mission-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .mission-details h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .detail-item strong {
            display: block;
            color: #667eea;
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
            border: none;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        /* Modal/Popup styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }

        .modal-header h2 {
            font-size: 28px;
            margin-top: 10px;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            padding: 20px 30px 30px;
            text-align: center;
        }

        .confirmation-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .confirmation-details p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .confirmation-details strong {
            color: #667eea;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

            .btn {
                display: block;
                margin: 10px 0;
            }

            .modal-content {
                width: 95%;
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
                <?php elseif ($status === 'pending'): ?>
                    📋
                <?php else: ?>
                    ❌
                <?php endif; ?>
            </h1>
            <h2>
                <?php if ($status === 'success'): ?>
                    Inscription confirmée !
                <?php elseif ($status === 'warning'): ?>
                    Mission déjà pourvue
                <?php elseif ($status === 'pending'): ?>
                    Détails de la mission
                <?php else: ?>
                    Erreur
                <?php endif; ?>
            </h2>
        </div>

        <div class="content">
            <?php if (!$showConfirmation): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

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
    </script>
</body>
</html>
