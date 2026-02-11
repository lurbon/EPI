<?php
/**
 * Proxy pour OpenRouteService - Contourne la CSP
 */
// Authentification requise
require_once(__DIR__ . '/auth.php');

// Charger la configuration
require_once('config.php');

// Vérifier que la clé API existe
if (!defined('OPENROUTE_API_KEY') || empty(OPENROUTE_API_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Clé API non configurée']);
    exit;
}

$OPENROUTE_API_KEY = OPENROUTE_API_KEY;

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'geocode') {
        // Géocodage avec OpenRouteService
        $address = $_GET['address'] ?? '';
        if (empty($address)) {
            echo json_encode(['error' => true, 'message' => 'Adresse manquante']);
            exit;
        }
        
        // Validation basique
        if (strlen($address) > 500) {
            echo json_encode(['error' => true, 'message' => 'Adresse trop longue']);
            exit;
        }
        
        // Stratégie 1 : Essayer l'adresse complète
        $url = 'https://api.openrouteservice.org/geocode/search?api_key=' . $OPENROUTE_API_KEY . 
               '&text=' . urlencode($address) . '&boundary.country=FR&size=5';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, application/geo+json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Vérifier les erreurs cURL
        if ($response === false || !empty($curlError)) {
            echo json_encode([
                'error' => true, 
                'message' => 'Erreur de connexion à l\'API',
                'detail' => $curlError
            ]);
            exit;
        }
        
        // Vérifier que la réponse n'est pas vide
        if (empty($response)) {
            echo json_encode([
                'error' => true, 
                'message' => 'Réponse vide de l\'API'
            ]);
            exit;
        }
        
        // Vérifier le code HTTP
        if ($httpCode !== 200) {
            echo json_encode([
                'error' => true, 
                'message' => 'Erreur API (HTTP ' . $httpCode . ')',
                'response' => $response
            ]);
            exit;
        }
        
        // Vérifier que c'est du JSON valide
        $jsonData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'error' => true, 
                'message' => 'Réponse API invalide',
                'json_error' => json_last_error_msg()
            ]);
            exit;
        }
        
        // Vérifier si des résultats ont été trouvés
        if (empty($jsonData['features'])) {
            // Stratégie 2 : Essayer avec juste le code postal et la ville
            if (preg_match('/(\d{5})\s+([A-Z\s\-]+)/i', $address, $matches)) {
                $simpleAddress = $matches[1] . ' ' . trim($matches[2]);
                
                $url2 = 'https://api.openrouteservice.org/geocode/search?api_key=' . $OPENROUTE_API_KEY . 
                       '&text=' . urlencode($simpleAddress) . '&boundary.country=FR&size=1';
                
                $ch2 = curl_init($url2);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Accept: application/json, application/geo+json'
                ]);
                
                $response2 = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($httpCode2 === 200 && !empty($response2)) {
                    $jsonData2 = json_decode($response2, true);
                    if (!empty($jsonData2['features'])) {
                        echo $response2;
                        exit;
                    }
                }
            }
            
            echo json_encode([
                'error' => true, 
                'message' => 'Adresse non trouvée : ' . $address
            ]);
            exit;
        }
        
        // Retourner le premier résultat
        echo json_encode([
            'type' => 'FeatureCollection',
            'features' => [$jsonData['features'][0]]
        ]);
        
    } elseif ($action === 'route') {
        // Calcul d'itinéraire avec OpenRouteService
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => true, 'message' => 'JSON invalide']);
            exit;
        }
        
        if (empty($data['coordinates']) || !is_array($data['coordinates'])) {
            echo json_encode(['error' => true, 'message' => 'Coordonnées manquantes']);
            exit;
        }
        
        // Validation des coordonnées
        if (count($data['coordinates']) > 50) {
            echo json_encode(['error' => true, 'message' => 'Trop de points']);
            exit;
        }
        
        foreach ($data['coordinates'] as $coord) {
            if (!is_array($coord) || count($coord) !== 2 || 
                !is_numeric($coord[0]) || !is_numeric($coord[1])) {
                echo json_encode(['error' => true, 'message' => 'Format de coordonnées invalide']);
                exit;
            }
        }
        
        $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $OPENROUTE_API_KEY;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['coordinates' => $data['coordinates']]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json, application/geo+json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            echo json_encode([
                'error' => true, 
                'message' => 'Erreur de connexion',
                'detail' => $curlError
            ]);
            exit;
        }
        
        if ($httpCode !== 200) {
            echo json_encode([
                'error' => true, 
                'message' => 'Erreur API ' . $httpCode,
                'response' => $response
            ]);
            exit;
        }
        
        echo $response;
        
    } else {
        echo json_encode(['error' => true, 'message' => 'Action invalide']);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}