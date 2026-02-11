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

// Récupérer les données pour les listes déroulantes
$secteurs = [];
try {
    $stmt = $conn->query("SELECT DISTINCT secteur FROM benevole WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur");
    $secteurs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Villes depuis la table EPI_ville
$villes = [];
try {
    $stmt = $conn->query("SELECT ville, cp, secteur FROM EPI_ville ORDER BY ville");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Plannings (créneaux horaires) depuis la table EPI_planning
$plannings = [];
try {
    $stmt = $conn->query("SELECT jours FROM EPI_planning");
    $plannings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Types de véhicules
$type_vehicule = [];
try {
    $stmt = $conn->query("SELECT type_vehicule FROM EPI_vehicule");
    $type_vehicule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Récupérer les moyens de paiement depuis la table EPI_paiement
$moyensPaiement = [];
try {
    $stmt = $conn->query("SELECT DISTINCT type_paiement FROM EPI_paiement WHERE type_paiement IS NOT NULL AND type_paiement != '' ORDER BY type_paiement");
    $moyensPaiement = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    error_log("Erreur récupération moyens paiement: " . $e->getMessage());
}

// Si la table est vide, utiliser des valeurs par défaut
if (empty($moyensPaiement)) {
    $moyensPaiement = ['Espèces', 'Chèque', 'Virement', 'Carte bancaire'];
}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Formatage du nom : NOM en majuscules, prénom(s) en minuscules avec initiale en majuscule
        $nomComplet = $_POST['nom'];
        if (strpos($nomComplet, ' ') !== false) {
            $premierEspace = strpos($nomComplet, ' ');
            $nom = substr($nomComplet, 0, $premierEspace);
            $prenoms = substr($nomComplet, $premierEspace + 1);
            // Mettre le nom en majuscules et le prénom en minuscules avec initiale en majuscule
            $prenoms = ucfirst(strtolower($prenoms));
            $nomComplet = strtoupper($nom) . ' ' . $prenoms;
        }
        
        $sql = "INSERT INTO EPI_benevole (nom, date_naissance, adresse, code_postal, commune, 
                tel_fixe, tel_mobile, courriel, secteur, commentaires, debut, fin, 
                lundi, mardi, mercredi, jeudi, vendredi, immatriculation, chevaux_fiscaux, 
                type, flag_mail, dons, date_2, observations_2) 
                VALUES (:nom, :date_naissance, :adresse, :code_postal, :commune, 
                :tel_fixe, :tel_mobile, :courriel, :secteur, :commentaires, :debut, :fin, 
                :lundi, :mardi, :mercredi, :jeudi, :vendredi, :immatriculation, :chevaux_fiscaux, 
                :type, :flag_mail, :dons, :date_2, :observations_2)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':nom', $nomComplet);
        $stmt->bindParam(':date_naissance', $_POST['date_naissance']);
        $stmt->bindParam(':adresse', $_POST['adresse']);
        $stmt->bindParam(':code_postal', $_POST['code_postal']);
        $stmt->bindParam(':commune', $_POST['commune']);
        $stmt->bindParam(':tel_fixe', $_POST['tel_fixe']);
        $stmt->bindParam(':tel_mobile', $_POST['tel_mobile']);
        $stmt->bindParam(':courriel', $_POST['courriel']);
        $stmt->bindParam(':secteur', $_POST['secteur']);
        $stmt->bindParam(':commentaires', $_POST['commentaires']);
        $stmt->bindParam(':debut', $_POST['debut']);
        $stmt->bindParam(':fin', $_POST['fin']);
        $stmt->bindParam(':lundi', $_POST['lundi']);
        $stmt->bindParam(':mardi', $_POST['mardi']);
        $stmt->bindParam(':mercredi', $_POST['mercredi']);
        $stmt->bindParam(':jeudi', $_POST['jeudi']);
        $stmt->bindParam(':vendredi', $_POST['vendredi']);
        $stmt->bindParam(':immatriculation', $_POST['immatriculation']);
        $stmt->bindParam(':chevaux_fiscaux', $_POST['chevaux_fiscaux']);
        $stmt->bindParam(':type', $_POST['type']);
        $stmt->bindParam(':flag_mail', $_POST['flag_mail']);
        $stmt->bindParam(':dons', $_POST['dons']);
        $stmt->bindParam(':date_2', $_POST['date_2']);
        $stmt->bindParam(':observations_2', $_POST['observations_2']);
        
        $stmt->execute();
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
        
    } catch(PDOException $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg);
        exit();
    }
}

// Gestion des messages après redirection
if (isset($_GET['success'])) {
    $message = "✅ Bénévole ajouté avec succès !";
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
    <title>Nouveau Bénévole - Formulaire Complet</title>
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
            max-width: 900px;
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
            margin-top: 20px;
            margin-bottom: 15px;
            font-size: clamp(14px, 4vw, 16px);
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
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

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
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

        input:focus,
        select:focus,
        textarea:focus {
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

        .disponibilites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .disponibilites-grid .form-group {
            margin-bottom: 0;
        }

        .disponibilites-grid label {
            font-size: clamp(11px, 2.5vw, 12px);
        }

        .disponibilites-grid select {
            font-size: 15px;
            padding: 10px 30px 10px 10px;
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
            margin-top: 8px;
            touch-action: manipulation;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .field-hint {
            font-size: clamp(11px, 2.5vw, 12px);
            color: #666;
            margin-top: 3px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body {
                padding: 10px;
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
            
            .container {
                padding: 20px;
                margin: 0 auto;
                border-radius: 15px;
            }
            
            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .disponibilites-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 20px;
            }

            h3 {
                font-size: 15px;
                margin-top: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 5px;
            }

            .container {
                padding: 15px;
                border-radius: 12px;
            }

            h1 {
                font-size: 18px;
                margin-bottom: 15px;
            }

            h3 {
                font-size: 14px;
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

            .message {
                padding: 10px;
                font-size: 13px;
            }
        }

        @media (max-width: 360px) {
            .container {
                padding: 12px;
            }

            h1 {
                font-size: 16px;
            }

            h3 {
                font-size: 13px;
            }

            input, select, textarea {
                padding: 8px;
            }

            .btn-submit {
                padding: 10px;
            }
        }

        @media (hover: none) and (pointer: coarse) {
            .btn-submit, .back-link, select {
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">🏠</a>

    <div class="container">
        <h1>👤 Nouveau Bénévole </h1>
        
        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nom">NOM et Prénom du bénévole *</label>
                <input type="text" id="nom" name="nom" required placeholder="">
                <div class="field-hint"></div>
            </div>

            <div class="form-group">
                <label for="date_naissance">Date de naissance *</label>
                <input type="date" id="date_naissance" name="date_naissance" required>
            </div>

            <div class="form-group">
                <label for="adresse">Adresse *</label>
                <input type="text" id="adresse" name="adresse" required>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="commune">Ville *</label>
                    <select id="commune" name="commune" required>
                        <option value="">-- Sélectionnez une ville --</option>
                        <?php foreach($villes as $ville): ?>
                            <option value="<?php echo htmlspecialchars($ville['ville']); ?>" 
                                    data-cp="<?php echo htmlspecialchars($ville['cp']); ?>"
                                    data-secteur="<?php echo htmlspecialchars($ville['secteur']); ?>">
                                <?php echo htmlspecialchars($ville['ville']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="code_postal">Code postal *</label>
                    <input type="text" id="code_postal" name="code_postal" readonly required>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="tel_fixe">Téléphone fixe</label>
                    <input type="tel" id="tel_fixe" name="tel_fixe" placeholder="02 00 00 00 00">
                </div>
                <div class="form-group">
                    <label for="tel_mobile">Téléphone portable</label>
                    <input type="tel" id="tel_mobile" name="tel_mobile" placeholder="06 00 00 00 00">
                </div>
            </div>

            <div class="form-group">
                <label for="courriel">Email</label>
                <input type="email" id="courriel" name="courriel">
                <div class="field-hint">Format : exemple@domaine.fr</div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="secteur">Secteur</label>
                    <input type="text" id="secteur" name="secteur" readonly>
                </div>
                <div class="form-group">
                    <label for="debut">Date début</label>
                    <input type="date" id="debut" name="debut" value="<?php echo $dateJour; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="commentaires">Commentaires</label>
                <textarea id="commentaires" name="commentaires"></textarea>
            </div>

            <h3>📅 Disponibilités hebdomadaires</h3>
            <div class="disponibilites-grid">
                <div class="form-group">
                    <label for="lundi">Lundi</label>
                    <select id="lundi" name="lundi">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>">
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mardi">Mardi</label>
                    <select id="mardi" name="mardi">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>">
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mercredi">Mercredi</label>
                    <select id="mercredi" name="mercredi">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>">
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="jeudi">Jeudi</label>
                    <select id="jeudi" name="jeudi">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>">
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="vendredi">Vendredi</label>
                    <select id="vendredi" name="vendredi">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>">
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3>🚗 Véhicule</h3>
            <div class="row">
                <div class="form-group">
                    <label for="immatriculation">Immatriculation</label>
                    <input type="text" id="immatriculation" name="immatriculation" placeholder="AA-123-BB">
                </div>
                <div class="form-group">
                    <label for="chevaux_fiscaux">Chevaux</label>
                    <input type="text" id="chevaux_fiscaux" name="chevaux_fiscaux" placeholder="">
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($type_vehicule as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['type_vehicule']); ?>">
                                <?php echo htmlspecialchars($type['type_vehicule']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3>📧 Autres informations</h3>
            <div class="form-group">
                <label for="flag_mail">Utilisation mail</label>
                <select id="flag_mail" name="flag_mail">
                    <option value="">-- Non renseigné --</option>
                    <option value="O">O (Oui)</option>
                    <option value="N">N (Non)</option>
                </select>
            </div>

            <input type="hidden" name="fin" value="">
            <input type="hidden" name="dons" value="">
            <input type="hidden" name="date_2" value="">
            <input type="hidden" name="observations_2" value="">

            <button type="submit" class="btn-submit">💾 Enregistrer le bénévole</button>
        </form>
    </div>

    <script>
        const villesData = <?php echo json_encode($villes); ?>;

        const communeInput = document.getElementById('commune');
        const cpInput = document.getElementById('code_postal');
        const secteurInput = document.getElementById('secteur');

        function updateCPAndSecteur(villeName) {
            const select = communeInput;
            const selectedOption = select.options[select.selectedIndex];
            const cp = selectedOption.dataset.cp;
            const secteur = selectedOption.dataset.secteur;
            
            cpInput.value = cp || '';
            secteurInput.value = secteur || '';
        }

        communeInput.addEventListener('change', function() {
            updateCPAndSecteur(this.value);
        });

        const nomInput = document.getElementById('nom');
        nomInput.addEventListener('blur', function() {
            let valeur = this.value.trim();
            if (valeur && valeur.includes(' ')) {
                const premierEspace = valeur.indexOf(' ');
                const nom = valeur.substring(0, premierEspace);
                const prenoms = valeur.substring(premierEspace + 1);
                // Mettre le nom en majuscules et le prénom en minuscules avec initiale en majuscule
                const prenomsFormates = prenoms.charAt(0).toUpperCase() + prenoms.slice(1).toLowerCase();
                this.value = nom.toUpperCase() + ' ' + prenomsFormates;
            }
        });

        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.substring(0, 10);
            let formatted = '';
            for (let i = 0; i < value.length; i += 2) {
                if (i > 0) formatted += ' ';
                formatted += value.substring(i, i + 2);
            }
            input.value = formatted;
        }

        const telFixeInput = document.getElementById('tel_fixe');
        const telMobileInput = document.getElementById('tel_mobile');

        telFixeInput.addEventListener('input', function() {
            formatPhoneNumber(this);
        });

        telMobileInput.addEventListener('input', function() {
            formatPhoneNumber(this);
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const cpValue = cpInput.value;
            if (!cpValue || cpValue.length !== 5) {
                e.preventDefault();
                alert('⚠️ Veuillez sélectionner une commune valide dans la liste pour obtenir le code postal.');
                communeInput.focus();
                return false;
            }

            const telFixe = telFixeInput.value;
            const telMobile = telMobileInput.value;
            
            if (telFixe && telFixe.replace(/\s/g, '').length !== 10) {
                e.preventDefault();
                alert('⚠️ Le téléphone fixe doit contenir 10 chiffres.');
                telFixeInput.focus();
                return false;
            }
            
            if (telMobile && telMobile.replace(/\s/g, '').length !== 10) {
                e.preventDefault();
                alert('⚠️ Le téléphone portable doit contenir 10 chiffres.');
                telMobileInput.focus();
                return false;
            }

            const emailInput = document.getElementById('courriel');
            const emailValue = emailInput.value.trim();
            if (emailValue) {
                const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
                if (!emailRegex.test(emailValue)) {
                    e.preventDefault();
                    alert('⚠️ Le format de l\'email est invalide. Format attendu : exemple@domaine.fr');
                    emailInput.focus();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
