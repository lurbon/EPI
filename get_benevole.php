<?php
require_once('config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

header('Content-Type: application/json');

// Fonction pour nettoyer les backslashes multiples
function cleanData($value) {
    if (is_null($value) || $value === '') {
        return $value;
    }
    // Retirer tous les backslashes d'échappement accumulés
    while (strpos($value, '\\\\') !== false) {
        $value = str_replace('\\\\', '\\', $value);
    }
    // Puis retirer les backslashes simples d'échappement
    return stripslashes($value);
}

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT nom, adresse, code_postal, commune, secteur FROM EPI_benevole WHERE id_benevole = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $benevole = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($benevole) {
            echo json_encode([
                'success' => true,
                'nom' => cleanData($benevole['nom']),
                'adresse' => cleanData($benevole['adresse']),
                'code_postal' => cleanData($benevole['code_postal']),
                'commune' => cleanData($benevole['commune']),
                'secteur' => cleanData($benevole['secteur'])
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Bénévole introuvable']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID manquant']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>