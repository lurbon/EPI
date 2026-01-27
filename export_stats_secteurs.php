<?php
// Charger la configuration WordPress
require_once('wp-config.php');
require_once('auth.php');
verifierRole(['admin', 'gestionnaire']);

// Vérifier que PhpSpreadsheet est installé
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Connexion à la base de données
$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données");
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
    die("Erreur lors de la récupération des données");
}

// Noms des mois en français
$nomsMois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Créer le fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre
$titre = "Statistiques par Secteur - Missions et KM - Année " . $annee;
if (!empty($secteurFiltre)) {
    $titre .= " - Secteur: " . $secteurFiltre;
}
$sheet->setCellValue('A1', $titre);
$sheet->mergeCells('A1:N1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// En-têtes
$row = 3;
$sheet->setCellValue('A' . $row, 'Secteur');
$sheet->setCellValue('B' . $row, 'Type');

$col = 'C';
for ($m = 1; $m <= 12; $m++) {
    $sheet->setCellValue($col . $row, substr($nomsMois[$m], 0, 3));
    $col++;
}
$sheet->setCellValue($col . $row, 'TOTAL');

// Style des en-têtes
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray($headerStyle);

// Données
$row++;
foreach ($statistiques as $secteur => $data) {
    // Ligne missions
    $sheet->setCellValue('A' . $row, $secteur);
    $sheet->setCellValue('B' . $row, 'Missions');
    
    $col = 'C';
    for ($m = 1; $m <= 12; $m++) {
        $sheet->setCellValue($col . $row, $data['mois'][$m]['missions']);
        $col++;
    }
    $sheet->setCellValue($col . $row, $data['total_missions']);
    
    // Fusionner la colonne secteur pour les 2 lignes
    $sheet->mergeCells('A' . $row . ':A' . ($row + 1));
    $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    
    $row++;
    
    // Ligne KM
    $sheet->setCellValue('B' . $row, 'Kilomètres');
    
    $col = 'C';
    for ($m = 1; $m <= 12; $m++) {
        $sheet->setCellValue($col . $row, $data['mois'][$m]['km']);
        $col++;
    }
    $sheet->setCellValue($col . $row, $data['total_km']);
    
    $row++;
}

// Ligne de total
$sheet->setCellValue('A' . $row, 'TOTAL ' . $annee);
$sheet->setCellValue('B' . $row, 'Missions');

$col = 'C';
for ($m = 1; $m <= 12; $m++) {
    $missionsMois = 0;
    foreach ($statistiques as $data) {
        $missionsMois += $data['mois'][$m]['missions'];
    }
    $sheet->setCellValue($col . $row, $missionsMois);
    $col++;
}

$totalGlobalMissions = 0;
foreach ($statistiques as $data) {
    $totalGlobalMissions += $data['total_missions'];
}
$sheet->setCellValue($col . $row, $totalGlobalMissions);

// Fusionner pour le total
$sheet->mergeCells('A' . $row . ':A' . ($row + 1));
$sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$row++;

// Ligne KM total
$sheet->setCellValue('B' . $row, 'Kilomètres');

$col = 'C';
for ($m = 1; $m <= 12; $m++) {
    $kmMois = 0;
    foreach ($statistiques as $data) {
        $kmMois += $data['mois'][$m]['km'];
    }
    $sheet->setCellValue($col . $row, $kmMois);
    $col++;
}

$totalGlobalKm = 0;
foreach ($statistiques as $data) {
    $totalGlobalKm += $data['total_km'];
}
$sheet->setCellValue($col . $row, $totalGlobalKm);

// Style de la ligne totale
$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EBFF']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A' . ($row - 1) . ':N' . $row)->applyFromArray($totalStyle);

// Bordures pour toutes les cellules de données
$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];
$sheet->getStyle('A4:N' . $row)->applyFromArray($dataStyle);

// Alignement de la colonne Secteur
$sheet->getStyle('A4:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(15);
for ($col = 'C'; $col <= 'N'; $col++) {
    $sheet->getColumnDimension($col)->setWidth(12);
}

// Générer le fichier
$filename = 'stats_secteurs_' . $annee;
if (!empty($secteurFiltre)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $secteurFiltre);
}
$filename .= '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
