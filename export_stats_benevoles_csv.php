<?php
/**
 * Export CSV des statistiques bénévoles
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
$benevoleFiltre = isset($_GET['benevole']) ? $_GET['benevole'] : '';

// Récupérer les statistiques par bénévole et par mois
$statistiques = [];
try {
    $sql = "SELECT
                benevole,
                MONTH(date_mission) as mois,
                YEAR(date_mission) as annee,
                SUM(COALESCE(km_saisi, 0)) as total_km,
                SEC_TO_TIME(SUM(TIME_TO_SEC(COALESCE(duree, '00:00:00')))) as total_duree,
                COUNT(*) as nb_missions
            FROM EPI_mission
            WHERE benevole IS NOT NULL
            AND TRIM(benevole) != ''
            AND YEAR(date_mission) = :annee";

    $params = [':annee' => $annee];

    if (!empty($benevoleFiltre)) {
        $sql .= " AND benevole = :benevole";
        $params[':benevole'] = $benevoleFiltre;
    }

    $sql .= " GROUP BY benevole, YEAR(date_mission), MONTH(date_mission)
              ORDER BY benevole, mois";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données par bénévole
    foreach ($resultats as $row) {
        $nom = $row['benevole'];
        if (!isset($statistiques[$nom])) {
            $statistiques[$nom] = [
                'mois' => array_fill(1, 12, ['km' => 0, 'duree' => '00:00:00', 'missions' => 0]),
                'total_km' => 0,
                'total_duree_sec' => 0,
                'total_missions' => 0
            ];
        }
        $statistiques[$nom]['mois'][$row['mois']] = [
            'km' => floatval($row['total_km']),
            'duree' => $row['total_duree'],
            'missions' => intval($row['nb_missions'])
        ];
        $statistiques[$nom]['total_km'] += floatval($row['total_km']);

        // Convertir la durée en secondes pour le total
        $parts = explode(':', $row['total_duree']);
        $seconds = (intval($parts[0]) * 3600) + (intval($parts[1]) * 60) + (isset($parts[2]) ? intval($parts[2]) : 0);
        $statistiques[$nom]['total_duree_sec'] += $seconds;
        $statistiques[$nom]['total_missions'] += intval($row['nb_missions']);
    }

    // Convertir le total des secondes en format HH:MM
    foreach ($statistiques as &$stat) {
        $heures = floor($stat['total_duree_sec'] / 3600);
        $minutes = floor(($stat['total_duree_sec'] % 3600) / 60);
        $stat['total_duree'] = sprintf('%02d:%02d', $heures, $minutes);
    }
    unset($stat);

} catch(PDOException $e) {
    ob_end_clean();
    die("Erreur lors de la récupération des données: " . $e->getMessage());
}

// Vérifier qu'il y a des données
if (empty($statistiques)) {
    ob_end_clean();
    die("Aucune donnée trouvée pour l'année " . $annee . ($benevoleFiltre ? " et le bénévole " . $benevoleFiltre : ""));
}

// Noms des mois en français
$nomsMois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Fonction pour formater la durée
function formaterDuree($duree) {
    if (empty($duree) || $duree === '00:00:00') return '-';
    $parts = explode(':', $duree);
    $heures = intval($parts[0]);
    $minutes = intval($parts[1]);
    if ($heures === 0 && $minutes === 0) return '-';
    if ($heures === 0) return $minutes . 'min';
    if ($minutes === 0) return $heures . 'h';
    return $heures . 'h' . sprintf('%02d', $minutes);
}

// CRITIQUE : Nettoyer TOUT le buffer de sortie avant d'envoyer le fichier
while (ob_get_level()) {
    ob_end_clean();
}

// Générer le nom du fichier
$filename = 'stats_benevoles_' . $annee;
if (!empty($benevoleFiltre)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $benevoleFiltre);
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
$titre = "Statistiques Bénévoles - KM et Durées - Année " . $annee;
if (!empty($benevoleFiltre)) {
    $titre .= " - Bénévole: " . $benevoleFiltre;
}
fputcsv($output, [$titre], $delimiter);
fputcsv($output, [], $delimiter); // Ligne vide

// En-têtes
$headers = ['Bénévole'];
for ($m = 1; $m <= 12; $m++) {
    $headers[] = substr($nomsMois[$m], 0, 3) . ' (KM)';
    $headers[] = substr($nomsMois[$m], 0, 3) . ' (Durée)';
}
$headers[] = 'TOTAL KM';
$headers[] = 'TOTAL Durée';
$headers[] = 'TOTAL Missions';
fputcsv($output, $headers, $delimiter);

// Données par bénévole
foreach ($statistiques as $nom => $data) {
    $row = [$nom];
    
    for ($m = 1; $m <= 12; $m++) {
        // KM
        $row[] = $data['mois'][$m]['km'] > 0 ? number_format($data['mois'][$m]['km'], 0, ',', '') : '-';
        // Durée
        $row[] = formaterDuree($data['mois'][$m]['duree']);
    }
    
    // Totaux
    $row[] = number_format($data['total_km'], 0, ',', '');
    $row[] = $data['total_duree'];
    $row[] = $data['total_missions'];
    
    fputcsv($output, $row, $delimiter);
}

// Ligne vide avant les totaux globaux
fputcsv($output, [], $delimiter);

// Ligne de total global
$totalRow = ['TOTAL ' . $annee];

// Calculer les totaux par mois
for ($m = 1; $m <= 12; $m++) {
    $kmMois = 0;
    $dureeMoisSec = 0;
    
    foreach ($statistiques as $data) {
        $kmMois += $data['mois'][$m]['km'];
        $parts = explode(':', $data['mois'][$m]['duree']);
        $dureeMoisSec += (intval($parts[0]) * 3600) + (intval($parts[1]) * 60);
    }
    
    $hMois = floor($dureeMoisSec / 3600);
    $minMois = floor(($dureeMoisSec % 3600) / 60);
    
    $totalRow[] = $kmMois > 0 ? number_format($kmMois, 0, ',', '') : '-';
    $totalRow[] = $hMois > 0 ? $hMois . 'h' . sprintf('%02d', $minMois) : '-';
}

// Totaux globaux
$totalGlobalKm = 0;
$totalGlobalDureeSec = 0;
$totalGlobalMissions = 0;

foreach ($statistiques as $stat) {
    $totalGlobalKm += $stat['total_km'];
    $totalGlobalDureeSec += $stat['total_duree_sec'];
    $totalGlobalMissions += $stat['total_missions'];
}

$totalGlobalHeures = floor($totalGlobalDureeSec / 3600);
$totalGlobalMinutes = floor(($totalGlobalDureeSec % 3600) / 60);

$totalRow[] = number_format($totalGlobalKm, 0, ',', '');
$totalRow[] = $totalGlobalHeures . 'h' . sprintf('%02d', $totalGlobalMinutes);
$totalRow[] = $totalGlobalMissions;

fputcsv($output, $totalRow, $delimiter);

fclose($output);
exit;
