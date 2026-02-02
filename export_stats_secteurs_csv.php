<?php
/**
 * Export CSV des statistiques par secteur
 * Version compatible WordPress + Thème Astra
 * SANS BESOIN de Composer ou PhpSpreadsheet
 */

// IMPORTANT : Démarrer l'output buffering AVANT tout autre code
ob_start();

// Trouver wp-config.php (dans le même dossier www)
if (file_exists('wp-config.php')) {
    require_once('wp-config.php');
} elseif (file_exists('../wp-config.php')) {
    require_once('../wp-config.php');
} elseif (file_exists('../../wp-config.php')) {
    require_once('../../wp-config.php');
} else {
    die("Erreur : wp-config.php introuvable");
}

require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

// Connexion à la base de données
$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    ob_end_clean();
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Paramètres de filtre
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
$secteurFiltre = isset($_GET['secteur']) ? $_GET['secteur'] : '';

// Récupérer les statistiques par secteur et par mois
$statistiques = [];
try {
    $sql = "SELECT
                secteur_aide,
                MONTH(date_mission) as mois,
                YEAR(date_mission) as annee,
                SUM(COALESCE(km_saisi, 0)) as total_km,
                COUNT(*) as nb_missions
            FROM EPI_mission
            WHERE secteur_aide IS NOT NULL
            AND TRIM(secteur_aide) != ''
            AND YEAR(date_mission) = :annee";

    $params = [':annee' => $annee];

    if (!empty($secteurFiltre)) {
        $sql .= " AND secteur_aide = :secteur";
        $params[':secteur'] = $secteurFiltre;
    }

    $sql .= " GROUP BY secteur_aide, YEAR(date_mission), MONTH(date_mission)
              ORDER BY secteur_aide, mois";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données par secteur
    foreach ($resultats as $row) {
        $nom = $row['secteur_aide'];
        if (!isset($statistiques[$nom])) {
            $statistiques[$nom] = [
                'mois' => array_fill(1, 12, ['km' => 0, 'missions' => 0]),
                'total_km' => 0,
                'total_missions' => 0
            ];
        }
        $statistiques[$nom]['mois'][$row['mois']] = [
            'km' => floatval($row['total_km']),
            'missions' => intval($row['nb_missions'])
        ];
        $statistiques[$nom]['total_km'] += floatval($row['total_km']);
        $statistiques[$nom]['total_missions'] += intval($row['nb_missions']);
    }

} catch(PDOException $e) {
    ob_end_clean();
    die("Erreur lors de la récupération des données: " . $e->getMessage());
}

// Vérifier qu'il y a des données
if (empty($statistiques)) {
    ob_end_clean();
    die("Aucune donnée trouvée pour l'année " . $annee . ($secteurFiltre ? " et le secteur " . $secteurFiltre : ""));
}

// Noms des mois en français
$nomsMois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// CRITIQUE : Nettoyer TOUT le buffer de sortie avant d'envoyer le fichier
while (ob_get_level()) {
    ob_end_clean();
}

// Générer le nom du fichier
$filename = 'stats_secteurs_' . $annee;
if (!empty($secteurFiltre)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $secteurFiltre);
}
$filename .= '.csv';

// Headers pour forcer le téléchargement
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Ajouter le BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

// Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// Définir le délimiteur (point-virgule pour Excel français)
$delimiter = ';';

// Ligne de titre
$titre = "Statistiques par Secteur - Missions et KM - Année " . $annee;
if (!empty($secteurFiltre)) {
    $titre .= " - Secteur: " . $secteurFiltre;
}
fputcsv($output, [$titre], $delimiter);
fputcsv($output, [], $delimiter); // Ligne vide

// En-têtes
$headers = ['Secteur', 'Type'];
for ($m = 1; $m <= 12; $m++) {
    $headers[] = substr($nomsMois[$m], 0, 3);
}
$headers[] = 'TOTAL';
fputcsv($output, $headers, $delimiter);

// Données par secteur
foreach ($statistiques as $secteur => $data) {
    // Ligne missions
    $rowMissions = [$secteur, 'Missions'];
    for ($m = 1; $m <= 12; $m++) {
        $rowMissions[] = $data['mois'][$m]['missions'];
    }
    $rowMissions[] = $data['total_missions'];
    fputcsv($output, $rowMissions, $delimiter);
    
    // Ligne KM
    $rowKm = ['', 'Kilomètres'];
    for ($m = 1; $m <= 12; $m++) {
        $rowKm[] = number_format($data['mois'][$m]['km'], 2, ',', '');
    }
    $rowKm[] = number_format($data['total_km'], 2, ',', '');
    fputcsv($output, $rowKm, $delimiter);
}

// Ligne vide avant les totaux
fputcsv($output, [], $delimiter);

// Ligne de total missions
$totalRowMissions = ['TOTAL ' . $annee, 'Missions'];
for ($m = 1; $m <= 12; $m++) {
    $missionsMois = 0;
    foreach ($statistiques as $data) {
        $missionsMois += $data['mois'][$m]['missions'];
    }
    $totalRowMissions[] = $missionsMois;
}
$totalGlobalMissions = 0;
foreach ($statistiques as $data) {
    $totalGlobalMissions += $data['total_missions'];
}
$totalRowMissions[] = $totalGlobalMissions;
fputcsv($output, $totalRowMissions, $delimiter);

// Ligne de total KM
$totalRowKm = ['', 'Kilomètres'];
for ($m = 1; $m <= 12; $m++) {
    $kmMois = 0;
    foreach ($statistiques as $data) {
        $kmMois += $data['mois'][$m]['km'];
    }
    $totalRowKm[] = number_format($kmMois, 2, ',', '');
}
$totalGlobalKm = 0;
foreach ($statistiques as $data) {
    $totalGlobalKm += $data['total_km'];
}
$totalRowKm[] = number_format($totalGlobalKm, 2, ',', '');
fputcsv($output, $totalRowKm, $delimiter);

fclose($output);
exit;
