<?php
require_once('config.php');

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';

    if (empty($username) || empty($email)) {
        $error = "Tous les champs sont obligatoires";
    } else {
        try {
            $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si l'utilisateur existe avec cet email
            $stmt = $conn->prepare("SELECT ID, user_login, user_email, display_name FROM mod321_users WHERE user_login = :username AND user_email = :email");
            $stmt->execute([':username' => $username, ':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Nom d'utilisateur ou email incorrect";
            } else {
                // Générer un mot de passe temporaire
                $temp_password = 'Temp' . rand(1000, 9999);

                // Hasher avec bcrypt natif
                $temp_password_hash = password_hash($temp_password, PASSWORD_BCRYPT);

                // Mettre à jour avec le mot de passe temporaire
                $stmt = $conn->prepare("UPDATE mod321_users SET user_pass = :password WHERE ID = :id");
                $stmt->execute([
                    ':password' => $temp_password_hash,
                    ':id' => $user['ID']
                ]);

                $message = "Votre mot de passe a été réinitialisé. Votre mot de passe temporaire est : <strong>" . $temp_password . "</strong><br><br>Veuillez le noter et le changer dès votre première connexion.";
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
    <title>Mot de passe oublié</title>
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
            margin-bottom: 20px;
            text-align: center;
            font-size: 28px;
        }

        .info-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
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
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            border-left: 4px solid #2e7d32;
            line-height: 1.6;
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

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #856404;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
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
        <h2>🔑 Mot de passe oublié</h2>

        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="success">✅ <?php echo $message; ?></div>
            <div class="warning-box">
                <strong>⚠️ Important :</strong>
                Notez bien ce mot de passe temporaire et changez-le dès votre première connexion via le menu "Changer le mot de passe".
            </div>
        <?php else: ?>
            <div class="info-text">
                Entrez votre nom d'utilisateur et votre adresse email pour réinitialiser votre mot de passe. Un mot de passe temporaire vous sera fourni.
            </div>

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
                    <label for="email">Adresse email</label>
                    <input 
                        type="email"
                        id="email"
                        name="email" 
                        placeholder="Entrez votre adresse email" 
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <button type="submit">Réinitialiser le mot de passe</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.html">← Retour à la connexion</a>
        </div>
    </div>
</body>
</html>
