<?php
/**
 * API endpoint for employees management
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

try {
    $employees = $db->fetchAll('SELECT * FROM employees WHERE active = TRUE ORDER BY name');
    
    // Format for frontend
    $formattedEmployees = [];
    foreach ($employees as $employee) {
        $formattedEmployees[] = [
            'id' => (int)$employee['id'],
            'name' => $employee['name'],
            'role' => $employee['role'],
            'active' => (bool)$employee['active']
        ];
    }
    
    echo json_encode($formattedEmployees);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>