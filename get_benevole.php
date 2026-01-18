<?php
require_once('wp-config.php');
require_once('auth.php');
verifierRole('admin');

header('Content-Type: application/json');

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
                'nom' => $benevole['nom'],
                'adresse' => $benevole['adresse'],
                'code_postal' => $benevole['code_postal'],
                'commune' => $benevole['commune'],
                'secteur' => $benevole['secteur']
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