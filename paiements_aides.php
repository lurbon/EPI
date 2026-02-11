<?php
// Charger la configuration
require_once('config.php');
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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['aides'])) {
    try {
        $conn->beginTransaction();
        $updateCount = 0;
        
        foreach ($_POST['aides'] as $id_aide => $data) {
            $sql = "UPDATE EPI_aide SET 
                    p_2026 = :p_2026,
                    moyen = :moyen,
                    date_paiement = :date_paiement,
                    observation = :observation,
                    don = :don,
                    date_don = :date_don,
                    don_observation = :don_observation
                    WHERE id_aide = :id_aide";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':p_2026' => (isset($data['p_2026']) && $data['p_2026'] !== '') ? $data['p_2026'] : null,
                ':moyen' => !empty($data['moyen']) ? $data['moyen'] : null,
                ':date_paiement' => !empty($data['date_paiement']) ? $data['date_paiement'] : null,
                ':observation' => !empty($data['observation']) ? $data['observation'] : null,
                ':don' => (isset($data['don']) && $data['don'] !== '') ? $data['don'] : null,
                ':date_don' => !empty($data['date_don']) ? $data['date_don'] : null,
                ':don_observation' => !empty($data['don_observation']) ? $data['don_observation'] : null,
                ':id_aide' => $id_aide
            ]);
            $updateCount++;
        }
        
        $conn->commit();
        $message = "✅ $updateCount aidé(s) mis à jour avec succès !";
        $messageType = "success";
        
    } catch(PDOException $e) {
        $conn->rollBack();
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer tous les aidés avec leurs infos de paiement
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_paiement = isset($_GET['filter_paiement']) ? $_GET['filter_paiement'] : '';

// Récupérer les moyens de paiement depuis la table EPI_paiement
try {
    $stmt_moyens = $conn->prepare("SELECT DISTINCT type_paiement FROM EPI_paiement WHERE type_paiement IS NOT NULL AND type_paiement != '' ORDER BY type_paiement ASC");
    $stmt_moyens->execute();
    $moyens_paiement = $stmt_moyens->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $moyens_paiement = [];
}

try {
    $sql = "SELECT id_aide, nom, adresse, commune, code_postal, 
            p_2026, moyen, date_paiement, observation, 
            don, date_don, don_observation
            FROM EPI_aide WHERE 1=1";
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
    $aides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - Aidés</title>
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

        .btn-print {
            padding: 12px 25px;
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            margin-left: 10px;
        }

        .btn-print:hover {
            background: linear-gradient(135deg, #138496 0%, #0f6674 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .checkbox-cell {
            text-align: center;
            width: 50px;
        }

        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .select-all-container {
            margin-bottom: 15px;
            padding: 12px;
            background: #f0f8ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid #17a2b8;
        }

        .select-all-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .select-all-container label {
            font-weight: 600;
            cursor: pointer;
            margin: 0;
            color: #17a2b8;
        }

        .selection-count {
            margin-left: auto;
            padding: 5px 15px;
            background: #17a2b8;
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Styles pour l'impression */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #printArea, #printArea * {
                visibility: visible;
            }
            
            #printArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .back-link, .filters, .stats, .save-bar, 
            .select-all-container, .no-print, 
            .btn-print, .btn-save-all, .checkbox-cell,
            .message {
                display: none !important;
            }

            table {
                page-break-inside: auto;
                border: 1px solid #000;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            th {
                background: #667eea !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #000;
            }

            td {
                border: 1px solid #ccc;
                padding: 5px;
            }

            .edit-input, .edit-select, .edit-textarea {
                border: none !important;
                background: transparent !important;
                padding: 2px !important;
            }
        }

        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
        }

        .print-header h2 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .print-date {
            color: #666;
            font-size: 14px;
        }

        @media print {
            .print-header {
                display: block;
            }
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

        .unpaid-name {
            background-color: #ffe6f0;
            border: 2px solid #ff99c2;
            border-radius: 6px;
            padding: 8px;
            display: inline-block;
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
    <a href="dashboard.php" class="back-link" title="Retour au tableau de bord">🏠</a>

    <div class="container">
        <h1>💰 Cotisations aidés</h1>

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
                <a href="paiements_aides.php" class="reset">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo count($aides); ?></div>
                <div class="label">Total aidés</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #28a745;">
                    <?php echo count(array_filter($aides, function($a) { return !is_null($a['p_2026']) && $a['p_2026'] !== ''; })); ?>
                </div>
                <div class="label">Cotisations payées</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #dc3545;">
                    <?php echo count(array_filter($aides, function($a) { return is_null($a['p_2026']) || $a['p_2026'] === ''; })); ?>
                </div>
                <div class="label">Cotisations en attente</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #ffc107;">
                    <?php 
                    $somme_cotisations = array_sum(array_map(function($a) { 
                        return (!is_null($a['p_2026']) && $a['p_2026'] !== '') ? floatval($a['p_2026']) : 0; 
                    }, $aides)); 
                    echo number_format($somme_cotisations, 0, ',', ' ') . ' €';
                    ?>
                </div>
                <div class="label">Somme cotisations 2026</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #17a2b8;">
                    <?php 
                    $somme_dons = array_sum(array_map(function($a) { 
                        return !empty($a['don']) ? floatval($a['don']) : 0; 
                    }, $aides)); 
                    echo number_format($somme_dons, 0, ',', ' ') . ' €';
                    ?>
                </div>
                <div class="label">Somme dons 2026</div>
            </div>
        </div>

        <form method="POST" id="mainForm">
            <div class="save-bar">
                <div class="save-bar-text">
                    💡 Modifiez les champs directement dans le tableau ci-dessous
                </div>
                <div>
                    <button type="button" class="btn-print" onclick="printSelected()">
                        🖨️ Imprimer la sélection
                    </button>
                    <button type="submit" class="btn-save-all">
                        💾 Enregistrer toutes les modifications
                    </button>
                </div>
            </div>

            <!-- Sélection -->
            <div class="select-all-container">
                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                <label for="selectAll">Tout sélectionner / Tout désélectionner</label>
                <span class="selection-count" id="selectionCount">0 sélectionné(s)</span>
            </div>

            <div class="table-wrapper" id="printArea">
                <div class="print-header">
                    <h2>📋 Paiements Aidés - Sélection</h2>
                    <div class="print-date">Imprimé le <?php echo date('d/m/Y à H:i'); ?></div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell no-print">☑️</th>
                            <th style="width: 150px;">Nom</th>
                            <th style="width: 120px;">Ville</th>
                            <th style="width: 100px;">Cotis. 2026</th>
                            <th style="width: 100px;">Moyen</th>
                            <th style="width: 100px;">Date obs.</th>
                            <th style="width: 150px;">Observation</th>
                            <th style="width: 100px;">Don</th>
                            <th style="width: 100px;">Date don</th>
                            <th style="width: 150px;">Obs. don</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($aides as $a): ?>
                            <tr id="row-<?php echo $a['id_aide']; ?>" class="printable-row">
                                <td class="checkbox-cell no-print">
                                    <input type="checkbox" 
                                           class="row-checkbox" 
                                           data-id="<?php echo $a['id_aide']; ?>"
                                           onchange="updateSelectionCount()">
                                </td>
                                <td>
                                    <strong class="<?php echo is_null($a['p_2026']) || $a['p_2026'] === '' ? 'unpaid-name' : ''; ?>">
                                        <?php echo htmlspecialchars($a['nom']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <small style="color: #666;">
                                        <?php echo htmlspecialchars($a['commune'] ?: '-'); ?>
                                        <?php if($a['code_postal']): ?>
                                            (<?php echo htmlspecialchars($a['code_postal']); ?>)
                                        <?php endif; ?>
                                    </small>
                                </td>
                                
                                <!-- Cotisation 2026 -->
                                <td>
                                    <input type="number" 
                                           step="0.01" 
                                           class="edit-input" 
                                           name="aides[<?php echo $a['id_aide']; ?>][p_2026]" 
                                           value="<?php echo htmlspecialchars($a['p_2026']); ?>" 
                                           placeholder="Montant"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Moyen -->
                                <td>
                                    <select class="edit-select" 
                                            name="aides[<?php echo $a['id_aide']; ?>][moyen]"
                                            onchange="markAsChanged(this)">
                                        <option value="">-- Choisir --</option>
                                        <?php foreach($moyens_paiement as $moyen): ?>
                                            <option value="<?php echo htmlspecialchars($moyen); ?>" 
                                                    <?php echo $a['moyen'] === $moyen ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($moyen); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                
                                <!-- Date observation -->
                                <td>
                                    <input type="date" 
                                           class="edit-input" 
                                           name="aides[<?php echo $a['id_aide']; ?>][date_paiement]" 
                                           value="<?php echo htmlspecialchars($a['date_paiement']); ?>"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Observation -->
                                <td>
                                    <textarea class="edit-textarea" 
                                              name="aides[<?php echo $a['id_aide']; ?>][observation]"
                                              onchange="markAsChanged(this)"
                                              placeholder="Observations..."><?php echo htmlspecialchars($a['observation']); ?></textarea>
                                </td>
                                
                                <!-- Don -->
                                <td>
                                    <input type="number" 
                                           step="0.01" 
                                           class="edit-input" 
                                           name="aides[<?php echo $a['id_aide']; ?>][don]" 
                                           value="<?php echo htmlspecialchars($a['don']); ?>" 
                                           placeholder="Montant"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Date don -->
                                <td>
                                    <input type="date" 
                                           class="edit-input" 
                                           name="aides[<?php echo $a['id_aide']; ?>][date_don]" 
                                           value="<?php echo htmlspecialchars($a['date_don']); ?>"
                                           onchange="markAsChanged(this)">
                                </td>
                                
                                <!-- Observation don -->
                                <td>
                                    <textarea class="edit-textarea" 
                                              name="aides[<?php echo $a['id_aide']; ?>][don_observation]"
                                              onchange="markAsChanged(this)"
                                              placeholder="Observations..."><?php echo htmlspecialchars($a['don_observation']); ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <script>
        // Fonction pour cocher/décocher toutes les cases
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
            updateSelectionCount();
        }

        // Fonction pour mettre à jour le compteur de sélection
        function updateSelectionCount() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const count = checkedBoxes.length;
            document.getElementById('selectionCount').textContent = count + ' sélectionné(s)';
            
            // Mettre à jour l'état de la case "Tout sélectionner"
            const allCheckboxes = document.querySelectorAll('.row-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            selectAllCheckbox.checked = (count === allCheckboxes.length && count > 0);
        }

        // Fonction pour imprimer les lignes sélectionnées
        function printSelected() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('⚠️ Veuillez sélectionner au moins un aidé à imprimer.');
                return;
            }

            // Masquer toutes les lignes non cochées
            const allRows = document.querySelectorAll('.printable-row');
            allRows.forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (!checkbox.checked) {
                    row.style.display = 'none';
                }
            });

            // Lancer l'impression
            window.print();

            // Réafficher toutes les lignes après l'impression
            setTimeout(() => {
                allRows.forEach(row => {
                    row.style.display = '';
                });
            }, 100);
        }

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