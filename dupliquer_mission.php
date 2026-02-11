<?php
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

$message = "";
$messageType = "";
$mission = null;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer toutes les missions pour le filtre (sans restriction de date)
$missions = [];
try {
    $stmt = $conn->prepare("SELECT id_mission, date_mission, heure_rdv, aide, benevole, nature_intervention 
                           FROM EPI_mission 
                           ORDER BY date_mission DESC, heure_rdv DESC");
    $stmt->execute();
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Traitement de la duplication
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'duplicate') {
    try {
        $id_mission = $_POST['id_mission'];
        $nb_semaines = (int)$_POST['nb_semaines'];
        
        if ($nb_semaines < 1 || $nb_semaines > 52) {
            throw new Exception("Le nombre de semaines doit être entre 1 et 52");
        }
        
        // Récupérer la mission originale
        $stmt = $conn->prepare("SELECT * FROM EPI_mission WHERE id_mission = :id");
        $stmt->execute([':id' => $id_mission]);
        $missionOriginale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$missionOriginale) {
            throw new Exception("Mission introuvable");
        }
        
        $dateOriginale = new DateTime($missionOriginale['date_mission']);
        $jourSemaine = $dateOriginale->format('N'); // 1 = lundi, 7 = dimanche
        $duplicationsReussies = 0;
        $erreurs = [];
        
        // Créer les duplications pour chaque semaine
        for ($i = 1; $i <= $nb_semaines; $i++) {
            $nouvelleDateObj = clone $dateOriginale;
            $nouvelleDateObj->modify("+{$i} week");
            $nouvelleDate = $nouvelleDateObj->format('Y-m-d');
            
            // Vérifier si une mission existe déjà à cette date pour ce bénévole et cet aidé
            $stmt = $conn->prepare("SELECT id_mission FROM EPI_mission 
                                   WHERE date_mission = :date 
                                   AND heure_rdv = :heure 
                                   AND id_benevole = :id_benevole 
                                   AND id_aide = :id_aide");
            $stmt->execute([
                ':date' => $nouvelleDate,
                ':heure' => $missionOriginale['heure_rdv'],
                ':id_benevole' => $missionOriginale['id_benevole'],
                ':id_aide' => $missionOriginale['id_aide']
            ]);
            
            if ($stmt->fetch()) {
                $erreurs[] = "Semaine +{$i} ({$nouvelleDate}) : mission déjà existante";
                continue;
            }
            
            // Insérer la nouvelle mission
            $sql = "INSERT INTO EPI_mission (
                date_mission, heure_rdv, heure_depart_mission, heure_retour_mission, duree,
                id_benevole, benevole, adresse_benevole, cp_benevole, commune_benevole, secteur_benevole,
                id_aide, aide, adresse_aide, cp_aide, commune_aide, secteur_aide,
                adresse_destination, cp_destination, commune_destination,
                nature_intervention, commentaires, km_saisi, km_calcule, email_createur, date_cre
            ) VALUES (
                :date_mission, :heure_rdv, :heure_depart_mission, :heure_retour_mission, :duree,
                :id_benevole, :benevole, :adresse_benevole, :cp_benevole, :commune_benevole, :secteur_benevole,
                :id_aide, :aide, :adresse_aide, :cp_aide, :commune_aide, :secteur_aide,
                :adresse_destination, :cp_destination, :commune_destination,
                :nature_intervention, :commentaires, :km_saisi, :km_calcule, :email_createur, NOW()
            )";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':date_mission' => $nouvelleDate,
                ':heure_rdv' => $missionOriginale['heure_rdv'],
                ':heure_depart_mission' => $missionOriginale['heure_depart_mission'],
                ':heure_retour_mission' => $missionOriginale['heure_retour_mission'],
                ':duree' => $missionOriginale['duree'],
                ':id_benevole' => $missionOriginale['id_benevole'],
                ':benevole' => $missionOriginale['benevole'],
                ':adresse_benevole' => $missionOriginale['adresse_benevole'],
                ':cp_benevole' => $missionOriginale['cp_benevole'],
                ':commune_benevole' => $missionOriginale['commune_benevole'],
                ':secteur_benevole' => $missionOriginale['secteur_benevole'],
                ':id_aide' => $missionOriginale['id_aide'],
                ':aide' => $missionOriginale['aide'],
                ':adresse_aide' => $missionOriginale['adresse_aide'],
                ':cp_aide' => $missionOriginale['cp_aide'],
                ':commune_aide' => $missionOriginale['commune_aide'],
                ':secteur_aide' => $missionOriginale['secteur_aide'],
                ':adresse_destination' => $missionOriginale['adresse_destination'],
                ':cp_destination' => $missionOriginale['cp_destination'],
                ':commune_destination' => $missionOriginale['commune_destination'],
                ':nature_intervention' => $missionOriginale['nature_intervention'],
                ':commentaires' => $missionOriginale['commentaires'],
                ':km_saisi' => $missionOriginale['km_saisi'],
                ':km_calcule' => $missionOriginale['km_calcule'],
                ':email_createur' => isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : null
            ]);
            
            $duplicationsReussies++;
        }
        
        $successMsg = "✅ {$duplicationsReussies} mission(s) créée(s) avec succès";
        if (!empty($erreurs)) {
            $successMsg .= "\n⚠️ Avertissements : " . implode(", ", $erreurs);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($successMsg));
        exit();
        
    } catch(Exception $e) {
        $errorMsg = urlencode($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . $errorMsg);
        exit();
    }
}

// Chargement de la mission si un ID est fourni
if (isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM EPI_mission WHERE id_mission = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mission) {
            $message = "❌ Mission introuvable";
            $messageType = "error";
        }
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Messages de succès/erreur
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
    $messageType = "success";
} elseif (isset($_GET['error'])) {
    $message = "❌ Erreur : " . urldecode($_GET['error']);
    $messageType = "error";
}

// Fonction pour formater la date en français
function formatDateFr($date) {
    if (!$date) return '';
    $jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $dt = new DateTime($date);
    return $jours[$dt->format('w')] . ' ' . $dt->format('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dupliquer une Mission</title>
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
            max-width: 1000px;
            margin: 40px auto;
            padding-left: 100px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
        }

        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            white-space: pre-line;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .search-section {
            margin-bottom: 30px;
        }

        .search-box {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-radius: 12px;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            margin-top: 5px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .autocomplete-items div {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .autocomplete-items div:hover,
        .autocomplete-active {
            background-color: #f8f9ff;
            color: #667eea;
        }

        .mission-info {
            background: #f8f9ff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .mission-info h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-box strong {
            color: #2980b9;
        }

        .calendar-preview {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }

        .calendar-preview h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .date-item {
            padding: 10px 15px;
            background: #f8f9ff;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .date-item .date {
            font-weight: 600;
            color: #333;
        }

        .date-item .day {
            color: #667eea;
            font-size: 14px;
        }

        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 18px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .no-selection {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-selection p {
            font-size: 18px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding-left: 20px;
            }

            .back-link {
                width: 50px;
                height: 50px;
                font-size: 20px;
                top: 20px;
                left: 20px;
            }

            .card {
                padding: 25px;
            }

            h1 {
                font-size: 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .btn-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link" title="Retour au tableau de bord">🏠</a>

    <div class="container">
        <div class="card">
            <h1>🔄 Dupliquer une Mission</h1>
            <p class="subtitle">Créez plusieurs missions identiques sur plusieurs semaines</p>

            <?php if($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="search-section">
                <h3 style="margin-bottom: 15px; color: #333;">Rechercher une mission</h3>
                <div class="search-box">
                    <input type="text" 
                           id="search_input" 
                           class="search-input" 
                           placeholder="🔍 Tapez le nom de l'aidé ou du bénévole..."
                           autocomplete="off">
                    <div id="autocomplete-list" class="autocomplete-items"></div>
                </div>
            </div>

            <?php if($mission): ?>
                <div class="mission-info">
                    <h3>📋 Informations de la mission à dupliquer</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Date et heure</div>
                            <div class="info-value">
                                <?php echo formatDateFr($mission['date_mission']); ?> à <?php echo substr($mission['heure_rdv'], 0, 5); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Bénévole</div>
                            <div class="info-value"><?php echo htmlspecialchars(stripslashes($mission['benevole'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Aidé</div>
                            <div class="info-value"><?php echo htmlspecialchars(stripslashes($mission['aide'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Nature intervention</div>
                            <div class="info-value"><?php echo htmlspecialchars(stripslashes($mission['nature_intervention'])); ?></div>
                        </div>
                        <?php if($mission['adresse_destination']): ?>
                        <div class="info-item">
                            <div class="info-label">Destination</div>
                            <div class="info-value"><?php echo htmlspecialchars(stripslashes($mission['adresse_destination'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" action="" id="duplicateForm">
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="id_mission" value="<?php echo $mission['id_mission']; ?>">

                    <div class="info-box">
                        <strong>ℹ️ Comment ça marche ?</strong><br>
                        La mission sera dupliquée le même jour de la semaine (<?php 
                            $dt = new DateTime($mission['date_mission']);
                            $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                            echo $jours[$dt->format('w')];
                        ?>) à la même heure pour le nombre de semaines indiqué.
                    </div>

                    <div class="form-group">
                        <label for="nb_semaines">Nombre de semaines à dupliquer *</label>
                        <input type="number" 
                               id="nb_semaines" 
                               name="nb_semaines" 
                               min="1" 
                               max="52" 
                               value="4" 
                               required>
                        <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                            Entrez un nombre entre 1 et 52
                        </small>
                    </div>

                    <div class="calendar-preview" id="preview">
                        <h4>📅 Aperçu des dates qui seront créées</h4>
                        <div id="preview-list"></div>
                    </div>

                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            🔄 Dupliquer la mission
                        </button>
                        <a href="?" class="btn btn-secondary">
                            ↺ Nouvelle recherche
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <div class="no-selection">
                    <p>👆 Veuillez rechercher et sélectionner une mission à dupliquer</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Données des missions pour l'autocomplétion
        const missionsData = [
            <?php foreach($missions as $m): ?>
            {
                id: <?php echo $m['id_mission']; ?>, 
                text: "<?php echo formatDateFr($m['date_mission']) . ' ' . substr($m['heure_rdv'], 0, 5); ?> - <?php echo addslashes(stripslashes($m['aide'])); ?> (<?php echo addslashes(stripslashes($m['benevole'])); ?>)",
                searchText: "<?php echo strtolower(addslashes(stripslashes($m['aide'] . ' ' . $m['benevole']))); ?>"
            },
            <?php endforeach; ?>
        ];

        const searchInput = document.getElementById('search_input');
        let currentFocus = -1;

        // Fonction de recherche et affichage des suggestions
        searchInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            closeAllLists();
            
            if (!val || val.length < 2) {
                return false;
            }
            
            currentFocus = -1;
            const autocompleteList = document.getElementById('autocomplete-list');
            
            // Filtrer les missions correspondantes
            const filtered = missionsData.filter(mission => 
                mission.searchText.includes(val)
            ).slice(0, 10); // Limiter à 10 résultats
            
            // Afficher les résultats
            if (filtered.length > 0) {
                filtered.forEach(mission => {
                    const div = document.createElement('div');
                    div.innerHTML = mission.text;
                    div.innerHTML += '<input type="hidden" value="' + mission.id + '">';
                    
                    // Clic sur une suggestion
                    div.addEventListener('click', function() {
                        const missionId = this.getElementsByTagName('input')[0].value;
                        window.location.href = '?id=' + missionId;
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

        // Navigation au clavier
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

        // Aperçu des dates
        <?php if($mission): ?>
        const dateOriginale = new Date('<?php echo $mission['date_mission']; ?>');
        const joursTexte = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        
        function updatePreview() {
            const nbSemaines = parseInt(document.getElementById('nb_semaines').value) || 0;
            const previewList = document.getElementById('preview-list');
            previewList.innerHTML = '';
            
            if (nbSemaines < 1 || nbSemaines > 52) {
                previewList.innerHTML = '<p style="color: #999; text-align: center;">Entrez un nombre entre 1 et 52</p>';
                return;
            }
            
            for (let i = 1; i <= nbSemaines; i++) {
                const newDate = new Date(dateOriginale);
                newDate.setDate(newDate.getDate() + (i * 7));
                
                const dateStr = newDate.toLocaleDateString('fr-FR');
                const jourStr = joursTexte[newDate.getDay()];
                
                const div = document.createElement('div');
                div.className = 'date-item';
                div.innerHTML = `
                    <span class="date">Semaine +${i} : ${dateStr}</span>
                    <span class="day">${jourStr}</span>
                `;
                previewList.appendChild(div);
            }
        }
        
        document.getElementById('nb_semaines').addEventListener('input', updatePreview);
        
        // Initialiser l'aperçu
        updatePreview();
        <?php endif; ?>
    </script>
</body>
</html>
