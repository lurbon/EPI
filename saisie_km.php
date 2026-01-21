<?php
// Charger la configuration WordPress
require_once('wp-config.php');
require_once('auth.php');
verifierRole(['admin']);

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

// Fonction pour convertir la date en français
function dateEnFrancais($date) {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    
    $timestamp = strtotime($date);
    $jour = $jours[date('w', $timestamp)];
    $numeroJour = date('d', $timestamp);
    $nomMois = $mois[intval(date('m', $timestamp))];
    $annee = date('Y', $timestamp);
    
    return "$jour $numeroJour $nomMois $annee";
}

// Traitement de la mise à jour
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_mission'])) {
    try {
        $sql = "UPDATE EPI_mission SET 
                km_saisi = :km_saisi,
                km_calcule = :km_calcule,
                heure_depart_mission = :heure_depart_mission,
                heure_retour_mission = :heure_retour_mission,
                duree = :duree
                WHERE id_mission = :id_mission";
        
        $stmt = $conn->prepare($sql);
        
        // Calcul de la durée si les heures sont fournies
        $duree = null;
        if (!empty($_POST['heure_depart_mission']) && !empty($_POST['heure_retour_mission'])) {
            $depart = new DateTime($_POST['heure_depart_mission']);
            $retour = new DateTime($_POST['heure_retour_mission']);
            $interval = $depart->diff($retour);
            $duree = $interval->format('%H:%I:00');
        }
        
        $stmt->execute([
            ':km_saisi' => !empty($_POST['km_saisi']) ? $_POST['km_saisi'] : null,
            ':km_calcule' => !empty($_POST['km_calcule']) ? $_POST['km_calcule'] : null,
            ':heure_depart_mission' => !empty($_POST['heure_depart_mission']) ? $_POST['heure_depart_mission'] : null,
            ':heure_retour_mission' => !empty($_POST['heure_retour_mission']) ? $_POST['heure_retour_mission'] : null,
            ':duree' => $duree,
            ':id_mission' => $_POST['id_mission']
        ]);
        
        $message = "✅ Mission mise à jour avec succès !";
        $messageType = "success";
        
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer les paramètres de filtre
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filterNoKm = isset($_GET['filter_no_km']) && $_GET['filter_no_km'] === '1';

// Récupérer les missions
try {
    $sql = "SELECT id_mission, date_mission, heure_rdv, 
            benevole, adresse_benevole, cp_benevole, commune_benevole,
            aide, adresse_aide, cp_aide, commune_aide,
            adresse_destination, cp_destination, commune_destination,
            nature_intervention, commentaires,
            km_saisi, km_calcule, heure_depart_mission, heure_retour_mission, duree
            FROM EPI_mission 
            WHERE benevole IS NOT NULL";
    
    $params = [];
    
    // Filtre pour missions sans km saisi
    if ($filterNoKm) {
        $sql .= " AND (km_saisi IS NULL OR km_saisi = 0)";
    }
    
    // Recherche par date, aidé ou bénévole
    if ($search) {
        $sql .= " AND (date_mission LIKE :search OR aide LIKE :search OR benevole LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY date_mission DESC, heure_rdv DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie KM et Heures</title>
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
            font-size: 28px;
        }

        .message {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .info-banner {
            background: #d1ecf1;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
            font-size: 13px;
            color: #0c5460;
            text-align: center;
        }

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }

        .filter-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .filter-checkbox label {
            cursor: pointer;
            margin: 0;
            font-weight: 500;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-filter:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .btn-reset {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-reset:hover {
            background: #5a6268;
        }

        .no-missions {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        .missions-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .mission-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .mission-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .mission-date {
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
        }

        .mission-benevole {
            color: #764ba2;
            font-weight: 600;
            font-size: 14px;
        }

        .mission-details {
            display: grid;
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            align-items: start;
            font-size: 14px;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            min-width: 140px;
        }

        .detail-value {
            color: #666;
        }

        .km-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[readonly] {
            background-color: #f5f5f5;
        }

        .btn-calculate {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            align-self: end;
        }

        .btn-calculate:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn-calculate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-save {
            padding: 10px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-save:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .calc-message {
            font-size: 12px;
            margin-top: 5px;
            padding: 5px;
            border-radius: 4px;
        }

        .calc-message.success {
            background: #d4edda;
            color: #155724;
        }

        .calc-message.error {
            background: #f8d7da;
            color: #721c24;
        }

        .calc-message.loading {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .mission-header {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au dashboard</a>

    <div class="container">
        <h1>🚗 Saisie des Kilomètres et Heures</h1>
        
        <div class="info-banner">
            ℹ️ Le calcul automatique des KM utilise OpenRouteService (gratuit). Saisissez les KM réels après vérification. <strong>Clé API requise (gratuite).</strong>
        </div>

        <!-- Formulaire de filtre -->
        <div class="filter-box">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">🔍 Rechercher</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               placeholder="Date, nom aidé ou bénévole..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <div class="filter-checkbox">
                            <input type="checkbox" 
                                   id="filter_no_km" 
                                   name="filter_no_km" 
                                   value="1"
                                   <?php echo $filterNoKm ? 'checked' : ''; ?>>
                            <label for="filter_no_km">🚫 Uniquement missions sans km saisi</label>
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">Filtrer</button>
                        <a href="saisie_km.php" class="btn-reset">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if(empty($missions)): ?>
            <div class="no-missions">
                ✅ Aucune mission en attente de saisie KM. Toutes les missions sont complétées !
            </div>
        <?php else: ?>
            <div class="missions-grid">
                <?php foreach($missions as $mission): ?>
                    <div class="mission-card">
                        <form method="POST" action="">
                            <input type="hidden" name="id_mission" value="<?php echo $mission['id_mission']; ?>">
                            
                            <div class="mission-header">
                                <div>
                                    <span class="mission-date">
                                        📅 <?php echo dateEnFrancais($mission['date_mission']); ?>
                                        <?php if($mission['heure_rdv']): ?>
                                            - <?php echo substr($mission['heure_rdv'], 0, 5); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="mission-benevole">
                                        👤 <?php echo htmlspecialchars($mission['benevole'] ?: 'Non assigné'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mission-details">
                                <div class="detail-row">
                                    <span class="detail-label">Aidé(e):</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($mission['aide']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Adresse Aidé(e):</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($mission['adresse_aide'] ?: '-'); ?>, 
                                        <?php echo htmlspecialchars($mission['cp_aide']); ?> 
                                        <?php echo htmlspecialchars($mission['commune_aide']); ?>
                                    </span>
                                </div>
                                <?php if($mission['adresse_benevole']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Adresse Bénévole:</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($mission['adresse_benevole']); ?>, 
                                        <?php echo htmlspecialchars($mission['cp_benevole']); ?> 
                                        <?php echo htmlspecialchars($mission['commune_benevole']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-row">
                                    <span class="detail-label">Destination:</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($mission['adresse_destination'] ?: 'Non précisée'); ?>
                                        <?php if($mission['commune_destination']): ?>
                                            , <?php echo htmlspecialchars($mission['cp_destination']); ?> 
                                            <?php echo htmlspecialchars($mission['commune_destination']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if($mission['nature_intervention']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Nature:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($mission['nature_intervention']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if($mission['commentaires']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Commentaires:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($mission['commentaires']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="km-section">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>KM calculés (auto)</label>
                                        <input type="number" 
                                               name="km_calcule" 
                                               id="km_calcule_<?php echo $mission['id_mission']; ?>"
                                               step="0.1" 
                                               value="<?php echo htmlspecialchars($mission['km_calcule']); ?>"
                                               readonly
                                               placeholder="Calculer">
                                    </div>
                                    <div class="form-group">
                                        <label>KM réels * (requis)</label>
                                        <input type="number" 
                                               name="km_saisi" 
                                               step="0.1" 
                                               value="<?php echo htmlspecialchars($mission['km_saisi']); ?>"
                                               placeholder="Ex: 25.5"
                                               required>
                                    </div>
                                    <div class="form-group">
                                        <label>Heure départ</label>
                                        <input type="time" 
                                               name="heure_depart_mission"
                                               id="heure_depart_<?php echo $mission['id_mission']; ?>"
                                               value="<?php echo $mission['heure_depart_mission'] ? substr($mission['heure_depart_mission'], 0, 5) : ''; ?>"
                                               onchange="calculateDuree(<?php echo $mission['id_mission']; ?>)">
                                    </div>
                                    <div class="form-group">
                                        <label>Heure retour</label>
                                        <input type="time" 
                                               name="heure_retour_mission"
                                               id="heure_retour_<?php echo $mission['id_mission']; ?>"
                                               value="<?php echo $mission['heure_retour_mission'] ? substr($mission['heure_retour_mission'], 0, 5) : ''; ?>"
                                               onchange="calculateDuree(<?php echo $mission['id_mission']; ?>)">
                                    </div>
                                    <div class="form-group">
                                        <label>Durée calculée</label>
                                        <input type="text" 
                                               id="duree_display_<?php echo $mission['id_mission']; ?>"
                                               value="<?php echo $mission['duree'] ? substr($mission['duree'], 0, 5) : ''; ?>"
                                               readonly
                                               placeholder="Auto"
                                               style="background-color: #f5f5f5;">
                                        <input type="hidden" 
                                               name="duree"
                                               id="duree_<?php echo $mission['id_mission']; ?>"
                                               value="<?php echo htmlspecialchars($mission['duree']); ?>">
                                    </div>
                                    <button type="button" 
                                            class="btn-calculate" 
                                            onclick="calculateDistanceGoogleMaps(<?php echo $mission['id_mission']; ?>, '<?php echo addslashes($mission['adresse_benevole']); ?>', '<?php echo addslashes($mission['cp_benevole']); ?>', '<?php echo addslashes($mission['commune_benevole']); ?>', '<?php echo addslashes($mission['adresse_aide']); ?>', '<?php echo addslashes($mission['cp_aide']); ?>', '<?php echo addslashes($mission['commune_aide']); ?>', '<?php echo addslashes($mission['adresse_destination']); ?>', '<?php echo addslashes($mission['cp_destination']); ?>', '<?php echo addslashes($mission['commune_destination']); ?>')">
                                        🗺️ Calculer KM
                                    </button>
                                </div>
                                <div id="calc_msg_<?php echo $mission['id_mission']; ?>" class="calc-message" style="display:none;"></div>
                                <button type="submit" class="btn-save">💾 Enregistrer</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ⚠️ IMPORTANT : Remplacez 'VOTRE_CLE_API_OPENROUTE' par votre clé API OpenRouteService
        // Obtenez votre clé gratuite sur : https://openrouteservice.org/dev/#/signup
        const OPENROUTE_API_KEY = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjUxNGUyYzdmMWUzMTRmM2E4ZTBmZTYwYWEzZTAzNjNmIiwiaCI6Im11cm11cjY0In0=';

        function showCalcMessage(id, text, type) {
            const msgDiv = document.getElementById('calc_msg_' + id);
            msgDiv.style.display = 'block';
            msgDiv.className = 'calc-message ' + type;
            msgDiv.textContent = text;
        }

        async function calculateDistanceGoogleMaps(id, benevoleAddr, benevoleCp, benevoleVille, aideAddr, aideCp, aideVille, destAddr, destCp, destVille) {
            const calcBtn = event.target;
            calcBtn.disabled = true;
            calcBtn.textContent = '⏳ Calcul...';
            
            showCalcMessage(id, '🔍 Calcul de la distance...', 'loading');

            try {
                if (!benevoleAddr || !aideAddr || !destAddr) {
                    throw new Error('Adresses incomplètes pour le calcul');
                }

                if (OPENROUTE_API_KEY === 'VOTRE_CLE_API_OPENROUTE') {
                    throw new Error('⚠️ Veuillez configurer votre clé API OpenRouteService dans le code (ligne 698)');
                }

                // Étape 1 : Géocoder les adresses (obtenir les coordonnées)
                showCalcMessage(id, '📍 Localisation des adresses...', 'loading');
                
                const coordsBenevole = await geocodeAddress(`${benevoleAddr}, ${benevoleCp} ${benevoleVille}, France`);
                const coordsAide = await geocodeAddress(`${aideAddr}, ${aideCp} ${aideVille}, France`);
                const coordsDest = await geocodeAddress(`${destAddr}, ${destCp} ${destVille}, France`);

                // Étape 2 : Calculer distance Bénévole → Aidé
                showCalcMessage(id, '🚗 Calcul: Bénévole → Aidé...', 'loading');
                const distBenevoleVersAide = await calculateRoute(coordsBenevole, coordsAide);
                
                // Étape 3 : Calculer distance Aidé → Destination
                showCalcMessage(id, '🚗 Calcul: Aidé → Destination...', 'loading');
                const distAideVersDest = await calculateRoute(coordsAide, coordsDest);
                
                // Distance aller = Bénévole → Aidé + Aidé → Destination
                const distanceAller = distBenevoleVersAide + distAideVersDest;
                
                // Distance totale = Aller × 2 (aller-retour), arrondi à l'entier supérieur
                const totalKm = Math.ceil(distanceAller * 2);
                
                document.getElementById('km_calcule_' + id).value = totalKm;
                
                showCalcMessage(id, `✓ Distance calculée : ${totalKm} km (${distanceAller.toFixed(1)} km × 2, arrondi sup.)
                    [${distBenevoleVersAide.toFixed(1)}km bénévole→aidé + ${distAideVersDest.toFixed(1)}km aidé→dest]`, 'success');

            } catch (error) {
                showCalcMessage(id, '❌ Erreur : ' + error.message, 'error');
                console.error('Erreur:', error);
            } finally {
                calcBtn.disabled = false;
                calcBtn.textContent = '🗺️ Calculer KM';
            }
        }

        // Fonction pour géocoder une adresse (obtenir lat/lon)
        async function geocodeAddress(address) {
            const url = `https://api.openrouteservice.org/geocode/search?api_key=${OPENROUTE_API_KEY}&text=${encodeURIComponent(address)}&boundary.country=FR&size=1`;
            
            try {
                const response = await fetch(url);
                
                if (!response.ok) {
                    if (response.status === 401) {
                        throw new Error('Clé API OpenRouteService invalide ou manquante');
                    }
                    throw new Error(`Erreur HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.features && data.features.length > 0) {
                    const coords = data.features[0].geometry.coordinates;
                    return {
                        lon: coords[0],
                        lat: coords[1]
                    };
                } else {
                    throw new Error(`Adresse non trouvée : ${address}`);
                }
            } catch (error) {
                throw new Error(`Géocodage impossible pour "${address}": ${error.message}`);
            }
        }

        // Fonction pour calculer la distance routière entre deux points
        async function calculateRoute(origin, destination) {
            const url = `https://api.openrouteservice.org/v2/directions/driving-car?api_key=${OPENROUTE_API_KEY}`;
            
            const body = {
                coordinates: [
                    [origin.lon, origin.lat],
                    [destination.lon, destination.lat]
                ]
            };
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                });
                
                if (!response.ok) {
                    if (response.status === 401) {
                        throw new Error('Clé API OpenRouteService invalide');
                    }
                    throw new Error(`Erreur HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.routes && data.routes.length > 0) {
                    const distanceMeters = data.routes[0].summary.distance;
                    return distanceMeters / 1000; // Convertir en km
                } else {
                    throw new Error('Itinéraire non trouvé');
                }
            } catch (error) {
                throw new Error(`Calcul d'itinéraire impossible: ${error.message}`);
            }
        }

        function calculateDuree(id) {
            const heureDepartInput = document.getElementById('heure_depart_' + id);
            const heureRetourInput = document.getElementById('heure_retour_' + id);
            const dureeDisplay = document.getElementById('duree_display_' + id);
            const dureeHidden = document.getElementById('duree_' + id);
            
            const heureDepart = heureDepartInput.value;
            const heureRetour = heureRetourInput.value;
            
            if (!heureDepart || !heureRetour) {
                dureeDisplay.value = '';
                dureeHidden.value = '';
                return;
            }
            
            try {
                const [hDepart, mDepart] = heureDepart.split(':').map(Number);
                const [hRetour, mRetour] = heureRetour.split(':').map(Number);
                
                let minutesDepart = hDepart * 60 + mDepart;
                let minutesRetour = hRetour * 60 + mRetour;
                
                if (minutesRetour < minutesDepart) {
                    minutesRetour += 24 * 60;
                }
                
                const diffMinutes = minutesRetour - minutesDepart;
                const heures = Math.floor(diffMinutes / 60);
                const minutes = diffMinutes % 60;
                
                const dureeFormatted = String(heures).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':00';
                dureeHidden.value = dureeFormatted;
                
                let dureeDisplay_text = '';
                if (heures > 0) {
                    dureeDisplay_text = heures + 'h' + String(minutes).padStart(2, '0');
                } else {
                    dureeDisplay_text = minutes + 'min';
                }
                dureeDisplay.value = dureeDisplay_text;
                
            } catch (e) {
                console.error('Erreur calcul durée:', e);
                dureeDisplay.value = '';
                dureeHidden.value = '';
            }
        }

        // Fermer le message de succès automatiquement après 5 secondes
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>