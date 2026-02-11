<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

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

// Récupérer les bénévoles pour la liste déroulante
$benevoles = [];
try {
    $stmt = $conn->query("SELECT id_benevole, nom FROM EPI_benevole ORDER BY nom");
    $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Récupérer les aidés pour la liste déroulante
$aides = [];
try {
    $stmt = $conn->query("SELECT id_aide, nom, adresse, code_postal, commune, secteur, tel_fixe, tel_portable FROM EPI_aide ORDER BY nom");
    $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Renommer les clés pour la compatibilité avec le JavaScript
    foreach($aides as &$aide) {
        $aide['telephone'] = isset($aide['tel_fixe']) ? $aide['tel_fixe'] : '';
        $aide['telephone_2'] = isset($aide['tel_portable']) ? $aide['tel_portable'] : '';
    }
} catch(PDOException $e) {}

// Récupérer les types d'intervention depuis la table EPI_intervention
$interventions = [];
try {
    $stmt = $conn->query("SELECT Nature_intervention FROM EPI_intervention ORDER BY Nature_intervention");
    $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Récupérer les villes depuis la table EPI_ville
$villes = [];
try {
    $stmt = $conn->query("SELECT ville, cp, secteur FROM EPI_ville ORDER BY ville");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Fonction pour nettoyer les backslashes multiples qui peuvent s'accumuler
        function cleanBackslashes($value) {
            if (is_null($value) || $value === '') {
                return $value;
            }
            // Nettoyer les multiples backslashes consécutifs
            $cleaned = $value;
            // Remplacer \\\\ par \\, puis \\ par \ jusqu'à ce qu'il n'y ait plus de doublons
            while (strpos($cleaned, '\\\\') !== false) {
                $cleaned = str_replace('\\\\', '\\', $cleaned);
            }
            // Puis retirer les backslashes d'échappement restants
            $cleaned = stripslashes($cleaned);
            return $cleaned;
        }
        
        // DEBUG: Afficher les données POST pour vérifier
        // Décommentez ces lignes pour déboguer
        // echo '<pre>'; print_r($_POST); echo '</pre>'; exit();
        
        $sql = "INSERT INTO EPI_mission (date_mission, heure_rdv, 
                id_benevole, benevole, adresse_benevole, cp_benevole, commune_benevole, secteur_benevole, 
                id_aide, aide, adresse_aide, cp_aide, commune_aide, secteur_aide, 
                adresse_destination, cp_destination, commune_destination, 
                nature_intervention, commentaires, email_createur, date_cre) 
                VALUES (:date_mission, :heure_rdv, 
                :id_benevole, :benevole, :adresse_benevole, :cp_benevole, :commune_benevole, :secteur_benevole, 
                :id_aide, :aide, :adresse_aide, :cp_aide, :commune_aide, :secteur_aide, 
                :adresse_destination, :cp_destination, :commune_destination, 
                :nature_intervention, :commentaires, :email_createur, NOW())";
        
        $stmt = $conn->prepare($sql);
        
        // Préparer les données en nettoyant les backslashes
        $data = [
            ':date_mission' => !empty($_POST['date_mission']) ? $_POST['date_mission'] : null,
            ':heure_rdv' => !empty($_POST['heure_rdv']) ? $_POST['heure_rdv'] : null,
            ':id_benevole' => !empty($_POST['id_benevole']) ? $_POST['id_benevole'] : null,
            ':benevole' => !empty($_POST['benevole']) ? cleanBackslashes($_POST['benevole']) : null,
            ':adresse_benevole' => !empty($_POST['adresse_benevole']) ? cleanBackslashes($_POST['adresse_benevole']) : null,
            ':cp_benevole' => !empty($_POST['cp_benevole']) ? cleanBackslashes($_POST['cp_benevole']) : null,
            ':commune_benevole' => !empty($_POST['commune_benevole']) ? cleanBackslashes($_POST['commune_benevole']) : null,
            ':secteur_benevole' => !empty($_POST['secteur_benevole']) ? cleanBackslashes($_POST['secteur_benevole']) : null,
            ':id_aide' => !empty($_POST['id_aide']) ? $_POST['id_aide'] : null,
            ':aide' => !empty($_POST['aide']) ? cleanBackslashes($_POST['aide']) : null,
            ':adresse_aide' => !empty($_POST['adresse_aide']) ? cleanBackslashes($_POST['adresse_aide']) : null,
            ':cp_aide' => !empty($_POST['cp_aide']) ? cleanBackslashes($_POST['cp_aide']) : null,
            ':commune_aide' => !empty($_POST['commune_aide']) ? cleanBackslashes($_POST['commune_aide']) : null,
            ':secteur_aide' => !empty($_POST['secteur_aide']) ? cleanBackslashes($_POST['secteur_aide']) : null,
            ':adresse_destination' => !empty($_POST['adresse_destination']) ? cleanBackslashes($_POST['adresse_destination']) : null,
            ':cp_destination' => !empty($_POST['cp_destination']) ? cleanBackslashes($_POST['cp_destination']) : null,
            ':commune_destination' => !empty($_POST['commune_destination']) ? cleanBackslashes($_POST['commune_destination']) : null,
            ':nature_intervention' => !empty($_POST['nature_intervention']) ? cleanBackslashes($_POST['nature_intervention']) : null,
            ':commentaires' => !empty($_POST['commentaires']) ? cleanBackslashes($_POST['commentaires']) : null,
            ':email_createur' => isset($_SESSION['user']['email']) ? cleanBackslashes($_SESSION['user']['email']) : null
        ];
        
        // DEBUG: Afficher les données préparées
        // Décommentez ces lignes pour déboguer
        // echo '<pre>Données envoyées à la BDD:'; print_r($data); echo '</pre>'; exit();
        
        $stmt->execute($data);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
        
    } catch(PDOException $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg);
        exit();
    }
}

// Messages
if (isset($_GET['success'])) {
    $message = "✅ Mission créée avec succès !";
    $messageType = "success";
} elseif (isset($_GET['error'])) {
    $message = "❌ Erreur : " . urldecode($_GET['error']);
    $messageType = "error";
}

$dateJour = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Mission</title>
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
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }

        .back-link:active {
            transform: scale(0.95);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container {
            animation: fadeInUp 0.6s ease-out;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 30px;
            max-width: 1000px;
            width: 100%;
            margin: 80px auto 20px;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
            font-size: clamp(20px, 5vw, 24px);
        }

        h3 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: clamp(14px, 4vw, 16px);
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }

        h3:first-of-type {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: clamp(12px, 3vw, 13px);
        }

        label .optional {
            color: #999;
            font-weight: normal;
            font-size: 0.9em;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: inherit;
            -webkit-appearance: none;
            appearance: none;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select {
            cursor: pointer;
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
            cursor: not-allowed;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 0;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 15px;
            touch-action: manipulation;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            font-size: clamp(13px, 3vw, 14px);
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .info-box {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
            margin-bottom: 15px;
            font-size: clamp(12px, 3vw, 13px);
            color: #1565c0;
        }

        #info_benevole, #info_aide {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            word-break: break-word;
        }

        #info_benevole strong, #info_aide strong {
            color: #667eea;
            font-size: clamp(12px, 3vw, 13px);
        }

        #affichage_adresse_benevole, #affichage_adresse_aide {
            font-size: clamp(12px, 3vw, 13px);
        }

        @media (max-width: 768px) {
            .back-link {
                width: 50px;
                height: 50px;
                font-size: 20px;
                top: 20px;
                left: 20px;
            }

            .container {
                padding: 20px;
                margin-top: 90px;
                border-radius: 15px;
            }

            .row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 18px;
            }

            h3 {
                margin-top: 15px;
                margin-bottom: 10px;
            }

            input, select, textarea {
                padding: 10px;
                font-size: 16px !important;
            }

            .btn-submit {
                padding: 12px;
                font-size: 15px;
            }
        }

        @media (max-width: 360px) {
            .container {
                padding: 12px;
            }

            h1 {
                font-size: 16px;
            }

            input, select, textarea {
                padding: 8px;
            }
        }

        @media (hover: none) and (pointer: coarse) {
            .btn-submit, .back-link, select {
                min-height: 44px;
            }
			
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

		
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link" >🏠</a>

    <div class="container">
        <h1>🚗 Nouvelle Mission</h1>
        
        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="missionForm">
            <!-- 1. AIDÉ -->
            <h3>🤝 Aidé</h3>
            <div class="form-group">
                <label for="id_aide">Sélectionner un aidé *</label>
                <select id="id_aide" name="id_aide" required onchange="chargerInfosAide(this.value)">
                    <option value="">-- Choisissez un aidé --</option>
                    <?php foreach($aides as $a): ?>
                        <option value="<?php echo $a['id_aide']; ?>"
                                data-nom="<?php echo htmlspecialchars($a['nom']); ?>"
                                data-adresse="<?php echo htmlspecialchars($a['adresse']); ?>"
                                data-cp="<?php echo htmlspecialchars($a['code_postal']); ?>"
                                data-commune="<?php echo htmlspecialchars($a['commune']); ?>"
                                data-secteur="<?php echo htmlspecialchars($a['secteur']); ?>"
                                data-telephone="<?php echo htmlspecialchars($a['telephone']); ?>"
                                data-telephone2="<?php echo htmlspecialchars($a['telephone_2']); ?>">
                            <?php echo htmlspecialchars($a['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Affichage des informations de l'aidé -->
            <div id="infos_aide_display" style="display: none; margin-top: -10px; margin-bottom: 20px; padding: 15px; background-color: #f0f8ff; border-left: 4px solid #667eea; border-radius: 4px;">
                <div id="aide_adresse_display" style="color: #555; margin-bottom: 8px;"></div>
                <div id="aide_telephones_display" style="color: #555;"></div>
            </div>

            <input type="hidden" id="aide" name="aide">
            <input type="hidden" id="adresse_aide" name="adresse_aide">
            <input type="hidden" id="cp_aide" name="cp_aide">
            <input type="hidden" id="commune_aide" name="commune_aide">
            <input type="hidden" id="secteur_aide" name="secteur_aide">

            <div id="info_aide" style="display:none;">
                <strong>📍 Adresse de l'aidé :</strong>
                <p id="affichage_adresse_aide" style="margin-top: 5px; color: #666;"></p>
            </div>

            <!-- 2. DÉTAILS DE L'INTERVENTION -->
            <h3>📋 Détails de l'intervention</h3>
            <div class="row">
                <div class="form-group">
                    <label for="date_mission">Date de la mission *</label>
                    <input type="date" id="date_mission" name="date_mission" required value="<?php echo $dateJour; ?>">
                </div>
                <div class="form-group">
                    <label for="heure_rdv">Heure de rendez-vous</label>
                    <input type="time" id="heure_rdv" name="heure_rdv">
                </div>
            </div>

            <div class="form-group">
                <label for="nature_intervention">Type d'intervention *</label>
                <select id="nature_intervention" name="nature_intervention" required>
                    <option value="">-- Choisissez un type --</option>
                    <?php foreach($interventions as $i): ?>
                        <option value="<?php echo htmlspecialchars($i['Nature_intervention']); ?>">
                            <?php echo htmlspecialchars($i['Nature_intervention']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. DESTINATION -->
            <h3>📍 Destination</h3>
            <div class="form-group">
                <label for="adresse_destination">Adresse de destination</label>
                <input type="text" id="adresse_destination" name="adresse_destination" 
                       placeholder="Ex: 1 Place Alexis Ricordeau">
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="commune_destination">Ville *</label>
                    <select id="commune_destination" name="commune_destination" required onchange="remplirCPDestination()">
                        <option value="">-- Choisissez une ville --</option>
                        <?php foreach($villes as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['ville']); ?>" 
                                    data-cp="<?php echo htmlspecialchars($v['cp']); ?>"
                                    data-secteur="<?php echo htmlspecialchars($v['secteur']); ?>">
                                <?php echo htmlspecialchars($v['ville']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cp_destination">Code postal</label>
                    <input type="text" id="cp_destination" name="cp_destination" readonly>
                </div>
            </div>

            <!-- 4. BÉNÉVOLE (FACULTATIF) -->
            <h3>👤 Bénévole <span class="optional">(facultatif)</span></h3>
            <div class="info-box">
                ℹ️ Le bénévole peut être assigné plus tard si nécessaire
            </div>
            
            <div class="form-group">
                <label for="id_benevole">Sélectionner un bénévole <span class="optional">(optionnel)</span></label>
                <select id="id_benevole" name="id_benevole" onchange="chargerInfosBenevole(this.value)">
                    <option value="">-- Aucun bénévole assigné --</option>
                    <?php foreach($benevoles as $b): ?>
                        <option value="<?php echo $b['id_benevole']; ?>" 
                                data-nom="<?php echo htmlspecialchars($b['nom']); ?>">
                            <?php echo htmlspecialchars($b['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" id="benevole" name="benevole">
            <input type="hidden" id="adresse_benevole" name="adresse_benevole">
            <input type="hidden" id="cp_benevole" name="cp_benevole">
            <input type="hidden" id="commune_benevole" name="commune_benevole">
            <input type="hidden" id="secteur_benevole" name="secteur_benevole">

            <div id="info_benevole" style="display:none;">
                <strong>📍 Adresse du bénévole :</strong>
                <p id="affichage_adresse_benevole" style="margin-top: 5px; color: #666;"></p>
            </div>

            <!-- 5. COMMENTAIRES -->
            <h3>💬 Commentaires <span class="optional">(optionnel)</span></h3>
            <div class="form-group">
                <label for="commentaires">Informations complémentaires</label>
                <textarea id="commentaires" name="commentaires" 
                          placeholder="Ex: Personne à mobilité réduite, nécessite un fauteuil roulant..."></textarea>
            </div>

            <button type="submit" class="btn-submit">💾 Enregistrer la mission</button>
        </form>
    </div>

    <script>
        const benevolesData = <?php echo json_encode($benevoles); ?>;
        const aidesData = <?php echo json_encode($aides); ?>;
        const villesData = <?php echo json_encode($villes); ?>;
        
        let benevoleDataLoading = false;
        let aideDataLoading = false;

        function remplirCPDestination() {
            const select = document.getElementById('commune_destination');
            const option = select.options[select.selectedIndex];
            const cp = option.dataset.cp;
            document.getElementById('cp_destination').value = cp || '';
        }

        async function chargerInfosBenevole(id) {
            if (!id) {
                document.getElementById('info_benevole').style.display = 'none';
                document.getElementById('benevole').value = '';
                document.getElementById('adresse_benevole').value = '';
                document.getElementById('cp_benevole').value = '';
                document.getElementById('commune_benevole').value = '';
                document.getElementById('secteur_benevole').value = '';
                benevoleDataLoading = false;
                return;
            }

            benevoleDataLoading = true;

            // Récupérer les données depuis les attributs data- de l'option sélectionnée
            const select = document.getElementById('id_benevole');
            const option = select.options[select.selectedIndex];
            const nom = option.dataset.nom || '';

            try {
                const response = await fetch('get_benevole.php?id=' + id);
                const data = await response.json();
                
                if (data.success) {
                    // CORRECTION: Remplir tous les champs cachés avec les données du bénévole
                    document.getElementById('benevole').value = data.nom || nom;
                    document.getElementById('adresse_benevole').value = data.adresse || '';
                    document.getElementById('cp_benevole').value = data.code_postal || '';
                    document.getElementById('commune_benevole').value = data.commune || '';
                    document.getElementById('secteur_benevole').value = data.secteur || '';
                    
                    const adresseComplete = (data.adresse || '') + ', ' + 
                                          (data.code_postal || '') + ' ' + 
                                          (data.commune || '');
                    document.getElementById('affichage_adresse_benevole').textContent = adresseComplete;
                    document.getElementById('info_benevole').style.display = 'block';
                } else {
                    const select = document.getElementById('id_benevole');
                    const option = select.options[select.selectedIndex];
                    document.getElementById('benevole').value = option.dataset.nom;
                    // En cas d'échec de l'API, on ne peut pas remplir les autres champs
                    document.getElementById('adresse_benevole').value = '';
                    document.getElementById('cp_benevole').value = '';
                    document.getElementById('commune_benevole').value = '';
                    document.getElementById('secteur_benevole').value = '';
                }
            } catch (error) {
                console.error('Erreur:', error);
                const select = document.getElementById('id_benevole');
                const option = select.options[select.selectedIndex];
                document.getElementById('benevole').value = option.dataset.nom;
                // En cas d'erreur, on ne peut pas remplir les autres champs
                document.getElementById('adresse_benevole').value = '';
                document.getElementById('cp_benevole').value = '';
                document.getElementById('commune_benevole').value = '';
                document.getElementById('secteur_benevole').value = '';
            } finally {
                benevoleDataLoading = false;
            }
        }

        async function chargerInfosAide(id) {
            if (!id) {
                document.getElementById('info_aide').style.display = 'none';
                document.getElementById('infos_aide_display').style.display = 'none';
                aideDataLoading = false;
                return;
            }

            aideDataLoading = true;

            // Récupérer les données depuis les attributs data- de l'option sélectionnée
            const select = document.getElementById('id_aide');
            const option = select.options[select.selectedIndex];
            
            const nom = option.dataset.nom || '';
            const adresse = option.dataset.adresse || '';
            const cp = option.dataset.cp || '';
            const commune = option.dataset.commune || '';
            const secteur = option.dataset.secteur || '';
            const telephone = option.dataset.telephone || '';
            const telephone2 = option.dataset.telephone2 || '';

            try {
                const response = await fetch('get_aide.php?id=' + id);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('aide').value = data.nom || nom;
                    document.getElementById('adresse_aide').value = data.adresse || adresse;
                    document.getElementById('cp_aide').value = data.code_postal || cp;
                    document.getElementById('commune_aide').value = data.commune || commune;
                    document.getElementById('secteur_aide').value = data.secteur || secteur;
                    
                    const adresseComplete = (data.adresse || adresse) + ', ' + 
                                          (data.code_postal || cp) + ' ' + 
                                          (data.commune || commune);
                    document.getElementById('affichage_adresse_aide').textContent = adresseComplete;
                    document.getElementById('info_aide').style.display = 'block';
                    
                    // Afficher l'adresse complète avec CP et commune
                    const adresseAffichage = (data.adresse || adresse) + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage) {
                        document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    // Afficher les téléphones avec des icônes distinctes
                    let telephonesHTML = '';
                    if (telephone) {
                        telephonesHTML = '☎️ Fixe : <strong>' + telephone + '</strong>';
                    }
                    if (telephone2) {
                        telephonesHTML += (telephone ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + telephone2 + '</strong>';
                    }
                    if (telephonesHTML) {
                        document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_aide_display').style.display = 'block';
                    }
                } else {
                    document.getElementById('aide').value = nom;
                    document.getElementById('adresse_aide').value = adresse;
                    document.getElementById('cp_aide').value = cp;
                    document.getElementById('commune_aide').value = commune;
                    document.getElementById('secteur_aide').value = secteur;
                    
                    // Afficher l'adresse et téléphones même en cas d'erreur API
                    const adresseAffichage = adresse + 
                                           (cp ? ', ' + cp : '') + 
                                           (commune ? ' ' + commune : '');
                    if (adresseAffichage) {
                        document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                    }
                    
                    let telephonesHTML = '';
                    if (telephone) {
                        telephonesHTML = '☎️ Fixe : <strong>' + telephone + '</strong>';
                    }
                    if (telephone2) {
                        telephonesHTML += (telephone ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                         '📱 Portable : <strong>' + telephone2 + '</strong>';
                    }
                    if (telephonesHTML) {
                        document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                        document.getElementById('infos_aide_display').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('aide').value = nom;
                document.getElementById('adresse_aide').value = adresse;
                document.getElementById('cp_aide').value = cp;
                document.getElementById('commune_aide').value = commune;
                document.getElementById('secteur_aide').value = secteur;
                
                // Afficher l'adresse et téléphones depuis les data-attributes
                const adresseAffichage = adresse + 
                                       (cp ? ', ' + cp : '') + 
                                       (commune ? ' ' + commune : '');
                if (adresseAffichage) {
                    document.getElementById('aide_adresse_display').innerHTML = '📍 ' + adresseAffichage;
                }
                
                let telephonesHTML = '';
                if (telephone) {
                    telephonesHTML = '☎️ Fixe : <strong>' + telephone + '</strong>';
                }
                if (telephone2) {
                    telephonesHTML += (telephone ? '<span style="margin: 0 10px;">|</span>' : '') + 
                                     '📱 Portable : <strong>' + telephone2 + '</strong>';
                }
                if (telephonesHTML) {
                    document.getElementById('aide_telephones_display').innerHTML = telephonesHTML;
                    document.getElementById('infos_aide_display').style.display = 'block';
                }
            } finally {
                aideDataLoading = false;
            }
        }

        // Intercepter la soumission du formulaire
        document.getElementById('missionForm').addEventListener('submit', async function(e) {
            // Si des données sont en cours de chargement, empêcher la soumission
            if (benevoleDataLoading || aideDataLoading) {
                e.preventDefault();
                
                // Désactiver temporairement le bouton
                const submitBtn = this.querySelector('.btn-submit');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Chargement des données...';
                
                // Attendre que les données soient chargées
                const checkInterval = setInterval(() => {
                    if (!benevoleDataLoading && !aideDataLoading) {
                        clearInterval(checkInterval);
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        // Soumettre le formulaire
                        this.submit();
                    }
                }, 100);
            }
        });
    </script>
</body>
</html>
