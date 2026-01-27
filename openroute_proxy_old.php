<?php
/**
 * Proxy pour OpenRouteService - Contourne la CSP
 */

header('Content-Type: application/json');

// Clé API OpenRouteService
$OPENROUTE_API_KEY = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjUxNGUyYzdmMWUzMTRmM2E4ZTBmZTYwYWEzZTAzNjNmIiwiaCI6Im11cm11cjY0In0=';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'geocode') {
        // Géocodage avec OpenRouteService
        $address = $_GET['address'] ?? '';
        if (empty($address)) {
            throw new Exception('Adresse manquante');
        }
        
        $url = 'https://api.openrouteservice.org/geocode/search?api_key=' . $OPENROUTE_API_KEY . 
               '&text=' . urlencode($address) . '&boundary.country=FR&size=1';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, application/geo+json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            http_response_code($httpCode);
            echo json_encode(['error' => true, 'message' => 'Erreur API ' . $httpCode]);
            exit;
        }
        
        echo $response;
        
    } elseif ($action === 'route') {
        // Calcul d'itinéraire avec OpenRouteService
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (empty($data['coordinates'])) {
            throw new Exception('Coordonnées manquantes');
        }
        
        $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $OPENROUTE_API_KEY;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['coordinates' => $data['coordinates']]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json, application/geo+json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            http_response_code($httpCode);
            echo json_encode(['error' => true, 'message' => 'Erreur API ' . $httpCode]);
            exit;
        }
        
        echo $response;
        
    } else {
        throw new Exception('Action invalide');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
