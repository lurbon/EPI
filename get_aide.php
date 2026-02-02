<?php
require_once('wp-config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

header('Content-Type: application/json');

$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT nom, adresse, code_postal, commune, secteur, tel_fixe, tel_portable FROM EPI_aide WHERE id_aide = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $aide = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($aide) {
            echo json_encode([
                'success' => true,
                'nom' => $aide['nom'] ?? '',
                'adresse' => $aide['adresse'] ?? '',
                'code_postal' => $aide['code_postal'] ?? '',
                'commune' => $aide['commune'] ?? '',
                'secteur' => $aide['secteur'] ?? '',
                'tel_fixe' => $aide['tel_fixe'] ?? '',
                'tel_portable' => $aide['tel_portable'] ?? ''
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Aidé introuvable']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID manquant']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>