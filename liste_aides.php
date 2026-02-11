<?php
// Charger la configuration
		require_once('config.php');
     require_once('auth.php');
verifierRole(['admin', 'chauffeur','gestionnaire']);
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

// Récupérer toutes les aides
$aides = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$secteur_filter = isset($_GET['secteur']) ? $_GET['secteur'] : '';

try {
    $sql = "SELECT * FROM EPI_aide WHERE 1=1";
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
    $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des aidés : " . $e->getMessage();
}

// Récupérer les secteurs pour le filtre
$secteurs = [];
try {
    $stmt = $conn->query("SELECT DISTINCT secteur FROM EPI_aide WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur");
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
    <title>Liste des Aidés</title>
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

        th.city-col {
            max-width: 140px;
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

        td.city-cell {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
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
            
            /* Ensure name cells don't overflow */
            td strong {
                max-width: 100%;
                word-wrap: break-word;
                overflow-wrap: break-word;
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
                max-width: 100%;
                word-break: break-word;
                white-space: normal;
                line-height: 1.2;
            }
        }

        /* Style pour les aidés inactifs */
        .aide-inactif {
            opacity: 0.5;
            background-color: #f8f8f8 !important;
        }

        .aide-inactif td {
            text-decoration: line-through;
            color: #999 !important;
        }

        .aide-inactif strong {
            color: #999 !important;
        }

        .aide-inactif a {
            color: #999 !important;
            pointer-events: none;
        }

        /* Style pour les aidés sans cotisation 2026 */
        .nom-sans-cotisation {
            background-color: #ffe6f0 !important;
            padding: 2px 4px;
            border-radius: 3px;
            border: 1px solid #ff69b4 !important;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .nom-sans-cotisation {
                padding: 1px 3px;
                border-width: 1px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link" title="Retour au tableau de bord">🏠</a>

    <div class="container">
        <h1>🤝 Liste des Aidés</h1>

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
                <a href="liste_aides.php" style="padding: 8px 12px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 12px;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <?php 
        // Compter uniquement les aidés actifs (sans date de fin)
        $aidesActifs = array_filter($aides, function($a) {
            return empty($a['date_fin']);
        });
        $nbActifs = count($aidesActifs);
        $nbTotal = count($aides);
        $nbInactifs = $nbTotal - $nbActifs;
        ?>
        
        <div class="stats">
            <?php echo $nbActifs; ?> aidé<?php echo $nbActifs > 1 ? 's' : ''; ?> actif<?php echo $nbActifs > 1 ? 's' : ''; ?>
            <?php if($nbInactifs > 0): ?>
                <span style="color: #999; font-size: 11px; margin-left: 10px;">
                    (<?php echo $nbInactifs; ?> inactif<?php echo $nbInactifs > 1 ? 's' : ''; ?>)
                </span>
            <?php endif; ?>
        </div>

        <?php if(empty($aides)): ?>
            <div class="no-results">
                😕 Aucun aidé trouvé avec ces critères.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Secteur</th>
                            <th>Adresse</th>
                            <th class="city-col">Ville</th>
                            <th class="comment-col">Commentaires</th>
                            <th class="email-col">Email</th>
                            <th>Tél. Fixe</th>
                            <th>Tél. Portable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($aides as $aide): ?>
                            <tr class="<?php echo !empty($aide['date_fin']) ? 'aide-inactif' : ''; ?>" onclick="showDetails(<?php echo htmlspecialchars(json_encode($aide), ENT_QUOTES, 'UTF-8'); ?>)">
                                <td>
                                    <strong class="<?php echo (empty($aide['p_2026']) && $aide['p_2026'] !== 0 && $aide['p_2026'] !== '0') ? 'nom-sans-cotisation' : ''; ?>">
                                        <?php echo htmlspecialchars($aide['nom']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if($aide['secteur']): ?>
                                        <span class="badge badge-secteur"><?php echo htmlspecialchars($aide['secteur']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $aide['adresse'] ? htmlspecialchars($aide['adresse']) : '-'; ?></td>
                                <td class="city-cell" title="<?php echo $aide['code_postal'] ? htmlspecialchars($aide['code_postal']) . ' ' . htmlspecialchars($aide['commune']) : ''; ?>">
                                    <?php 
                                    if($aide['code_postal'] && $aide['commune']) {
                                        echo htmlspecialchars($aide['code_postal']) . ' ' . htmlspecialchars($aide['commune']);
                                    } elseif($aide['commune']) {
                                        echo htmlspecialchars($aide['commune']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="comment-cell" title="<?php echo $aide['commentaires'] ? htmlspecialchars($aide['commentaires']) : ''; ?>">
                                    <?php echo $aide['commentaires'] ? htmlspecialchars($aide['commentaires']) : '-'; ?>
                                </td>
                                <td class="email-cell" title="<?php echo $aide['courriel'] ? htmlspecialchars($aide['courriel']) : ''; ?>" onclick="event.stopPropagation()">
                                    <?php if($aide['courriel']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($aide['courriel']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            ✉️ <?php echo htmlspecialchars($aide['courriel']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <?php if($aide['tel_fixe']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($aide['tel_fixe']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            📞 <?php echo htmlspecialchars($aide['tel_fixe']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <?php if($aide['tel_portable']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($aide['tel_portable']); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                            📱 <?php echo htmlspecialchars($aide['tel_portable']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
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
        function showDetails(aide) {
            const modal = document.getElementById('detailModal');
            const modalNom = document.getElementById('modalNom');
            const modalBody = document.getElementById('modalBody');
            
            modalNom.textContent = '🤝 ' + aide.nom;
            
            let html = '';
            
            // Informations personnelles
            html += '<div class="detail-section"><h4>📋 Informations personnelles</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Date de naissance</strong><span>' + (aide.date_naissance || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Adresse</strong><span>' + (aide.adresse || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Commune</strong><span>' + (aide.commune || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Code postal</strong><span>' + (aide.code_postal || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone fixe</strong><span>' + (aide.tel_fixe || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Téléphone portable</strong><span>' + (aide.tel_portable || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Email</strong><span>' + (aide.courriel || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Secteur</strong><span>' + (aide.secteur || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
            
            // Contact et lien parental
            html += '<div class="detail-section"><h4>👥 Contact et lien parental</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Téléphone contact</strong><span>' + (aide.tel_contact || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Lien parenté</strong><span>' + (aide.lien_parente || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Nom contact</strong><span>' + (aide.nom_contact || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
            
            // Dates importantes
            html += '<div class="detail-section"><h4>📅 Dates</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Date début</strong><span>' + (aide.date_debut || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>Date fin</strong><span>' + (aide.date_fin || 'Non renseignée') + '</span></div>';
            html += '<div class="detail-item"><strong>P 2026</strong><span>' + (aide.p_2026 || 'Non renseigné') + '</span></div>';
            html += '</div></div>';
            
            // Observations et commentaires
            html += '<div class="detail-section"><h4>📝 Observations</h4>';
            if (aide.commentaires) {
                html += '<div class="detail-item" style="margin-bottom: 10px;"><strong>Commentaires</strong><span>' + aide.commentaires + '</span></div>';
            }
            html += '<div class="detail-grid">';
            html += '<div class="detail-item"><strong>Moyen</strong><span>' + (aide.moyen || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Date observation</strong><span>' + (aide.date_observation || 'Non renseignée') + '</span></div>';
            html += '</div>';
            if (aide.observation) {
                html += '<div class="detail-item" style="margin-top: 10px;"><strong>Observation</strong><span>' + aide.observation + '</span></div>';
            }
            html += '</div>';
            
            // Dons
            html += '<div class="detail-section"><h4>💰 Dons</h4><div class="detail-grid">';
            html += '<div class="detail-item"><strong>Don</strong><span>' + (aide.don || 'Non renseigné') + '</span></div>';
            html += '<div class="detail-item"><strong>Date don</strong><span>' + (aide.date_don || 'Non renseignée') + '</span></div>';
            html += '</div>';
            if (aide.don_observation) {
                html += '<div class="detail-item" style="margin-top: 10px;"><strong>Observation don</strong><span>' + aide.don_observation + '</span></div>';
            }
            html += '</div>';
            
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