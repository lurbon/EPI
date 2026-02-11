<?php
/**
 * PAGE DE CONSULTATION DE L'HISTORIQUE DES ENVOIS DE MISSIONS
 * 
 * Fichier: historique_envois.php
 * Accès: Réservé aux admins et gestionnaires
 */

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

// ⭐ TRAITEMENT AJAX - Doit être AU DÉBUT avant toute sortie HTML
if (isset($_GET['ajax_details'])) {
    $id = intval($_GET['ajax_details']);
    
    try {
        $sql = "SELECT * FROM EPI_envoi WHERE id_historique = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $envoi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($envoi) {
            $envoi['missions_ids'] = json_decode($envoi['missions_ids'], true);
            $envoi['destinataires'] = json_decode($envoi['destinataires'], true);
            $date = new DateTime($envoi['date_envoi']);
            $envoi['date_envoi'] = $date->format('d/m/Y à H:i:s');
            
            // ⭐ NOUVEAU : Récupérer les détails des missions
            $missionsDetails = [];
            if (!empty($envoi['missions_ids']) && is_array($envoi['missions_ids'])) {
                $placeholders = str_repeat('?,', count($envoi['missions_ids']) - 1) . '?';
                $sqlMissions = "SELECT 
                                    m.id_mission,
                                    m.date_mission,
                                    m.heure_rdv,
                                    a.nom as aide_nom
                                FROM EPI_mission m
                                LEFT JOIN EPI_aide a ON m.id_aide = a.id_aide
                                WHERE m.id_mission IN ($placeholders)
                                ORDER BY m.date_mission, m.heure_rdv";
                
                $stmtMissions = $conn->prepare($sqlMissions);
                $stmtMissions->execute($envoi['missions_ids']);
                $missionsDetails = $stmtMissions->fetchAll(PDO::FETCH_ASSOC);
            }
            $envoi['missions_details'] = $missionsDetails;
            
            header('Content-Type: application/json');
            echo json_encode($envoi);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Envoi non trouvé']);
        }
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit; // IMPORTANT: Arrêter l'exécution ici pour AJAX
}

// Paramètres de pagination et filtrage
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$filterEmetteur = isset($_GET['emetteur']) ? $_GET['emetteur'] : '';
$filterSecteur = isset($_GET['secteur']) ? $_GET['secteur'] : '';
$filterDateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$filterDateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construire la clause WHERE
$whereConditions = [];
$params = [];

if (!empty($filterEmetteur)) {
    $whereConditions[] = "email_emetteur = :emetteur";
    $params['emetteur'] = $filterEmetteur;
}

if (!empty($filterSecteur)) {
    $whereConditions[] = "secteur = :secteur";
    $params['secteur'] = $filterSecteur;
}

if (!empty($filterDateDebut)) {
    $whereConditions[] = "DATE(date_envoi) >= :date_debut";
    $params['date_debut'] = $filterDateDebut;
}

if (!empty($filterDateFin)) {
    $whereConditions[] = "DATE(date_envoi) <= :date_fin";
    $params['date_fin'] = $filterDateFin;
}

$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Compter le total
$sqlCount = "SELECT COUNT(*) as total FROM EPI_envoi $whereClause";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Récupérer les données paginées
$sql = "SELECT 
            id_historique,
            email_emetteur,
            date_envoi,
            missions_ids,
            destinataires,
            nb_missions,
            nb_destinataires,
            secteur,
            sujet_email
        FROM EPI_envoi
        $whereClause
        ORDER BY date_envoi DESC
        LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$envois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des émetteurs pour le filtre
$sqlEmetteurs = "SELECT DISTINCT email_emetteur FROM EPI_envoi ORDER BY email_emetteur";
$stmtEmetteurs = $conn->prepare($sqlEmetteurs);
$stmtEmetteurs->execute();
$emetteurs = $stmtEmetteurs->fetchAll(PDO::FETCH_COLUMN);

// Récupérer la liste des secteurs pour le filtre
$sqlSecteurs = "SELECT DISTINCT secteur FROM EPI_envoi WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur";
$stmtSecteurs = $conn->prepare($sqlSecteurs);
$stmtSecteurs->execute();
$secteurs = $stmtSecteurs->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Envois - Entraide Plus Iroise</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .filters {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .content {
            padding: 30px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead {
            background: #667eea;
            color: white;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-missions {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-destinataires {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .secteur-tag {
            display: inline-block;
            padding: 3px 8px;
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
            font-size: 12px;
        }

        .details-btn {
            padding: 5px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .details-btn:hover {
            background: #5568d3;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            font-size: 14px;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination a:hover {
            background: #f0f4ff;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #667eea;
        }

        .detail-row {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        .detail-value {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
            word-wrap: break-word;
        }

        .list-item {
            padding: 8px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .close-modal {
            float: right;
            font-size: 24px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='dashboard.php'" class="back-link" title="Retour au tableau de bord">🏠</button>

    <div class="container">
        <div class="header">
            <h1>📊 Historique des Envois de Missions</h1>
            <p>Suivi des emails envoyés aux bénévoles</p>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="emetteur">Émetteur</label>
                        <select name="emetteur" id="emetteur">
                            <option value="">Tous les émetteurs</option>
                            <?php foreach ($emetteurs as $emetteur): ?>
                                <option value="<?php echo htmlspecialchars($emetteur); ?>" 
                                    <?php echo $filterEmetteur === $emetteur ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emetteur); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="secteur">Secteur</label>
                        <select name="secteur" id="secteur">
                            <option value="">Tous les secteurs</option>
                            <?php foreach ($secteurs as $secteur): ?>
                                <option value="<?php echo htmlspecialchars($secteur); ?>" 
                                    <?php echo $filterSecteur === $secteur ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($secteur); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_debut">Date début</label>
                        <input type="date" name="date_debut" id="date_debut" value="<?php echo htmlspecialchars($filterDateDebut); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_fin">Date fin</label>
                        <input type="date" name="date_fin" id="date_fin" value="<?php echo htmlspecialchars($filterDateFin); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                    <a href="historique_envois.php" class="btn btn-secondary">🔄 Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Tableau des envois -->
        <div class="content">
            <?php if (count($envois) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Heure</th>
                                <th>Émetteur</th>
                                <th>Missions</th>
                                <th>Destinataires</th>
                                <th>Secteur</th>
                                <th>Sujet</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($envois as $envoi): ?>
                                <tr>
                                    <td><?php echo $envoi['id_historique']; ?></td>
                                    <td>
                                        <?php 
                                        $date = new DateTime($envoi['date_envoi']);
                                        echo $date->format('d/m/Y à H:i'); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($envoi['email_emetteur']); ?></td>
                                    <td>
                                        <span class="badge badge-missions">
                                            📋 <?php echo $envoi['nb_missions']; ?> mission<?php echo $envoi['nb_missions'] > 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-destinataires">
                                            👥 <?php echo $envoi['nb_destinataires']; ?> bénévole<?php echo $envoi['nb_destinataires'] > 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($envoi['secteur'])): ?>
                                            <span class="secteur-tag"><?php echo htmlspecialchars($envoi['secteur']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($envoi['sujet_email'], 0, 30)) . (strlen($envoi['sujet_email']) > 30 ? '...' : ''); ?></td>
                                    <td>
                                        <button class="details-btn" onclick="showDetails(<?php echo $envoi['id_historique']; ?>)">
                                            👁️ Détails
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filterEmetteur) ? '&emetteur=' . urlencode($filterEmetteur) : ''; ?><?php echo !empty($filterSecteur) ? '&secteur=' . urlencode($filterSecteur) : ''; ?><?php echo !empty($filterDateDebut) ? '&date_debut=' . $filterDateDebut : ''; ?><?php echo !empty($filterDateFin) ? '&date_fin=' . $filterDateFin : ''; ?>">
                                ← Précédent
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($filterEmetteur) ? '&emetteur=' . urlencode($filterEmetteur) : ''; ?><?php echo !empty($filterSecteur) ? '&secteur=' . urlencode($filterSecteur) : ''; ?><?php echo !empty($filterDateDebut) ? '&date_debut=' . $filterDateDebut : ''; ?><?php echo !empty($filterDateFin) ? '&date_fin=' . $filterDateFin : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filterEmetteur) ? '&emetteur=' . urlencode($filterEmetteur) : ''; ?><?php echo !empty($filterSecteur) ? '&secteur=' . urlencode($filterSecteur) : ''; ?><?php echo !empty($filterDateDebut) ? '&date_debut=' . $filterDateDebut : ''; ?><?php echo !empty($filterDateFin) ? '&date_fin=' . $filterDateFin : ''; ?>">
                                Suivant →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>📭 Aucun envoi trouvé avec ces critères.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal des détails -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal" onclick="closeModal()">&times;</span>
                <h2>Détails de l'envoi</h2>
            </div>
            <div id="modal-body">
                <p style="text-align: center; color: #999;">Chargement...</p>
            </div>
        </div>
    </div>

    <script>
        function showDetails(id) {
            const modal = document.getElementById('detailsModal');
            const body = document.getElementById('modal-body');
            
            modal.classList.add('active');
            body.innerHTML = '<p style="text-align: center; color: #999;">⏳ Chargement...</p>';
            
            fetch('?ajax_details=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        body.innerHTML = '<p style="color: red;">❌ ' + data.error + '</p>';
                        return;
                    }
                    
                    let html = '';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">📧 Émetteur</div>';
                    html += '<div class="detail-value">' + data.email_emetteur + '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">📅 Date et heure</div>';
                    html += '<div class="detail-value">' + data.date_envoi + '</div>';
                    html += '</div>';
                    
                    if (data.secteur) {
                        html += '<div class="detail-row">';
                        html += '<div class="detail-label">🗺️ Secteur</div>';
                        html += '<div class="detail-value">' + data.secteur + '</div>';
                        html += '</div>';
                    }
                    
                    if (data.sujet_email) {
                        html += '<div class="detail-row">';
                        html += '<div class="detail-label">✉️ Sujet</div>';
                        html += '<div class="detail-value">' + data.sujet_email + '</div>';
                        html += '</div>';
                    }
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">📋 Missions envoyées (' + data.missions_ids.length + ')</div>';
                    html += '<div class="detail-value">';
                    
                    // ⭐ NOUVEAU : Afficher les détails complets des missions
                    if (data.missions_details && data.missions_details.length > 0) {
                        data.missions_details.forEach(mission => {
                            html += '<div class="list-item" style="padding: 12px; margin-bottom: 8px; border-left: 3px solid #667eea;">';
                            html += '<div style="font-weight: 600; color: #667eea; margin-bottom: 5px;">Mission #' + mission.id_mission + '</div>';
                            
                            // Date et heure
                            if (mission.date_mission) {
                                const dateParts = mission.date_mission.split('-');
                                const dateFormatted = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
                                html += '<div style="font-size: 13px; color: #555; margin-bottom: 3px;">';
                                html += '📅 ' + dateFormatted;
                                if (mission.heure_rdv) {
                                    html += ' à ' + mission.heure_rdv.substring(0, 5);
                                }
                                html += '</div>';
                            }
                            
                            // Nom de l'aidé
                            if (mission.aide_nom) {
                                html += '<div style="font-size: 13px; color: #555;">';
                                html += '👤 ' + mission.aide_nom;
                                html += '</div>';
                            }
                            
                            html += '</div>';
                        });
                    } else {
                        // Fallback si pas de détails
                        data.missions_ids.forEach(id => {
                            html += '<div class="list-item">Mission #' + id + '</div>';
                        });
                    }
                    
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">👥 Destinataires (' + data.destinataires.length + ')</div>';
                    html += '<div class="detail-value">';
                    data.destinataires.forEach(email => {
                        html += '<div class="list-item">' + email + '</div>';
                    });
                    html += '</div>';
                    html += '</div>';
                    
                    body.innerHTML = html;
                })
                .catch(error => {
                    body.innerHTML = '<p style="color: red;">❌ Erreur: ' + error + '</p>';
                });
        }
        
        function closeModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
