<?php
// Charger la configuration
require_once('config.php');
require_once('auth.php');
verifierRole(['admin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - EPI</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
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


        .back-link:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.7);
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .admin-menu {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .admin-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .admin-link:hover {
            transform: translateX(10px);
            border-color: #667eea;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .admin-link .icon {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
        }

        .admin-link .text {
            flex: 1;
        }

        .admin-link .title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .admin-link .description {
            font-size: 0.85rem;
            color: #666;
        }

        .admin-link .arrow {
            font-size: 1.5rem;
            color: #667eea;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .admin-link:hover .arrow {
            opacity: 1;
            transform: translateX(5px);
        }

        @media (max-width: 600px) {
            .container {
                padding: 25px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .admin-link {
                padding: 15px;
            }

            .admin-link .icon {
                width: 40px;
                height: 40px;
                font-size: 1.5rem;
            }

            .back-link {
                top: 15px;
                left: 15px;
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>
 <button onclick="window.location.href='dashboard.php'" class="back-link" title="Retour au tableau de bord">🏠</button>
    <div class="container">
        <h1>Administration</h1>
        <p class="subtitle">Outils réservés aux administrateurs</p>

        <div class="admin-menu">
            <a href="stats_benevoles.php" class="admin-link">
                <div class="icon">📊</div>
                <div class="text">
                    <div class="title">Statistiques Bénévoles</div>
                    <div class="description">KM et durées par bénévole et par mois</div>
                </div>
                <div class="arrow">→</div>
            </a>

            <a href="stats_secteurs.php" class="admin-link">
                <div class="icon">🗺️</div>
                <div class="text">
                    <div class="title">Statistiques Secteurs</div>
                    <div class="description">Analyse par secteur géographique</div>
                </div>
                <div class="arrow">→</div>
            </a>


            <a href="logs_connexions.php" class="admin-link">
                <div class="icon">📋</div>
                <div class="text">
                    <div class="title">Logs de Connexions</div>
                    <div class="description">Historique des connexions utilisateurs</div>
                </div>
                <div class="arrow">→</div>
            </a>

            <a href="historique_envois.php" class="admin-link">
                <div class="icon">📧</div>
                <div class="text">
                    <div class="title">Historique Envois Missions</div>
                    <div class="description">Suivi des emails envoyés aux bénévoles</div>
                </div>
                <div class="arrow">→</div>
            </a>
        </div>
    </div>
</body>
</html>