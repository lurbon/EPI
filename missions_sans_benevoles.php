<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin','gestionnaire']);

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

// Fonction pour obtenir les couleurs d'un secteur (cohérence avec liste_missions.php)
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

/**
 * Enregistre un envoi de missions dans l'historique
 */
function enregistrerHistoriqueEnvoi($conn, $emailEmetteur, $missionsIds, $destinataires, $secteur = '', $sujetEmail = '') {
    try {
        // Convertir les tableaux en JSON
        $missionsJson = json_encode($missionsIds, JSON_UNESCAPED_UNICODE);
        $destinatairesJson = json_encode($destinataires, JSON_UNESCAPED_UNICODE);
        
        // Compter les éléments
        $nbMissions = count($missionsIds);
        $nbDestinataires = count($destinataires);
        
        // Préparer la requête d'insertion
        $sql = "INSERT INTO EPI_envoi 
                (email_emetteur, date_envoi, missions_ids, destinataires, nb_missions, nb_destinataires, secteur, sujet_email) 
                VALUES 
                (:email_emetteur, NOW(), :missions_ids, :destinataires, :nb_missions, :nb_destinataires, :secteur, :sujet_email)";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            'email_emetteur' => $emailEmetteur,
            'missions_ids' => $missionsJson,
            'destinataires' => $destinatairesJson,
            'nb_missions' => $nbMissions,
            'nb_destinataires' => $nbDestinataires,
            'secteur' => $secteur,
            'sujet_email' => $sujetEmail
        ]);
        
        if ($result) {
            $historiqueId = $conn->lastInsertId();
            error_log("✅ Historique envoi créé - ID: $historiqueId - Émetteur: $emailEmetteur - $nbMissions missions vers $nbDestinataires destinataires");
            return $historiqueId;
        }
        
        return false;
        
    } catch(PDOException $e) {
        error_log("❌ Erreur enregistrement historique envoi: " . $e->getMessage());
        return false;
    }
}

// Récupérer l'email de l'utilisateur connecté (émetteur)
$currentUserEmail = '';

// Le système d'auth personnalisé stocke les données dans $_SESSION['user']
if (isset($_SESSION['user'])) {
    // Essayer différents champs possibles pour l'email
    if (isset($_SESSION['user']['email'])) {
        $currentUserEmail = $_SESSION['user']['email'];
    } elseif (isset($_SESSION['user']['courriel'])) {
        $currentUserEmail = $_SESSION['user']['courriel'];
    } elseif (isset($_SESSION['user']['mail'])) {
        $currentUserEmail = $_SESSION['user']['mail'];
    }
    
    // Si toujours vide, essayer de récupérer depuis la base avec le username
    if (empty($currentUserEmail) && isset($_SESSION['user']['username'])) {
        try {
            $sqlUser = "SELECT courriel FROM EPI_benevole WHERE LOWER(nom) = LOWER(:username) LIMIT 1";
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->execute(['username' => $_SESSION['user']['username']]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($userData && !empty($userData['courriel'])) {
                $currentUserEmail = $userData['courriel'];
            }
        } catch(PDOException $e) {
            error_log("Erreur récupération email: " . $e->getMessage());
        }
    }
}

// Si toujours vide, utiliser un email par défaut (À CONFIGURER)
if (empty($currentUserEmail)) {
    // OPTION : Mettre un email fixe ici si besoin
    // $currentUserEmail = 'coordination@entraide-iroise.fr';
}

// Traitement de l'envoi d'email
$emailSent = false;
$emailError = false;
$emailCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $secteur = $_POST['secteur'];
    $subject = $_POST['subject'];
    $selectedBenevoles = isset($_POST['benevoles']) ? $_POST['benevoles'] : [];
    $selectedMissions = isset($_POST['missions']) ? $_POST['missions'] : [];
    
    if (count($selectedBenevoles) > 0 && count($selectedMissions) > 0) {
        try {
            // Récupérer uniquement les missions sélectionnées
            $placeholders = str_repeat('?,', count($selectedMissions) - 1) . '?';
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
                            WHERE m.id_mission IN ($placeholders)
                            AND (m.id_benevole IS NULL OR m.id_benevole = 0)
                            ORDER BY m.date_mission, m.heure_rdv";
            
            $stmtMissions = $conn->prepare($sqlMissions);
            $stmtMissions->execute($selectedMissions);
            $missions = $stmtMissions->fetchAll(PDO::FETCH_ASSOC);
            
            // IMPORTANT: Remplacer 'tondomaine.fr' par ton VRAI domaine partout
            $domaine = $_SERVER['HTTP_HOST']; // Utilise le même domaine que le serveur
            
            // Boundary unique pour multipart
            $boundary = "----=_NextPart_" . md5(uniqid(time()));
            
            // En-têtes améliorés pour éviter les spams
            $headers = "From: Entraide Plus Iroise <noreply@{$domaine}>\r\n";
            $headers .= "Reply-To: Entraide Plus Iroise <contact@{$domaine}>\r\n";
            $headers .= "Return-Path: noreply@{$domaine}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "X-Priority: 3\r\n";
            $headers .= "Message-ID: <" . time() . "-" . md5(uniqid()) . "@{$domaine}>\r\n";
            
            // URL de base pour les inscriptions (à adapter)
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            
            // ⭐ NOUVEAU : Tracker les emails réussis
            $successfulEmails = [];
            
            foreach ($selectedBenevoles as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                
                // Construire le corps de l'email avec les missions
                $missionsHtml = '';
                foreach ($missions as $index => $mission) {
                    $token = generateSecureToken($mission['id_mission'], $email);
                    $inscriptionUrl = $baseUrl . '/inscrire_mission.php?mission=' . $mission['id_mission'] . 
                                     '&email=' . urlencode($email) . '&token=' . $token . 
                                     '&emetteur=' . urlencode($currentUserEmail);
                    
                    $missionsHtml .= '
                    <div style="background: #ffffff; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 20px; border-radius: 8px;">
                        <h3 style="color: #667eea; margin-top: 0;">Mission #' . ($index + 1) . ' - ' . formatDateLong($mission['date_mission']) . '</h3>
                        
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;">
                            <strong style="font-size: 18px;">👤 ' . htmlspecialchars($mission['aide_nom']) . '</strong>
                        </div>
                        
                        <p style="margin: 10px 0;"><strong>📅 Date et heure :</strong> ' . formatDate($mission['date_mission']) . ' à ' . 
                        (!empty($mission['heure_rdv']) ? substr($mission['heure_rdv'], 0, 5) : 'Heure non précisée') . '</p>
                        
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
                
                // Créer la version texte brut
                $textMessage = "NOUVELLES MISSIONS - Secteur : " . $secteur . "\n\n";
                $textMessage .= "Bonjour,\n\n";
                $textMessage .= count($missions) . " nouvelle" . (count($missions) > 1 ? 's' : '') . " mission" . (count($missions) > 1 ? 's' : '') . " ";
                $textMessage .= (count($missions) > 1 ? 'sont' : 'est') . " disponible" . (count($missions) > 1 ? 's' : '') . " sur votre secteur.\n\n";
                
                foreach ($missions as $index => $mission) {
                    $textMessage .= "----------------------------------------\n";
                    $textMessage .= "MISSION #" . ($index + 1) . " - " . formatDateLong($mission['date_mission']) . "\n\n";
                    $textMessage .= "Personne aidée : " . $mission['aide_nom'] . "\n";
                    $textMessage .= "Date : " . formatDate($mission['date_mission']) . " à " . (!empty($mission['heure_rdv']) ? substr($mission['heure_rdv'], 0, 5) : 'Heure non précisée') . "\n";
                    $textMessage .= "Départ : " . $mission['adresse'] . ", " . $mission['commune'] . "\n";
                    
                    if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])) {
                        $textMessage .= "Destination : ";
                        if (!empty($mission['adresse_destination'])) $textMessage .= $mission['adresse_destination'] . ", ";
                        if (!empty($mission['commune_destination'])) $textMessage .= $mission['commune_destination'];
                        $textMessage .= "\n";
                    }
                    
                    if (!empty($mission['nature_intervention'])) {
                        $textMessage .= "Nature : " . $mission['nature_intervention'] . "\n";
                    }
                    
                    if (!empty($mission['commentaires'])) {
                        $textMessage .= "Commentaires : " . $mission['commentaires'] . "\n";
                    }
                    
                    $textMessage .= "Contact : ";
                    if (!empty($mission['tel_fixe'])) $textMessage .= "Fixe: " . formatPhone($mission['tel_fixe']) . " ";
                    if (!empty($mission['tel_portable'])) $textMessage .= "Mobile: " . formatPhone($mission['tel_portable']);
                    if (empty($mission['tel_fixe']) && empty($mission['tel_portable'])) $textMessage .= "Non renseigné";
                    $textMessage .= "\n\n";
                    
                    $token = generateSecureToken($mission['id_mission'], $email);
                    $inscriptionUrl = $baseUrl . '/inscrire_mission.php?mission=' . $mission['id_mission'] . '&email=' . urlencode($email) . '&token=' . $token;
                    $textMessage .= "Pour vous inscrire : " . $inscriptionUrl . "\n\n";
                }
                
                $textMessage .= "----------------------------------------\n";
                $textMessage .= "Merci de votre engagement !\n";
                $textMessage .= "Entraide Plus Iroise\n";
                
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
                            <p style="color: #1a5490; font-weight: bold; margin: 0;">Merci de votre engagement !</p>
                        </div>
                        
                    </div>
                </body>
                </html>';
                
                // Créer le message multipart (texte + HTML)
                $fullMessage = "--{$boundary}\r\n";
                $fullMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $fullMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $fullMessage .= $textMessage . "\r\n\r\n";
                
                $fullMessage .= "--{$boundary}\r\n";
                $fullMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
                $fullMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $fullMessage .= $htmlMessage . "\r\n\r\n";
                
                $fullMessage .= "--{$boundary}--\r\n";
                
                // Forcer le Return-Path pour éviter mail-out.cluster127.hosting.ovh.net
                $additional_params = "-f noreply@{$domaine}";
                
                $mailResult = mail($email, $subject, $fullMessage, $headers, $additional_params);
                
                // ⭐ NOUVEAU : Tracker les emails réussis
                if ($mailResult) {
                    $emailCount++;
                    $successfulEmails[] = $email;
                    error_log("✅ Email envoyé à: " . $email);
                } else {
                    error_log("❌ Échec envoi email à: " . $email);
                }
            }
            
            // ⭐ NOUVEAU : Enregistrer dans l'historique si au moins un email a été envoyé
            if ($emailCount > 0 && count($successfulEmails) > 0) {
                enregistrerHistoriqueEnvoi(
                    $conn, 
                    $currentUserEmail,      // Email de l'émetteur
                    $selectedMissions,      // IDs des missions (array)
                    $successfulEmails,      // Emails des destinataires qui ont reçu (array)
                    $secteur,               // Secteur
                    $subject                // Sujet de l'email
                );
            }
            
            $emailSent = true;
        } catch(Exception $e) {
            $emailError = "Erreur lors de l'envoi : " . $e->getMessage();
        }
    } else {
        if (count($selectedBenevoles) === 0) {
            $emailError = "Aucun bénévole sélectionné.";
        } else if (count($selectedMissions) === 0) {
            $emailError = "Aucune mission sélectionnée.";
        }
    }
}

// Fonction pour récupérer les bénévoles par secteur ou tous (pour AJAX)
if (isset($_GET['get_benevoles'])) {
    header('Content-Type: application/json');
    try {
        if (isset($_GET['all']) && $_GET['all'] === '1') {
            // Tous les bénévoles avec flag_mail = 'O', groupés par email
            $sqlBenevoles = "SELECT 
                                GROUP_CONCAT(nom ORDER BY nom SEPARATOR ', ') as noms,
                                courriel, 
                                GROUP_CONCAT(DISTINCT secteur ORDER BY secteur SEPARATOR ', ') as secteurs,
                                COUNT(*) as nb_benevoles
                            FROM EPI_benevole 
                            WHERE courriel IS NOT NULL AND courriel != '' AND flag_mail='O'
                            GROUP BY courriel
                            ORDER BY noms";
            $stmtBenevoles = $conn->prepare($sqlBenevoles);
            $stmtBenevoles->execute();
        } else if (isset($_GET['secteur'])) {
            // Bénévoles du secteur spécifique, groupés par email
            $sqlBenevoles = "SELECT 
                                GROUP_CONCAT(nom ORDER BY nom SEPARATOR ', ') as noms,
                                courriel,
                                secteur as secteurs,
                                COUNT(*) as nb_benevoles
                            FROM EPI_benevole 
                            WHERE secteur = :secteur AND courriel IS NOT NULL AND courriel != '' AND flag_mail='O'
                            GROUP BY courriel
                            ORDER BY noms";
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

// Fonction pour récupérer les missions d'un secteur (pour AJAX)
if (isset($_GET['get_missions'])) {
    header('Content-Type: application/json');
    try {
        if (isset($_GET['secteur'])) {
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
            $stmtMissions->execute(['secteur' => $_GET['secteur']]);
            $missions = $stmtMissions->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($missions);
        } else {
            echo json_encode(['error' => 'Paramètre secteur manquant']);
        }
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
            border: 2px solid transparent;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .tab.active {
            /* Les couleurs sont appliquées via styles inline par secteur */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            font-weight: 700;
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

        /* Styles pour le tableau des missions */
        .missions-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .missions-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .missions-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .missions-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }

        .missions-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
        }

        .missions-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .missions-table tbody tr:last-child {
            border-bottom: none;
        }

        .missions-table td {
            padding: 12px;
            vertical-align: top;
            font-size: 13px;
        }

        .date-cell {
            white-space: nowrap;
        }

        .date-cell strong {
            color: #667eea;
            font-weight: 600;
        }

        .date-cell .time {
            color: #667eea;
            font-size: 13px;
            font-weight: 600;
        }

        .aide-cell {
            font-weight: 600;
            color: #333;
        }

        .address-cell {
            line-height: 1.5;
        }

        .address-cell .commune {
            color: #666;
            font-size: 12px;
        }

        .destination-cell {
            line-height: 1.5;
        }

        .destination-cell .commune {
            color: #666;
            font-size: 12px;
        }

        .nature-cell {
            line-height: 1.5;
        }

        .nature-badge {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .comments {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
            font-style: italic;
        }

        .contact-cell {
            line-height: 1.6;
            font-size: 12px;
        }

        .not-specified {
            color: #999;
            font-style: italic;
            font-size: 12px;
        }

        .mission-title {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
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
            max-width: 950px;
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
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
        }

        .benevole-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            background: white;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .benevole-item:hover {
            background: #e7f3ff;
            transform: translateX(5px);
        }

        .benevole-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
            accent-color: #667eea;
            flex-shrink: 0;
        }

        .benevole-info {
            flex: 1;
            min-width: 0;
        }

        .benevole-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .benevole-email {
            font-size: 11px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .benevole-secteur {
            font-size: 10px;
            color: #999;
            font-style: italic;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        /* Styles pour la liste des missions */
        .missions-list {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
        }

        .mission-item {
            background: #fafbff;
            border: 3px solid #667eea;
            border-left: 6px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }

        .mission-item:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            transform: translateX(5px);
            border-color: #764ba2;
        }

        .mission-item.selected {
            background: #e7f3ff;
            border-color: #28a745;
            border-left-color: #28a745;
        }

        .mission-checkbox-container {
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .mission-checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 3px;
            cursor: pointer;
            accent-color: #667eea;
            flex-shrink: 0;
        }

        .mission-content {
            flex: 1;
        }

        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .mission-date {
            font-weight: 600;
            color: #667eea;
            font-size: 14px;
        }

        .mission-aide-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 8px;
        }

        .mission-details {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .mission-details strong {
            color: #333;
        }

        .mission-nature {
            background: #fff3cd;
            padding: 6px 10px;
            border-radius: 4px;
            margin-top: 8px;
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            color: #856404;
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

        @media (max-width: 1024px) {
            .benevoles-list {
                grid-template-columns: 1fr 1fr;
            }
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

            .benevoles-list {
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
                    <?php $colors = getSecteurColor($secteur); ?>
                    <button class="tab <?php echo $first ? 'active' : ''; ?>" 
                            onclick="switchTab('<?php echo htmlspecialchars($secteur); ?>')"
                            data-bg-color="<?php echo $colors['bg']; ?>"
                            data-text-color="<?php echo $colors['text']; ?>"
                            style="<?php echo $first ? 'background: ' . $colors['bg'] . '; color: ' . $colors['text'] . '; border: 2px solid ' . $colors['text'] . ';' : ''; ?>">
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
                    <div class="missions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>📅 Date</th>
                                    <th>👤 Personne aidée</th>
                                    <th>📍 Départ</th>
                                    <th>🎯 Destination</th>
                                    <th>📖 Nature</th>
                                    <th>📞 Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($missions as $index => $mission): ?>
                                    <tr>
                                        <td>
                                            <div class="date-cell">
                                                <strong><?php echo formatDate($mission['date_mission']); ?></strong>
                                                <strong class="time"> à <?php echo !empty($mission['heure_rdv']) ? substr($mission['heure_rdv'], 0, 5) : 'heure non précisée'; ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="aide-cell">
                                                <?php echo htmlspecialchars($mission['aide_nom']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="address-cell">
                                                <?php echo htmlspecialchars($mission['adresse']); ?><br>
                                                <span class="commune"><?php echo htmlspecialchars($mission['commune']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="destination-cell">
                                                <?php if (!empty($mission['adresse_destination']) || !empty($mission['commune_destination'])): ?>
                                                    <?php echo !empty($mission['adresse_destination']) ? htmlspecialchars($mission['adresse_destination']) . '<br>' : ''; ?>
                                                    <span class="commune"><?php echo !empty($mission['commune_destination']) ? htmlspecialchars($mission['commune_destination']) : ''; ?></span>
                                                <?php else: ?>
                                                    <span class="not-specified">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="nature-cell">
                                                <?php if (!empty($mission['nature_intervention'])): ?>
                                                    <span class="nature-badge"><?php echo htmlspecialchars($mission['nature_intervention']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-specified">-</span>
                                                <?php endif; ?>
                                                <?php if (!empty($mission['commentaires'])): ?>
                                                    <div class="comments">💬 <?php echo htmlspecialchars($mission['commentaires']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-cell">
                                                <?php if (!empty($mission['tel_fixe'])): ?>
                                                    📞 <?php echo formatPhone($mission['tel_fixe']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($mission['tel_portable'])): ?>
                                                    📱 <?php echo formatPhone($mission['tel_portable']); ?>
                                                <?php endif; ?>
                                                <?php if (empty($mission['tel_fixe']) && empty($mission['tel_portable'])): ?>
                                                    <span class="not-specified">Non renseigné</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
            
            <?php if (!empty($currentUserEmail)): ?>
            <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; color: #0d47a1; font-size: 13px;">
                <strong style="color: #1976d2;">💡 Information :</strong>
                Vous recevrez une copie de chaque confirmation d'inscription à : 
                <strong><?php echo htmlspecialchars($currentUserEmail); ?></strong>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="emailForm">
                <input type="hidden" name="send_email" value="1">
                <input type="hidden" name="secteur" id="modal-secteur" value="">
                
                <div class="form-group">
                    <label style="display: inline; margin-right: 10px;">Secteur concerné :</label>
                    <span style="color: #667eea; font-weight: bold; font-size: 16px;" id="modal-secteur-display"></span>
                </div>

                <div class="form-group">
                    <label>📋 Sélectionner les missions à envoyer :</label>
                    <div id="missions-container">
                        <div class="loading-spinner">
                            <p>⏳ Chargement des missions...</p>
                        </div>
                    </div>
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
            // Retirer la classe active et réinitialiser le style de tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
                // Retirer les styles inline pour revenir au style par défaut (gris)
                tab.style.background = '';
                tab.style.color = '';
                tab.style.border = '';
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet cliqué et appliquer ses couleurs
            const activeTab = event.target;
            activeTab.classList.add('active');
            const bgColor = activeTab.getAttribute('data-bg-color');
            const textColor = activeTab.getAttribute('data-text-color');
            activeTab.style.background = bgColor;
            activeTab.style.color = textColor;
            activeTab.style.border = '2px solid ' + textColor;
            
            document.getElementById('tab-' + secteur).classList.add('active');
        }

        function openEmailModal(secteur, nbMissions) {
            const modal = document.getElementById('emailModal');
            currentSecteur = secteur;
            document.getElementById('modal-secteur').value = secteur;
            document.getElementById('modal-secteur-display').textContent = secteur + ' (' + nbMissions + ' mission' + (nbMissions > 1 ? 's' : '') + ')';
            
            // Réinitialiser à "secteur uniquement"
            document.getElementById('secteur_only').checked = true;
            
            // Charger les missions du secteur
            loadMissions(secteur);
            
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

        function loadMissions(secteur) {
            const container = document.getElementById('missions-container');
            container.innerHTML = '<div class="loading-spinner"><p>⏳ Chargement des missions...</p></div>';
            
            fetch('?get_missions=1&secteur=' + encodeURIComponent(secteur))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = '<div class="no-benevoles"><p>❌ Erreur: ' + data.error + '</p></div>';
                        return;
                    }
                    
                    if (data.length === 0) {
                        container.innerHTML = '<div class="no-benevoles"><p>Aucune mission trouvée.</p></div>';
                        return;
                    }
                    
                    let html = '<div class="select-all-container">';
                    html += '<span class="benevole-count">' + data.length + ' mission' + (data.length > 1 ? 's' : '') + ' disponible' + (data.length > 1 ? 's' : '') + '</span>';
                    html += '<button type="button" class="select-all-btn" onclick="toggleSelectAllMissions()">✓ Tout sélectionner</button>';
                    html += '</div>';
                    html += '<div class="missions-list">';
                    
                    data.forEach((mission, index) => {
                        const dateObj = new Date(mission.date_mission);
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        const dateFr = dateObj.toLocaleDateString('fr-FR', options);
                        
                        html += '<label class="mission-item">';
                        html += '<div class="mission-checkbox-container">';
                        html += '<input type="checkbox" name="missions[]" value="' + mission.id_mission + '">';
                        html += '<div class="mission-content">';
                        html += '<div class="mission-header">';
                        html += '<div class="mission-date">📅 ' + dateFr.charAt(0).toUpperCase() + dateFr.slice(1) + '</div>';
                        html += '</div>';
                        html += '<div class="mission-aide-name">👤 ' + mission.aide_nom + '</div>';
                        html += '<div class="mission-details">';
                        html += '<strong>⏰ Heure:</strong> ' + (mission.heure_rdv ? mission.heure_rdv.substring(0, 5) : 'Non précisée') + '<br>';
                        html += '<strong>📍 Départ:</strong> ' + mission.adresse + ', ' + mission.commune + '<br>';
                        
                        if (mission.adresse_destination || mission.commune_destination) {
                            html += '<strong>🎯 Destination:</strong> ';
                            if (mission.adresse_destination) html += mission.adresse_destination;
                            if (mission.commune_destination) html += (mission.adresse_destination ? ', ' : '') + mission.commune_destination;
                            html += '<br>';
                        }
                        
                        if (mission.nature_intervention) {
                            html += '<div class="mission-nature">📖 ' + mission.nature_intervention + '</div>';
                        }
                        
                        // Ajouter les téléphones
                        html += '<div style="margin-top: 8px; font-size: 12px; color: #666;">';
                        html += '<strong>📞 Contact:</strong> ';
                        let contacts = [];
                        if (mission.tel_fixe) {
                            contacts.push('Fixe: ' + mission.tel_fixe);
                        }
                        if (mission.tel_portable) {
                            contacts.push('Mobile: ' + mission.tel_portable);
                        }
                        if (contacts.length > 0) {
                            html += contacts.join(' | ');
                        } else {
                            html += 'Non renseigné';
                        }
                        html += '</div>';
                        
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                        html += '</label>';
                    });
                    
                    html += '</div>';
                    container.innerHTML = html;
                    
                    // Ajouter l'événement click sur les labels
                    document.querySelectorAll('.mission-item').forEach(item => {
                        item.addEventListener('click', function(e) {
                            if (e.target.tagName !== 'INPUT') {
                                const checkbox = this.querySelector('input[type="checkbox"]');
                                checkbox.checked = !checkbox.checked;
                            }
                            this.classList.toggle('selected', this.querySelector('input[type="checkbox"]').checked);
                        });
                    });
                })
                .catch(error => {
                    container.innerHTML = '<div class="no-benevoles"><p>❌ Erreur de chargement: ' + error + '</p></div>';
                });
        }

        function toggleSelectAllMissions() {
            const checkboxes = document.querySelectorAll('.missions-list input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const btn = event.target;
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
                cb.closest('.mission-item').classList.toggle('selected', !allChecked);
            });
            
            btn.textContent = allChecked ? '✓ Tout sélectionner' : '✗ Tout désélectionner';
        }

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
                    
                    // Compter le nombre total de bénévoles (en additionnant nb_benevoles)
                    const totalBenevoles = data.reduce((sum, item) => sum + parseInt(item.nb_benevoles || 1), 0);
                    const nbEmails = data.length;
                    
                    let html = '<div class="select-all-container">';
                    html += '<span class="benevole-count">' + totalBenevoles + ' bénévole' + (totalBenevoles > 1 ? 's' : '') + ' (' + nbEmails + ' email' + (nbEmails > 1 ? 's' : '') + ')</span>';
                    html += '<button type="button" class="select-all-btn" onclick="toggleSelectAll()">✓ Tout sélectionner</button>';
                    html += '</div>';
                    html += '<div class="benevoles-list">';
                    
                    data.forEach(benevole => {
                        html += '<label class="benevole-item">';
                        html += '<input type="checkbox" name="benevoles[]" value="' + benevole.courriel + '">';
                        html += '<div class="benevole-info">';
                        // Afficher les noms groupés (peut être plusieurs noms séparés par des virgules)
                        html += '<div class="benevole-name">' + benevole.noms + '</div>';
                        html += '<div class="benevole-email">' + benevole.courriel + '</div>';
                        if (loadAll && benevole.secteurs) {
                            html += '<div class="benevole-secteur">Secteur: ' + benevole.secteurs + '</div>';
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
            const checkedMissions = document.querySelectorAll('.missions-list input[type="checkbox"]:checked');
            const checkedBenevoles = document.querySelectorAll('.benevoles-list input[type="checkbox"]:checked');
            
            if (checkedMissions.length === 0) {
                e.preventDefault();
                alert('⚠️ Veuillez sélectionner au moins une mission à envoyer.');
                return false;
            }
            
            if (checkedBenevoles.length === 0) {
                e.preventDefault();
                alert('⚠️ Veuillez sélectionner au moins un bénévole destinataire.');
                return false;
            }
            
            const confirmMsg = `📧 Confirmer l'envoi de ${checkedMissions.length} mission${checkedMissions.length > 1 ? 's' : ''} à ${checkedBenevoles.length} bénévole${checkedBenevoles.length > 1 ? 's' : ''} ?`;
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