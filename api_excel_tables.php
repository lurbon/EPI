<?php
/**
 * API pour Excel
 *
 * Cette API retourne les donnees des tables EPI en format CSV
 * compatible avec Excel
 *
 * Authentification:
 *   - Par cle API: &api_key=VOTRE_CLE (definie dans config.php : API_EXCEL_KEY)
 *
 * Utilisation:
 *   ?table=benevoles    - Retourne tous les benevoles
 *   ?table=aides        - Retourne tous les aides
 *   ?table=missions     - Retourne toutes les missions
 *
 * Filtres optionnels:
 *   &secteur=NomSecteur - Filtre par secteur
 *   &actifs_only=1      - Uniquement les enregistrements actifs
 *   &annee=2026         - Filtre les missions par annee
 *
 * Date de creation: 2026-02-04
 */

require_once('config.php');

// ============================================================
// CLE API : definie dans config.php (constante API_EXCEL_KEY)
// ============================================================
if (!defined('API_EXCEL_KEY')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Configuration serveur incomplete']);
    exit();
}

// Parametres
$table = isset($_GET['table']) ? $_GET['table'] : '';
$secteur = isset($_GET['secteur']) ? $_GET['secteur'] : '';
$actifs_only = isset($_GET['actifs_only']) && $_GET['actifs_only'] == '1';
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : null;

/**
 * Verifie l'authentification par cle API
 */
function verifierAuthentificationAPI() {
    if (!isset($_GET['api_key']) || empty($_GET['api_key'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Cle API requise. Ajoutez &api_key=VOTRE_CLE a l\'URL'
        ]);
        exit();
    }

    if (!hash_equals(API_EXCEL_KEY, $_GET['api_key'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Cle API invalide'
        ]);
        exit();
    }

    return true;
}

// Verifier l'authentification
verifierAuthentificationAPI();

// Connexion a la base de donnees
$serveur = DB_HOST;
$utilisateur = DB_USER;
$motdepasse = DB_PASSWORD;
$base = DB_NAME;

try {
    $conn = new PDO("mysql:host=$serveur;dbname=$base;charset=utf8mb4", $utilisateur, $motdepasse);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("api_excel_tables: Erreur connexion BDD: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion a la base de donnees'
    ]);
    exit();
}

/**
 * Recupere les benevoles
 */
function getBenevoles($conn, $secteur = '', $actifs_only = false) {
    $sql = "SELECT
                id_benevole,
                nom,
                adresse,
                code_postal,
                commune,
                secteur,
                tel_fixe,
                tel_mobile,
                courriel,
                date_naissance,
                lundi,
                mardi,
                mercredi,
                jeudi,
                vendredi,
                debut,
                fin,
                immatriculation,
                chevaux_fiscaux,
                type,
                p_2026,
                moyen,
                date_1,
                observations_1,
                dons,
                date_2,
                observations_2,
                commentaires,
                flag_mail
            FROM EPI_benevole
            WHERE 1=1";

    $params = [];

    if ($secteur) {
        $sql .= " AND secteur = :secteur";
        $params[':secteur'] = $secteur;
    }

    if ($actifs_only) {
        $sql .= " AND (fin IS NULL OR fin = '' OR fin = '0000-00-00')";
    }

    $sql .= " ORDER BY nom ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recupere les aides
 */
function getAides($conn, $secteur = '', $actifs_only = false) {
    $sql = "SELECT
                id_aide,
                nom,
                adresse,
                code_postal,
                commune,
                secteur,
                tel_fixe,
                tel_portable,
                courriel,
                date_naissance,
                tel_contact,
                lien_parente,
                nom_contact,
                date_debut,
                date_fin,
                p_2026,
                moyen,
                date_paiement,
                observation,
                don,
                date_don,
                don_observation
            FROM EPI_aide
            WHERE 1=1";

    $params = [];

    if ($secteur) {
        $sql .= " AND secteur = :secteur";
        $params[':secteur'] = $secteur;
    }

    if ($actifs_only) {
        $sql .= " AND (date_fin IS NULL OR date_fin = '' OR date_fin = '0000-00-00')";
    }

    $sql .= " ORDER BY nom ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recupere les missions
 */
function getMissions($conn, $secteur = '', $annee = null) {
    $sql = "SELECT
                m.id_mission,
                m.date_mission,
                m.heure_rdv,
                m.benevole,
                m.aide,
                m.secteur_aide,
                m.adresse_aide,
                m.cp_aide,
                m.commune_aide,
                m.adresse_destination,
                m.cp_destination,
                m.commune_destination,
                m.nature_intervention,
                m.commentaires,
                m.km_saisi,
                m.km_calcule,
                m.duree
            FROM EPI_mission m
            WHERE 1=1";

    $params = [];

    if ($secteur) {
        $sql .= " AND m.secteur_aide = :secteur";
        $params[':secteur'] = $secteur;
    }

    if ($annee) {
        $sql .= " AND YEAR(m.date_mission) = :annee";
        $params[':annee'] = $annee;
    }

    $sql .= " ORDER BY m.date_mission DESC, m.heure_rdv ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Nettoie une valeur CSV pour empecher l'injection de formules Excel
 * Les cellules commencant par =, +, -, @, \t ou \r peuvent executer des commandes
 */
function sanitizeCSVValue($value) {
    if (is_string($value) && strlen($value) > 0) {
        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"])) {
            $value = "'" . $value;
        }
    }
    return $value;
}

/**
 * Exporte les donnees en CSV
 */
function exportCSV($data, $filename) {
    // Headers pour telecharger un fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    // BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Ecrire les en-tetes (noms des colonnes)
        fputcsv($output, array_keys($data[0]), ';');

        // Ecrire les donnees en nettoyant chaque cellule
        foreach ($data as $row) {
            $cleanRow = array_map('sanitizeCSVValue', $row);
            fputcsv($output, $cleanRow, ';');
        }
    }

    fclose($output);
    exit();
}

// Traitement de la requete
try {
    $data = [];
    $filename = 'export';

    switch ($table) {
        case 'benevoles':
            $data = getBenevoles($conn, $secteur, $actifs_only);
            $filename = 'benevoles_' . date('Y-m-d');
            break;

        case 'aides':
            $data = getAides($conn, $secteur, $actifs_only);
            $filename = 'aides_' . date('Y-m-d');
            break;

        case 'missions':
            $data = getMissions($conn, $secteur, $annee);
            $filename = 'missions_' . ($annee ?: date('Y')) . '_' . date('Y-m-d');
            break;

        default:
            // Documentation (sans exposer de cle API)
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'API Excel - Documentation',
                'exemples' => [
                    'Benevoles' => '?table=benevoles&api_key=VOTRE_CLE',
                    'Aides' => '?table=aides&api_key=VOTRE_CLE',
                    'Missions' => '?table=missions&api_key=VOTRE_CLE',
                    'Missions 2026' => '?table=missions&annee=2026&api_key=VOTRE_CLE'
                ],
                'filtres' => [
                    'secteur' => '&secteur=NomSecteur',
                    'actifs_only' => '&actifs_only=1',
                    'annee' => '&annee=2026 (missions uniquement)'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
    }

    // Exporter en CSV
    exportCSV($data, $filename);

} catch(PDOException $e) {
    error_log("api_excel_tables: Erreur requete: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur'
    ]);
}