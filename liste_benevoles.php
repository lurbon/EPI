<?php
// Charger la configuration WordPress
		require_once('wp-config.php');
     require_once('auth.php');
verifierRole(['admin', 'benevole']);
// Connexion à la base de données
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

// Récupérer tous les bénévoles
$benevoles = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$secteur_filter = isset($_GET['secteur']) ? $_GET['secteur'] : '';

try {
    $sql = "SELECT * FROM EPI_benevole WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (nom LIKE :search OR commune LIKE :search OR courriel LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($secteur_filter) {
        $sql .= " AND secteur = :secteur";
        $params[':secteur'] = $secteur_filter;
    }
    
    $sql .= " ORDER BY nom ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des bénévoles : " . $e->getMessage();
}

// Récupérer les secteurs pour le filtre
$secteurs = [];
try {
    $stmt = $conn->query("SELECT DISTINCT secteur FROM EPI_benevole WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur");
    $secteurs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    // Continuer sans filtre
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Bénévoles</title>
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
            max-width: 1600px;
            margin: 0 auto;
        }

        h1 {
            color: #667eea;
            margin-bottom: 25px;
            text-align: center;
            font-size: 28px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 250px;
        }

        .filters select {
            min-width: 180px;
        }

        .filters input:focus,
        .filters select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filters button {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .filters button:hover {
            transform: translateY(-2px);
        }

        .stats {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            color: #667eea;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        th.day-col {
            text-align: center;
            padding: 12px 6px;
        }

        th.address-col {
            max-width: 200px;
        }

        th.email-col {
            max-width: 180px;
        }

        th.comment-col {
            max-width: 200px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 12px;
            color: #333;
        }

        td.address-cell {
            max-width: 200px;
            font-size: 11px;
            line-height: 1.4;
        }

        td.address-cell .address-line {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        td.address-cell .city-line {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #666;
            font-size: 10px;
        }

        td.email-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
        }

        td.comment-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
            font-style: italic;
            color: #555;
        }

        td.day-cell {
            text-align: center;
            padding: 10px 6px;
            font-size: 11px;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-secteur {
            background: #e3f2fd;
            color: #1976d2;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover {
            color: #667eea;
        }

        .modal-header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 20px;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-item {
            font-size: 12px;
        }

        .detail-item strong {
            display: block;
            color: #666;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .detail-item span {
            color: #333;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            th, td {
                padding: 6px 4px;
                font-size: 11px;
            }

            .filters {
                flex-direction: column;
            }

            .filters input[type="text"],
            .filters select {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au dashboard</a>

    <div class="container">
        <h1>📋 Liste des Bénévoles</h1>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher par nom, ville, email..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="secteur">
                <option value="">Tous les secteurs</option>
                <?php foreach($secteurs as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $secteur_filter === $s ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrer</button>
            <?php if($search || $secteur_filter): ?>
                <a href="liste_benevoles.php" style="padding: 8px 12px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 12px;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <?php echo count($benevoles); ?> bénévole<?php echo count($benevoles) > 1 ? 's' : ''; ?> trouvé<?php echo count($benevoles) > 1 ? 's' : ''; ?>
        </div>

        <?php if(empty($benevoles)): ?>
            <div class="no-results">
                😕 Aucun bénévole trouvé avec ces critères.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Secteur</th>
                            <th class="address-col">Adresse / Ville</th>
                            <th class="comment-col">Commentaires</th>
                            <th class="email-col">Email</th>
                            <th>Tél. Fixe</th>
                            <th>Tél. Mobile</th>
                            <th class="day-col">Lun</th>
                            <th class="day-col">Mar</th>
                            <th class="day-col">Mer</th>
                            <th class="day-col">Jeu</th>
                            <th class="day-col">Ven</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($benevoles as $benevole): ?>
                            <tr onclick="showDetails(<?php echo htmlspecialchars(json_encode($benevole), ENT_QUOTES, 'UTF-8'); ?>)">
                                <td><strong><?php echo htmlspecialchars($benevole['nom']); ?></strong></td>
                                <td>
                                    <?php if($benevole['secteur']): ?>
                                        <span class="badge badge-secteur"><?php echo htmlspecialchars($benevole['secteur']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="address-cell" title="<?php echo ($benevole['adresse'] ? htmlspecialchars($benevole['adresse']) . ' - ' : '') . ($benevole['code_postal'] ? htmlspecialchars($benevole['code_postal']) . ' ' : '') . ($benevole['commune'] ? htmlspecialchars($benevole['commune']) : ''); ?>">
                                    <span class="address-line">
                                        <?php echo $benevole['adresse'] ? htmlspecialchars($benevole['adresse']) : 'Adresse non renseignée'; ?>
                                    </span>
                                    <span class="city-line">
                                        <?php 
                                        if($benevole['code_postal'] && $benevole['commune']) {
                                            echo htmlspecialchars($benevole['code_postal']) . ' ' . htmlspecialchars($benevole['commune']);
                                        } elseif($benevole['commune']) {
                                            echo htmlspecialchars($benevole['commune']);
                                        } else {
                                            echo 'Ville non renseignée';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="comment-cell" title="<?php echo $benevole['commentaires'] ? htmlspecialchars($benevole['commentaires']) : ''; ?>">
                                    <?php echo $benevole['commentaires'] ? htmlspecialchars($benevole['commentaires']) : '-'; ?>
                                </td>
                                <td class="email-cell" title="<?php echo $benevole['courriel'] ? htmlspecialchars($benevole['courriel']) : ''; ?>" onclick="event.stopPropagation()">
                                    <?php if($benevole['courriel']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($benevole['courriel']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            ✉️ <?php echo htmlspecialchars($benevole['courriel']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <?php if($benevole['tel_fixe']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($benevole['tel_fixe']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            📞 <?php echo htmlspecialchars($benevole['tel_fixe']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <?php if($benevole['tel_mobile']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($benevole['tel_mobile']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            📱 <?php echo htmlspecialchars($benevole['tel_mobile']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="day-cell"><?php echo $benevole['lundi'] ? htmlspecialchars($benevole['lundi']) : '-'; ?></td>
                                <td class="day-cell"><?php echo $benevole['mardi'] ? htmlspecialchars($benevole['mardi']) : '-'; ?></td>
                                <td class="day-cell"><?php echo $benevole['mercredi'] ? htmlspecialchars($benevole['mercredi']) : '-'; ?></td>
                                <td class="day-cell"><?php echo $benevole['jeudi'] ? htmlspecialchars($benevole['jeudi']) : '-'; ?></td>
                                <td class="day-cell"><?php echo $benevole['vendredi'] ? htmlspecialchars($benevole['vendredi']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal pour les détails -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalNom"></h2>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function showDetails(benevole) {
            const modal = document.getElementById('detailModal');
            const modalNom = document.getElementById('modalNom');
            const modalBody = document.getElementById('modalBody');
            
            modalNom.textContent = '👤 ' + benevole.nom;
            
            let html = '';
            
            // Informations personnelles
            html += '<div class="detail-section"><h4>📋 Informations personnelles</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Date de naissance</strong><span>' + (benevole.date_naissance || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Adresse</strong><span>' + (benevole.adresse || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Commune</strong><span>' + (benevole.commune || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Code postal</strong><span>' + (benevole.code_postal || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone fixe</strong><span>' + (benevole.tel_fixe || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone mobile</strong><span>' + (benevole.tel_mobile || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Email</strong><span>' + (benevole.courriel || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Secteur</strong><span>' + (benevole.secteur || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
            
            // Disponibilités
            html += '<div class="detail-section"><h4>📅 Disponibilités</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Lundi</strong><span>' + (benevole.lundi || 'Non disponible') + '</span></div>';
            html += '<div class="detail-item"><strong>Mardi</strong><span>' + (benevole.mardi || 'Non disponible') + '</span></div>';
            html += '<div class="detail-item"><strong>Mercredi</strong><span>' + (benevole.mercredi || 'Non disponible') + '</span></div>';
            html += '<div class="detail-item"><strong>Jeudi</strong><span>' + (benevole.jeudi || 'Non disponible') + '</span></div>';
            html += '<div class="detail-item"><strong>Vendredi</strong><span>' + (benevole.vendredi || 'Non disponible') + '</span></div>';
            html += '<div class="detail-item"><strong>Début</strong><span>' + (benevole.debut || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Fin</strong><span>' + (benevole.fin || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
			
            // Autres informations
            html += '<div class="detail-section"><h4>📝 Autres informations</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Utilisation mail</strong><span>' + (benevole.flag_mail || 'Non renseigné') + '</span></div>';
            html += '</div>';
            if (benevole.commentaires) {
                html += '<div class="detail-item" style="margin-top: 10px;"><strong>Commentaires</strong><span>' + benevole.commentaires + '</span></div>';
            }
            html += '</div>';   
			
            // Véhicule
            html += '<div class="detail-section"><h4>🚗 Véhicule</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Immatriculation</strong><span>' + (benevole.immatriculation || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Chevaux fiscaux</strong><span>' + (benevole.chevaux_fiscaux || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Type</strong><span>' + (benevole.type || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
                        
            // Dons et paiements
            html += '<div class="detail-section"><h4>💰 Dons et paiements</h4><div class="detail-grid">';
			html += '<div class="detail-item"><strong>Paiement 2026</strong><span>' + (benevole.p_2026 || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Moyen paiement</strong><span>' + (benevole.moyen || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Date cotisation</strong><span>' + (benevole.date_1 || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Observations cotisation</strong><span>' + (benevole.observations_1 || 'Non renseignées') + '</span></div>';
            html += '<div class="detail-item"><strong>Don</strong><span>' + (benevole.dons || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Date don</strong><span>' + (benevole.date_2 || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Observations don</strong><span>' + (benevole.observations_2 || 'Non renseignées') + '</span></div>';
            html += '</div></div>';
            
            modalBody.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Fermer avec la touche Echap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>