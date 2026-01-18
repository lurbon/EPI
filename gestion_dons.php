<?php
// Charger la configuration WordPress
require_once('wp-config.php');

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

// Vérifier qu'un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: liste_benevoles.php");
    exit();
}

$benevole_id = intval($_GET['id']);

// Traitement du formulaire AVANT de récupérer les données
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $sql = "UPDATE EPI_benevole SET 
                date_1 = :date_1,
                observations_1 = :observations_1,
                dons = :dons,
                date_2 = :date_2,
                observations_2 = :observations_2
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        
        // Gérer les valeurs vides
        $date_1 = !empty($_POST['date_1']) ? $_POST['date_1'] : null;
        $date_2 = !empty($_POST['date_2']) ? $_POST['date_2'] : null;
        
        $stmt->bindParam(':date_1', $date_1);
        $stmt->bindParam(':observations_1', $_POST['observations_1']);
        $stmt->bindParam(':dons', $_POST['dons']);
        $stmt->bindParam(':date_2', $date_2);
        $stmt->bindParam(':observations_2', $_POST['observations_2']);
        $stmt->bindParam(':id', $benevole_id, PDO::PARAM_INT);
        
        $stmt->execute();
        
        $message = "✅ Dons et paiements mis à jour avec succès !";
        $messageType = "success";
        
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer les informations du bénévole APRÈS le traitement
$benevole = null;
try {
    $stmt = $conn->prepare("SELECT * FROM EPI_benevole WHERE id = :id");
    $stmt->bindParam(':id', $benevole_id, PDO::PARAM_INT);
    $stmt->execute();
    $benevole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$benevole) {
        header("Location: liste_benevoles.php");
        exit();
    }
} catch(PDOException $e) {
    die("Erreur lors de la récupération du bénévole : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Dons et Paiements - <?php echo htmlspecialchars($benevole['nom']); ?></title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        h1 {
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
            font-size: 24px;
        }

        .benevole-name {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 18px;
            font-weight: 600;
        }

        h3 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            font-size: 14px;
        }

        .info-item strong {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .info-item span {
            color: #333;
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

        input[type="text"],
        input[type="date"],
        input[type="number"],
        textarea {
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

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .row {
            display: flex;
            gap: 15px;
        }

        .row .form-group {
            flex: 1;
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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 8px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }

        .section-description {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1976d2;
            border-left: 4px solid #1976d2;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .back-link {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .row {
                flex-direction: column;
                gap: 0;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="liste_benevoles.php" class="back-link">← Retour à la liste</a>

    <div class="container">
        <h1>💰 Gestion des Dons et Paiements</h1>
        <div class="benevole-name">
            👤 <?php echo htmlspecialchars($benevole['nom']); ?>
        </div>
        
        <?php if($message): ?>
            <div class="message <?php echo $messageType; ?>" id="messageBox">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="section-description">
            ℹ️ Cette section permet uniquement de modifier les informations de dons et paiements. Les autres informations du bénévole sont affichées en lecture seule.
        </div>

        <!-- Informations en lecture seule -->
        <h3>📋 Informations du bénévole (lecture seule)</h3>
        <div class="info-section">
            <div class="info-grid">
                <div class="info-item">
                    <strong>Nom complet</strong>
                    <span><?php echo htmlspecialchars($benevole['nom']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Date de naissance</strong>
                    <span><?php echo $benevole['date_naissance'] ? htmlspecialchars($benevole['date_naissance']) : 'Non renseignée'; ?></span>
                </div>
                <div class="info-item">
                    <strong>Adresse</strong>
                    <span><?php echo htmlspecialchars($benevole['adresse']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Ville</strong>
                    <span><?php echo htmlspecialchars($benevole['commune']) . ' (' . htmlspecialchars($benevole['code_postal']) . ')'; ?></span>
                </div>
                <div class="info-item">
                    <strong>Téléphone mobile</strong>
                    <span><?php echo $benevole['tel_mobile'] ? htmlspecialchars($benevole['tel_mobile']) : 'Non renseigné'; ?></span>
                </div>
                <div class="info-item">
                    <strong>Email</strong>
                    <span><?php echo $benevole['courriel'] ? htmlspecialchars($benevole['courriel']) : 'Non renseigné'; ?></span>
                </div>
                <div class="info-item">
                    <strong>Secteur</strong>
                    <span><?php echo $benevole['secteur'] ? htmlspecialchars($benevole['secteur']) : 'Non renseigné'; ?></span>
                </div>
                <div class="info-item">
                    <strong>Utilisation mail</strong>
                    <span><?php echo $benevole['flag_mail'] ? htmlspecialchars($benevole['flag_mail']) : 'Non renseigné'; ?></span>
                </div>
            </div>
        </div>

        <!-- Formulaire éditable pour dons et paiements -->
        <form method="POST" action="" id="donsForm">
            <h3>💳 Informations de dons et paiements (modifiable)</h3>
            
            <div class="row">
                <div class="form-group">
                    <label for="date_1">Date 1</label>
                    <input type="date" id="date_1" name="date_1" value="<?php echo htmlspecialchars($benevole['date_1'] ?? ''); ?>">
                    <div class="field-hint">Date du premier paiement ou don</div>
                </div>
                <div class="form-group">
                    <label for="dons">Dons</label>
                    <input type="text" id="dons" name="dons" value="<?php echo htmlspecialchars($benevole['dons'] ?? ''); ?>" placeholder="Montant ou description">
                    <div class="field-hint">Montant des dons reçus</div>
                </div>
            </div>

            <div class="form-group">
                <label for="observations_1">Observations 1</label>
                <textarea id="observations_1" name="observations_1" placeholder="Observations concernant le premier paiement..."><?php echo htmlspecialchars($benevole['observations_1'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="date_2">Date 2</label>
                <input type="date" id="date_2" name="date_2" value="<?php echo htmlspecialchars($benevole['date_2'] ?? ''); ?>">
                <div class="field-hint">Date du deuxième paiement ou don</div>
            </div>

            <div class="form-group">
                <label for="observations_2">Observations 2</label>
                <textarea id="observations_2" name="observations_2" placeholder="Observations concernant le deuxième paiement..."><?php echo htmlspecialchars($benevole['observations_2'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">💾 Enregistrer les modifications</button>
        </form>

        <!-- Info de dernière modification -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0; text-align: center; color: #666; font-size: 13px;">
            <p>📝 Page chargée le : <?php echo date('d/m/Y à H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Confirmation avant de quitter si le formulaire a été modifié
        let formModified = false;
        const form = document.getElementById('donsForm');
        const inputs = form.querySelectorAll('input, textarea');
        
        // Sauvegarder les valeurs initiales
        const initialValues = {};
        inputs.forEach(input => {
            initialValues[input.name] = input.value;
        });
        
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                formModified = input.value !== initialValues[input.name];
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formModified) {
                e.preventDefault();
                e.returnValue = 'Vous avez des modifications non enregistrées. Voulez-vous vraiment quitter ?';
            }
        });

        form.addEventListener('submit', () => {
            formModified = false;
        });

        // Faire disparaître le message après 5 secondes
        const messageBox = document.getElementById('messageBox');
        if (messageBox) {
            setTimeout(() => {
                messageBox.style.transition = 'opacity 0.5s ease';
                messageBox.style.opacity = '0';
                setTimeout(() => {
                    messageBox.remove();
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>