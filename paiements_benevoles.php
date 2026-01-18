<?php
// Charger la configuration WordPress
require_once('wp-config.php');
require_once('auth.php');
verifierRole('admin');

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

// Traitement de la mise à jour GLOBALE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['benevoles'])) {
    try {
        $conn->beginTransaction();
        $updateCount = 0;
        
        foreach ($_POST['benevoles'] as $id_benevole => $data) {
            $sql = "UPDATE EPI_benevole SET 
                    p_2026 = :p_2026,
                    moyen = :moyen,
                    date_1 = :date_1,
                    observations_1 = :observations_1,
                    dons = :dons,
                    date_2 = :date_2,
                    observations_2 = :observations_2
                    WHERE id_benevole = :id_benevole";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':p_2026' => !empty($data['p_2026']) ? $data['p_2026'] : null,
                ':moyen' => !empty($data['moyen']) ? $data['moyen'] : null,
                ':date_1' => !empty($data['date_1']) ? $data['date_1'] : null,
                ':observations_1' => !empty($data['observations_1']) ? $data['observations_1'] : null,
                ':dons' => !empty($data['dons']) ? $data['dons'] : null,
                ':date_2' => !empty($data['date_2']) ? $data['date_2'] : null,
                ':observations_2' => !empty($data['observations_2']) ? $data['observations_2'] : null,
                ':id_benevole' => $id_benevole
            ]);
            $updateCount++;
        }
        
        $conn->commit();
        $message = "✅ $updateCount bénévole(s) mis à jour avec succès !";
        $messageType = "success";
        
    } catch(PDOException $e) {
        $conn->rollBack();
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer tous les bénévoles avec leurs infos de paiement
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_paiement = isset($_GET['filter_paiement']) ? $_GET['filter_paiement'] : '';

try {
    $sql = "SELECT id_benevole, nom, adresse, commune, code_postal, 
            p_2026, moyen, date_1, observations_1, 
            dons, date_2, observations_2
            FROM EPI_benevole WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (nom LIKE :search OR commune LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($filter_paiement === 'paye') {
        $sql .= " AND p_2026 IS NOT NULL AND p_2026 != ''";
    } elseif ($filter_paiement === 'non_paye') {
        $sql .= " AND (p_2026 IS NULL OR p_2026 = '')";
    }
    
    $sql .= " ORDER BY nom ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $benevoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - Bénévoles</title>
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
            max-width: 1800px;
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

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filters input,
        .filters select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filters input:focus,
        .filters select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 250px;
        }

        .filters button,
        .filters a {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .filters button:hover,
        .filters a:hover {
            transform: translateY(-2px);
        }

        .filters a.reset {
            background: #e0e0e0;
            color: #333;
        }

        .stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-item .label {
            font-size: 14px;
            color: #666;
        }

        .save-bar {
            position: sticky;
            top: 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
            z-index: 100;
        }

        .save-bar-text {
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .btn-save-all {
            padding: 12px 30px;
            background: white;
            color: #28a745;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-save-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-save-all:active {
            transform: translateY(0);
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
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        tbody tr.modified {
            background-color: #fff3cd;
        }

        .edit-input,
        .edit-select,
        .edit-textarea {
            width: 100%;
            padding: 6px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .edit-input:focus,
        .edit-select:focus,
        .edit-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .edit-input.changed,
        .edit-select.changed,
        .edit-textarea.changed {
            border-color: #ffc107;
            background-color: #fffbeb;
        }

        .edit-textarea {
            resize: vertical;
            min-height: 50px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 1400px) {
            th, td {
                font-size: 11px;
                padding: 6px;
            }
            
            .edit-input,
            .edit-select,
            .edit-textarea {
                font-size: 11px;
                padding: 5px 6px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .stats {
                flex-direction: column;
            }

            .save-bar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .table-wrapper {
                font-size: 10px;
            }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .loading::after {
            content: ' ⏳';
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Retour au dashboard</a>

    <div class="container">
        <h1>💰 Gestion des Paiements</h1>

        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher par nom ou ville..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter_paiement">
                <option value="">Tous</option>
                <option value="paye" <?php echo $filter_paiement === 'paye' ? 'selected' : ''; ?>>Payés uniquement</option>
                <option value="non_paye" <?php echo $filter_paiement === 'non_paye' ? 'selected' : ''; ?>>Non payés uniquement</option>
            </select>
            <button type="submit">Filtrer</button>
            <?php if($search || $filter_paiement): ?>
                <a href="paiements_benevoles.php" class="reset">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo count($benevoles); ?></div>
                <div class="label">Total bénévoles</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #28a745;">
                    <?php echo count(array_filter($benevoles, function($b) { return !empty($b['p_2026']); })); ?>
                </div>
                <div class="label">Cotisations payées</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #dc3545;">
                    <?php echo count(array_filter($benevoles, function($b) { return empty($b['p_2026']); })); ?>
                </div>
                <div class="label">Cotisations en attente</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #ffc107;">
                    <?php echo count(array_filter($benevoles, function($b) { return !empty($b['dons']); })); ?>
                </div>
                <div class="label">Dons reçus</div>
            </div>
        </div>

        <form method="POST" id="mainForm">
            <div class="save-bar">
                <div class="save-bar-text">
                    💡 Modifiez les champs directement dans le tableau ci-dessous
                </div>
                <button type="submit" class="btn-save-all">
                    💾 Enregistrer toutes les modifications
                </button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 150px;">Nom</th>
                            <th style="width: 120px;">Ville</th>
                            <th style="width: 100px;">Cotis. 2026</th>
                            <th style="width: 100px;">Moyen</th>
                            <th style="width: 100px;">Date cotis.</th>
                            <th style="width: 150px;">Obs. cotisation</th>
                            <th style="width: 100px;">Don</th>
                            <th style="width: 100px;">Date don</th>
                            <th style="width: 150px;">Obs. don</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($benevoles as $b): ?>
                            <tr id="row-<?php echo $b['id_benevole']; ?>">
                                <td><strong><?php echo htmlspecialchars($b['nom']); ?></strong></td>
                                <td>
                                    <small style="color: #666;">
                                        <?php echo htmlspecialchars($b['commune'] ?: '-'); ?>
                                        <?php if($b['code_postal']): ?>
                                            (<?php echo htmlspecialchars($b['code_postal']); ?>)
                                        <?php endif; ?>
                                    </small>
                                </td>
                                
                                <!-- Cotisation 2026 -->
                                <td>
                                    <input type="number" 
                                           step="0.01" 
                                           class="edit-input" 
                                           name="benevoles[<?php echo $b['id_benevole']; ?>][p_2026]" 
                                           value="<?php echo htmlspecialchars($b['p_2026']); ?>" 
                                           placeholder="Montant"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Moyen -->
                                <td>
                                    <select class="edit-select" 
                                            name="benevoles[<?php echo $b['id_benevole']; ?>][moyen]"
                                            onchange="markAsChanged(this)">
                                        <option value="">-- Choisir --</option>
                                        <option value="Espèces" <?php echo $b['moyen'] === 'Espèces' ? 'selected' : ''; ?>>Espèces</option>
                                        <option value="Chèque" <?php echo $b['moyen'] === 'Chèque' ? 'selected' : ''; ?>>Chèque</option>
                                        <option value="Virement" <?php echo $b['moyen'] === 'Virement' ? 'selected' : ''; ?>>Virement</option>
                                        <option value="Carte bancaire" <?php echo $b['moyen'] === 'Carte bancaire' ? 'selected' : ''; ?>>Carte bancaire</option>
                                    </select>
                                </td>
                                
                                <!-- Date cotisation -->
                                <td>
                                    <input type="date" 
                                           class="edit-input" 
                                           name="benevoles[<?php echo $b['id_benevole']; ?>][date_1]" 
                                           value="<?php echo htmlspecialchars($b['date_1']); ?>"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Observations cotisation -->
                                <td>
                                    <textarea class="edit-textarea" 
                                              name="benevoles[<?php echo $b['id_benevole']; ?>][observations_1]"
                                              onchange="markAsChanged(this)"
                                              placeholder="Observations..."><?php echo htmlspecialchars($b['observations_1']); ?></textarea>
                                </td>
                                
                                <!-- Don -->
                                <td>
                                    <input type="number" 
                                           step="0.01" 
                                           class="edit-input" 
                                           name="benevoles[<?php echo $b['id_benevole']; ?>][dons]" 
                                           value="<?php echo htmlspecialchars($b['dons']); ?>" 
                                           placeholder="Montant"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Date don -->
                                <td>
                                    <input type="date" 
                                           class="edit-input" 
                                           name="benevoles[<?php echo $b['id_benevole']; ?>][date_2]" 
                                           value="<?php echo htmlspecialchars($b['date_2']); ?>"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Observations don -->
                                <td>
                                    <textarea class="edit-textarea" 
                                              name="benevoles[<?php echo $b['id_benevole']; ?>][observations_2]"
                                              onchange="markAsChanged(this)"
                                              placeholder="Observations..."><?php echo htmlspecialchars($b['observations_2']); ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <script>
        // Marquer les champs modifiés visuellement
        function markAsChanged(element) {
            element.classList.add('changed');
            const row = element.closest('tr');
            row.classList.add('modified');
        }

        // Confirmation avant de quitter la page si des modifications sont en cours
        let formModified = false;
        document.querySelectorAll('.edit-input, .edit-select, .edit-textarea').forEach(element => {
            element.addEventListener('change', () => {
                formModified = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formModified) {
                e.preventDefault();
                e.returnValue = 'Des modifications non enregistrées existent. Voulez-vous vraiment quitter ?';
            }
        });

        // Désactiver la confirmation après soumission
        document.getElementById('mainForm').addEventListener('submit', () => {
            formModified = false;
            document.querySelector('.btn-save-all').classList.add('loading');
            document.querySelector('.btn-save-all').textContent = 'Enregistrement en cours...';
        });

        // Fermer le message de succès automatiquement après 5 secondes
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }

        // Raccourci clavier Ctrl+S pour sauvegarder
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('mainForm').submit();
            }
        });
    </script>
</body>
</html>