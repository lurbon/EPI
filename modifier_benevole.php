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
$benevole = null;
$benevoles = [];

// Connexion PDO
try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer la liste des bénévoles pour le filtre
try {
    $stmt = $conn->query("SELECT id_benevole, nom FROM EPI_benevole ORDER BY nom");
    $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Villes depuis la table EPI_ville
$villes = [];
try {
    $stmt = $conn->query("SELECT ville, cp, secteur FROM EPI_ville ORDER BY ville");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Plannings
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

// Traitement du formulaire de modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_benevole'])) {
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
        
        $sql = "UPDATE EPI_benevole SET 
                nom = :nom, date_naissance = :date_naissance, adresse = :adresse, 
                code_postal = :code_postal, commune = :commune, tel_fixe = :tel_fixe, 
                tel_mobile = :tel_mobile, courriel = :courriel, secteur = :secteur, 
                commentaires = :commentaires, debut = :debut, fin = :fin, 
                lundi = :lundi, mardi = :mardi, mercredi = :mercredi, jeudi = :jeudi, 
                vendredi = :vendredi, immatriculation = :immatriculation, 
                chevaux_fiscaux = :chevaux_fiscaux, type = :type, flag_mail = :flag_mail
                WHERE id_benevole = :id_benevole";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':nom' => $nomComplet,
            ':date_naissance' => !empty($_POST['date_naissance']) ? $_POST['date_naissance'] : null,
            ':adresse' => !empty($_POST['adresse']) ? $_POST['adresse'] : null,
            ':code_postal' => !empty($_POST['code_postal']) ? $_POST['code_postal'] : null,
            ':commune' => !empty($_POST['commune']) ? $_POST['commune'] : null,
            ':tel_fixe' => !empty($_POST['tel_fixe']) ? $_POST['tel_fixe'] : null,
            ':tel_mobile' => !empty($_POST['tel_mobile']) ? $_POST['tel_mobile'] : null,
            ':courriel' => !empty($_POST['courriel']) ? $_POST['courriel'] : null,
            ':secteur' => !empty($_POST['secteur']) ? $_POST['secteur'] : null,
            ':commentaires' => !empty($_POST['commentaires']) ? $_POST['commentaires'] : null,
            ':debut' => !empty($_POST['debut']) ? $_POST['debut'] : null,
            ':fin' => !empty($_POST['fin']) ? $_POST['fin'] : null,
            ':lundi' => !empty($_POST['lundi']) ? $_POST['lundi'] : null,
            ':mardi' => !empty($_POST['mardi']) ? $_POST['mardi'] : null,
            ':mercredi' => !empty($_POST['mercredi']) ? $_POST['mercredi'] : null,
            ':jeudi' => !empty($_POST['jeudi']) ? $_POST['jeudi'] : null,
            ':vendredi' => !empty($_POST['vendredi']) ? $_POST['vendredi'] : null,
            ':immatriculation' => !empty($_POST['immatriculation']) ? $_POST['immatriculation'] : null,
            ':chevaux_fiscaux' => !empty($_POST['chevaux_fiscaux']) ? $_POST['chevaux_fiscaux'] : null,
            ':type' => !empty($_POST['type']) ? $_POST['type'] : null,
            ':flag_mail' => !empty($_POST['flag_mail']) ? $_POST['flag_mail'] : null,
            ':id_benevole' => $_POST['id_benevole']
        ]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&id=" . $_POST['id_benevole']);
        exit();
        
    } catch(PDOException $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg . "&id=" . $_POST['id_benevole']);
        exit();
    }
}

// Chargement des données du bénévole si un ID est fourni
if (isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM EPI_benevole WHERE id_benevole = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $benevole = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$benevole) {
            $message = "❌ Bénévole introuvable";
            $messageType = "error";
        }
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Messages
if (isset($_GET['success'])) {
    $message = "✅ Bénévole modifié avec succès !";
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
    <title>Modifier un Bénévole</title>
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

        .search-box {
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .search-box label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 600;
        }

        .autocomplete-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .autocomplete-wrapper input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .autocomplete-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .autocomplete-items div {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .autocomplete-items div:hover {
            background-color: #f8f9ff;
        }

        .autocomplete-active {
            background-color: #667eea !important;
            color: white;
        }

        .search-box select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
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
            cursor: not-allowed;
        }

        .row {
            display: flex;
            gap: 15px;
        }

        .row .form-group {
            flex: 1;
        }

        .disponibilites-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 10px;
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
            margin-top: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
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

        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }
            
            .disponibilites-grid {
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
        <h1>✏️ Modifier un Bénévole</h1>

        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <label for="search_benevole">🔍 Recherchez ou sélectionnez un bénévole à modifier</label>
            
            <!-- Champ de recherche avec autocomplétion -->
            <div class="autocomplete-wrapper">
                <input type="text" 
                       id="search_input" 
                       placeholder="Tapez pour rechercher..." 
                       autocomplete="off"
                       value="<?php echo isset($_GET['id']) && $benevole ? htmlspecialchars($benevole['nom']) : ''; ?>">
                <div id="autocomplete-list" class="autocomplete-items"></div>
            </div>
            
            <!-- Liste déroulante traditionnelle -->
            <select id="search_benevole" onchange="if(this.value) window.location.href='?id='+this.value">
                <option value="">-- Ou choisissez dans la liste --</option>
                <?php foreach($benevoles as $b): ?>
                    <option value="<?php echo $b['id_benevole']; ?>" 
                            <?php echo (isset($_GET['id']) && $_GET['id'] == $b['id_benevole']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if($benevole): ?>
        <form method="POST" action="">
            <input type="hidden" name="id_benevole" value="<?php echo $benevole['id_benevole']; ?>">

            <div class="form-group">
                <label for="nom">NOM et Prénom *</label>
                <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($benevole['nom']); ?>">
            </div>

            <div class="form-group">
                <label for="date_naissance">Date de naissance</label>
                <input type="date" id="date_naissance" name="date_naissance" value="<?php echo $benevole['date_naissance']; ?>">
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($benevole['adresse']); ?>">
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="commune">Ville</label>
                    <select id="commune" name="commune">
                        <option value="">-- Sélectionnez --</option>
                        <?php foreach($villes as $ville): ?>
                            <option value="<?php echo htmlspecialchars($ville['ville']); ?>" 
                                    data-cp="<?php echo htmlspecialchars($ville['cp']); ?>"
                                    data-secteur="<?php echo htmlspecialchars($ville['secteur']); ?>"
                                    <?php echo ($benevole['commune'] == $ville['ville']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ville['ville']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="code_postal">Code postal</label>
                    <input type="text" id="code_postal" name="code_postal" readonly value="<?php echo htmlspecialchars($benevole['code_postal']); ?>">
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="tel_fixe">Téléphone fixe</label>
                    <input type="tel" id="tel_fixe" name="tel_fixe" value="<?php echo htmlspecialchars($benevole['tel_fixe']); ?>">
                </div>
                <div class="form-group">
                    <label for="tel_mobile">Téléphone mobile</label>
                    <input type="tel" id="tel_mobile" name="tel_mobile" value="<?php echo htmlspecialchars($benevole['tel_mobile']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="courriel">Email</label>
                <input type="email" id="courriel" name="courriel" value="<?php echo htmlspecialchars($benevole['courriel']); ?>">
            </div>

            <div class="form-group">
                <label for="secteur">Secteur</label>
                <input type="text" id="secteur" name="secteur" readonly value="<?php echo htmlspecialchars($benevole['secteur']); ?>">
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="debut">Date début</label>
                    <input type="date" id="debut" name="debut" value="<?php echo $benevole['debut']; ?>">
                </div>
                <div class="form-group">
                    <label for="fin">Date fin</label>
                    <input type="date" id="fin" name="fin" value="<?php echo $benevole['fin']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="commentaires">Commentaires</label>
                <textarea id="commentaires" name="commentaires"><?php echo htmlspecialchars($benevole['commentaires']); ?></textarea>
            </div>

            <h3>📅 Disponibilités</h3>
            <div class="disponibilites-grid">
                <?php 
                $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
                foreach($jours as $jour): 
                ?>
                <div class="form-group">
                    <label for="<?php echo $jour; ?>"><?php echo ucfirst($jour); ?></label>
                    <select id="<?php echo $jour; ?>" name="<?php echo $jour; ?>">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($plannings as $planning): ?>
                            <option value="<?php echo htmlspecialchars($planning['jours']); ?>"
                                    <?php echo ($benevole[$jour] == $planning['jours']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($planning['jours']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <h3>🚗 Véhicule</h3>
            <div class="row">
                <div class="form-group">
                    <label for="immatriculation">Immatriculation</label>
                    <input type="text" id="immatriculation" name="immatriculation" value="<?php echo htmlspecialchars($benevole['immatriculation']); ?>">
                </div>
                <div class="form-group">
                    <label for="chevaux_fiscaux">Chevaux fiscaux</label>
                    <input type="text" id="chevaux_fiscaux" name="chevaux_fiscaux" value="<?php echo htmlspecialchars($benevole['chevaux_fiscaux']); ?>">
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($type_vehicule as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['type_vehicule']); ?>"
                                    <?php echo ($benevole['type'] == $type['type_vehicule']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_vehicule']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3>📝 Autres</h3>
            <div class="form-group">
                <label for="flag_mail">Utilisation mail</label>
                <select id="flag_mail" name="flag_mail">
                    <option value="">-- Non renseigné --</option>
                    <option value="O" <?php echo ($benevole['flag_mail'] == 'O') ? 'selected' : ''; ?>>O (Oui)</option>
                    <option value="N" <?php echo ($benevole['flag_mail'] == 'N') ? 'selected' : ''; ?>>N (Non)</option>
                </select>
            </div>

            <h3>💰 Cotisation</h3>
            <div class="row">
                <div class="form-group">
                    <label for="p_2026">Cotisation 2026 (€) - Consultation seule</label>
                    <input type="number" step="0.01" id="p_2026" name="p_2026" value="<?php echo $benevole['p_2026']; ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label for="moyen">Moyen de paiement - Consultation seule</label>
                    <select id="moyen" name="moyen" disabled style="background-color: #f0f0f0; cursor: not-allowed;">
                        <option value="">-- Non renseigné --</option>
                        <?php foreach($moyensPaiement as $moyen): ?>
                            <option value="<?php echo htmlspecialchars($moyen); ?>" 
                                    <?php echo ($benevole['moyen'] == $moyen) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($moyen); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="date_1">Date de paiement - Consultation seule</label>
                <input type="date" id="date_1" name="date_1" value="<?php echo $benevole['date_1']; ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label for="observations_1">Observations - Consultation seule</label>
                <textarea id="observations_1" name="observations_1" readonly style="background-color: #f0f0f0; cursor: not-allowed;"><?php echo htmlspecialchars($benevole['observations_1']); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">💾 Enregistrer les modifications</button>
        </form>
        <?php else: ?>
            <div class="no-selection">
                <p>👆 Veuillez sélectionner un bénévole dans la liste ci-dessus</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const communeInput = document.getElementById('commune');
        const cpInput = document.getElementById('code_postal');
        const secteurInput = document.getElementById('secteur');

        if (communeInput) {
            communeInput.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                cpInput.value = selectedOption.dataset.cp || '';
                secteurInput.value = selectedOption.dataset.secteur || '';
            });
        }

        const nomInput = document.getElementById('nom');
        if (nomInput) {
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
        }

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

        const telFixe = document.getElementById('tel_fixe');
        const telMobile = document.getElementById('tel_mobile');
        
        if (telFixe) {
            telFixe.addEventListener('input', function() { formatPhoneNumber(this); });
        }
        if (telMobile) {
            telMobile.addEventListener('input', function() { formatPhoneNumber(this); });
        }

        // ============ AUTOCOMPLÉTION POUR LA RECHERCHE DE BÉNÉVOLES ============
        const searchInput = document.getElementById('search_input');
        const searchSelect = document.getElementById('search_benevole');
        
        // Données des bénévoles pour l'autocomplétion
        const benevolesData = [
            <?php foreach($benevoles as $b): ?>
            {id: <?php echo $b['id_benevole']; ?>, nom: "<?php echo addslashes($b['nom']); ?>"},
            <?php endforeach; ?>
        ];

        let currentFocus = -1;

        // Fonction de recherche et affichage des suggestions
        searchInput.addEventListener('input', function() {
            const val = this.value.trim();
            closeAllLists();
            
            if (!val) {
                return false;
            }
            
            currentFocus = -1;
            const autocompleteList = document.getElementById('autocomplete-list');
            
            // Filtrer les bénévoles correspondants
            const filtered = benevolesData.filter(benevole => 
                benevole.nom.toLowerCase().includes(val.toLowerCase())
            );
            
            // Afficher les résultats
            if (filtered.length > 0) {
                filtered.forEach(benevole => {
                    const div = document.createElement('div');
                    
                    // Mettre en évidence le texte correspondant
                    const index = benevole.nom.toLowerCase().indexOf(val.toLowerCase());
                    const before = benevole.nom.substr(0, index);
                    const match = benevole.nom.substr(index, val.length);
                    const after = benevole.nom.substr(index + val.length);
                    
                    div.innerHTML = before + '<strong>' + match + '</strong>' + after;
                    div.innerHTML += '<input type="hidden" value="' + benevole.id + '">';
                    
                    // Clic sur une suggestion
                    div.addEventListener('click', function() {
                        const benevoleId = this.getElementsByTagName('input')[0].value;
                        window.location.href = '?id=' + benevoleId;
                    });
                    
                    autocompleteList.appendChild(div);
                });
            } else {
                const div = document.createElement('div');
                div.innerHTML = '<em style="color: #999;">Aucun résultat trouvé</em>';
                div.style.cursor = 'default';
                autocompleteList.appendChild(div);
            }
        });

        // Navigation au clavier dans les suggestions
        searchInput.addEventListener('keydown', function(e) {
            let items = document.getElementById('autocomplete-list');
            if (items) items = items.getElementsByTagName('div');
            
            if (e.keyCode == 40) { // Flèche bas
                currentFocus++;
                addActive(items);
                e.preventDefault();
            } else if (e.keyCode == 38) { // Flèche haut
                currentFocus--;
                addActive(items);
                e.preventDefault();
            } else if (e.keyCode == 13) { // Entrée
                e.preventDefault();
                if (currentFocus > -1) {
                    if (items) items[currentFocus].click();
                }
            }
        });

        function addActive(items) {
            if (!items) return false;
            removeActive(items);
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (items.length - 1);
            items[currentFocus].classList.add('autocomplete-active');
        }

        function removeActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('autocomplete-active');
            }
        }

        function closeAllLists(elmnt) {
            const items = document.getElementsByClassName('autocomplete-items');
            for (let i = 0; i < items.length; i++) {
                if (elmnt != items[i] && elmnt != searchInput) {
                    items[i].innerHTML = '';
                }
            }
        }

        // Fermer la liste quand on clique ailleurs
        document.addEventListener('click', function (e) {
            if (e.target !== searchInput) {
                closeAllLists(e.target);
            }
        });

        // Synchroniser le select avec l'input
        searchSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                searchInput.value = selectedOption.text;
            }
        });
    </script>
</body>
</html>