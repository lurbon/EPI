<?php
/**
 * Système de tracking d'activité basé sur les modifications en base de données
 * 
 * Principe :
 * - Chaque fois qu'une modification en base est effectuée (INSERT/UPDATE/DELETE)
 * - On met à jour le champ last_activity_db dans connexions_log
 * - Cela permet de savoir précisément quand l'utilisateur a été actif
 */

/**
 * Enregistre une activité utilisateur dans connexions_log
 * À appeler après chaque modification en base de données
 * 
 * @param PDO|null $pdo Connexion PDO (optionnelle)
 * @return bool Succès ou échec
 */
function enregistrerActiviteDB($pdo = null) {
    // Démarrer la session si nécessaire
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Vérifier que la session existe
    if (!isset($_SESSION['connexion_log_id'])) {
        return false;
    }
    
    $closeConnection = false;
    
    // Créer une connexion si nécessaire
    if ($pdo === null) {
        try {
            require_once(__DIR__ . '/config.php');
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $closeConnection = true;
        } catch (PDOException $e) {
            error_log("Erreur connexion PDO pour activité: " . $e->getMessage());
            return false;
        }
    }
    
    try {
        // Mettre à jour last_activity_db dans connexions_log
        $stmt = $pdo->prepare("
            UPDATE connexions_log 
            SET last_activity_db = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['connexion_log_id']]);
        
        // Mettre à jour aussi la session PHP
        $_SESSION['last_activity'] = time();
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur enregistrement activité DB: " . $e->getMessage());
        return false;
    } finally {
        if ($closeConnection && $pdo !== null) {
            $pdo = null;
        }
    }
}
