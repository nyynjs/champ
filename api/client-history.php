<?php
/**
 * API endpoint for client history lookup
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get phone number from URL
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$segments = array_filter(explode('/', $path));

// Find the phone number segment (should be after 'client-history')
$phone = '';
$foundClientHistory = false;
foreach ($segments as $segment) {
    if ($foundClientHistory) {
        $phone = $segment;
        break;
    }
    if ($segment === 'client-history') {
        $foundClientHistory = true;
    }
}

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone number required']);
    exit;
}

try {
    $clientHistory = getClientHistory($db, $phone);
    echo json_encode($clientHistory);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getClientHistory($db, $phone) {
    $formattedPhone = preg_replace('/[^\d]/', '', $phone);
    
    if (strlen($formattedPhone) < 9) {
        return [
            'isNew' => true,
            'visitCount' => 0,
            'lastVisit' => null,
            'clientName' => null,
            'dogNames' => [],
            'services' => []
        ];
    }

    // Find client by phone
    $client = $db->fetchOne(
        'SELECT * FROM clients WHERE REPLACE(REPLACE(phone, " ", ""), "-", "") = ?',
        [$formattedPhone]
    );

    if (!$client) {
        return [
            'isNew' => true,
            'visitCount' => 0,
            'lastVisit' => null,
            'clientName' => null,
            'dogNames' => [],
            'services' => []
        ];
    }

    // Get appointment history
    $historyQuery = "
        SELECT 
            a.appointment_date,
            a.service_description,
            a.status,
            d.name as dog_name,
            s.name as service_name
        FROM appointments a
        JOIN dogs d ON a.dog_id = d.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.client_id = ?
        ORDER BY a.appointment_date DESC
    ";

    $history = $db->fetchAll($historyQuery, [$client['id']]);

    if (empty($history)) {
        return [
            'isNew' => true,
            'visitCount' => 0,
            'lastVisit' => null,
            'clientName' => $client['name'],
            'dogNames' => [],
            'services' => []
        ];
    }

    // Extract unique dog names and services
    $dogNames = [];
    $services = [];
    
    foreach ($history as $appointment) {
        if (!in_array($appointment['dog_name'], $dogNames)) {
            $dogNames[] = $appointment['dog_name'];
        }
        
        $service = $appointment['service_description'] ?: $appointment['service_name'];
        if ($service && !in_array($service, $services)) {
            $services[] = $service;
        }
    }

    return [
        'isNew' => false,
        'visitCount' => count($history),
        'lastVisit' => $history[0]['appointment_date'],
        'clientName' => $client['name'],
        'dogNames' => $dogNames,
        'services' => $services,
        'clientId' => (int)$client['id']
    ];
}
?>