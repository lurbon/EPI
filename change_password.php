<?php
require_once('config.php');
require_once('phpass_compat.php');

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Vérifications
    if (empty($username) || empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Tous les champs sont obligatoires";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas";
    } elseif (strlen($new_password) < 6) {
        $error = "Le nouveau mot de passe doit contenir au moins 6 caractères";
    } else {
        try {
            $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier l'utilisateur et l'ancien mot de passe
            $stmt = $conn->prepare("SELECT ID, user_pass FROM mod321_users WHERE user_login = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Nom d'utilisateur incorrect";
            } else {
                // Vérifier l'ancien mot de passe (supporte bcrypt et phpass)
                $passwordValid = false;
                $storedHash = $user['user_pass'];

                if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
                    $passwordValid = password_verify($old_password, $storedHash);
                } elseif (strpos($storedHash, '$P$') === 0 || strpos($storedHash, '$H$') === 0) {
                    $passwordValid = epi_phpass_check($old_password, $storedHash);
                }

                if (!$passwordValid) {
                    $error = "Ancien mot de passe incorrect";
                } else {
                    // Mettre à jour le mot de passe avec bcrypt natif
                    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("UPDATE mod321_users SET user_pass = :password WHERE ID = :id");
                    $stmt->execute([
                        ':password' => $new_password_hash,
                        ':id' => $user['ID']
                    ]);

                    $message = "Mot de passe modifié avec succès ! Vous pouvez maintenant vous connecter.";
                }
            }

        } catch(PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
            padding: 10px;
        }

        .logo-container img {
            max-width: 150px;
            height: auto;
        }

        h2 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        .toggle-password:hover {
            color: #764ba2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            border-left: 4px solid #c62828;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            border-left: 4px solid #2e7d32;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding-left: 5px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 24px;
            }

            .logo-container img {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="img/Logo-Entraide-Plus-Iroise.jpg" alt="Logo Entraide Plus Iroise">
        </div>
        <h2>🔐 Changer le mot de passe</h2>

        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input 
                    type="text"
                    id="username" 
                    name="username" 
                    placeholder="Entrez votre nom d'utilisateur" 
                    required
                    autofocus
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                >
            </div>

            <div class="form-group">
                <label for="old_password">Ancien mot de passe</label>
                <div class="password-wrapper">
                    <input 
                        type="password"
                        id="old_password"
                        name="old_password" 
                        placeholder="Entrez votre ancien mot de passe" 
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword('old_password')" title="Afficher/Masquer">
                        👁️
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <div class="password-wrapper">
                    <input 
                        type="password"
                        id="new_password"
                        name="new_password" 
                        placeholder="Entrez votre nouveau mot de passe" 
                        required
                        minlength="6"
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')" title="Afficher/Masquer">
                        👁️
                    </button>
                </div>
                <div class="password-requirements">
                    Minimum 6 caractères
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                <div class="password-wrapper">
                    <input 
                        type="password"
                        id="confirm_password"
                        name="confirm_password" 
                        placeholder="Confirmez votre nouveau mot de passe" 
                        required
                        minlength="6"
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" title="Afficher/Masquer">
                        👁️
                    </button>
                </div>
            </div>

            <button type="submit">Changer le mot de passe</button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.html">← Retour à la connexion</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }

        // Vérifier que les mots de passe correspondent
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Les nouveaux mots de passe ne correspondent pas !');
                }
            });
        }
    </script>
</body>
</html>
