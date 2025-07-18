<?php
/**
 * API endpoint for appointments management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Parse the request
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api/appointments', '', $path);
$segments = array_filter(explode('/', $path));

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $segments);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'DELETE':
            handleDelete($db, $segments);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($db, $segments) {
    $startDate = $_GET['startDate'] ?? date('Y-m-01');
    $endDate = $_GET['endDate'] ?? date('Y-m-t');
    
    $query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.service_description,
            a.price,
            a.notes,
            c.name as client_name,
            c.phone as client_phone,
            d.name as dog_name,
            d.breed as dog_breed,
            e.name as employee_name,
            s.name as service_name,
            added_by.name as added_by_name
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        JOIN dogs d ON a.dog_id = d.id
        JOIN employees e ON a.employee_id = e.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN employees added_by ON a.added_by_employee_id = added_by.id
        WHERE a.appointment_date BETWEEN ? AND ?
        ORDER BY a.appointment_date, a.start_time
    ";
    
    $appointments = $db->fetchAll($query, [$startDate, $endDate]);
    
    // Convert to format expected by frontend
    $formattedAppointments = [];
    foreach ($appointments as $appointment) {
        $formattedAppointments[] = [
            'id' => (int)$appointment['id'],
            'date' => $appointment['appointment_date'],
            'timeStart' => substr($appointment['start_time'], 0, 5), // HH:MM format
            'timeEnd' => substr($appointment['end_time'], 0, 5),
            'clientName' => $appointment['client_name'],
            'dogName' => $appointment['dog_name'],
            'phone' => $appointment['client_phone'] ?? '',
            'service' => $appointment['service_description'] ?? $appointment['service_name'] ?? '',
            'employee' => $appointment['employee_name'],
            'addedBy' => $appointment['added_by_name'],
            'status' => $appointment['status'],
            'price' => $appointment['price'] ? (float)$appointment['price'] : null,
            'notes' => $appointment['notes'] ?? ''
        ];
    }
    
    echo json_encode($formattedAppointments);
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['date', 'timeStart', 'timeEnd', 'clientName', 'dogName', 'service', 'employee', 'addedBy'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $db->beginTransaction();
    
    try {
        // Find or create client
        $clientId = findOrCreateClient($db, $input['clientName'], $input['phone'] ?? '');
        
        // Find or create dog
        $dogId = findOrCreateDog($db, $clientId, $input['dogName']);
        
        // Find employee
        $employee = $db->fetchOne('SELECT id FROM employees WHERE name = ?', [$input['employee']]);
        if (!$employee) {
            throw new Exception('Employee not found');
        }
        $employeeId = $employee['id'];
        
        // Find added_by employee
        $addedByEmployee = $db->fetchOne('SELECT id FROM employees WHERE name = ?', [$input['addedBy']]);
        $addedByEmployeeId = $addedByEmployee ? $addedByEmployee['id'] : null;
        
        // Create appointment
        $query = "
            INSERT INTO appointments 
            (client_id, dog_id, employee_id, appointment_date, start_time, end_time, service_description, added_by_employee_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $db->execute($query, [
            $clientId,
            $dogId,
            $employeeId,
            $input['date'],
            $input['timeStart'] . ':00',
            $input['timeEnd'] . ':00',
            $input['service'],
            $addedByEmployeeId
        ]);
        
        $appointmentId = $db->lastInsertId();
        $db->commit();
        
        // Return the created appointment
        $newAppointment = $db->fetchOne("
            SELECT 
                a.id,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.service_description,
                c.name as client_name,
                c.phone as client_phone,
                d.name as dog_name,
                e.name as employee_name,
                added_by.name as added_by_name
            FROM appointments a
            JOIN clients c ON a.client_id = c.id
            JOIN dogs d ON a.dog_id = d.id
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN employees added_by ON a.added_by_employee_id = added_by.id
            WHERE a.id = ?
        ", [$appointmentId]);
        
        $response = [
            'id' => (int)$newAppointment['id'],
            'date' => $newAppointment['appointment_date'],
            'timeStart' => substr($newAppointment['start_time'], 0, 5),
            'timeEnd' => substr($newAppointment['end_time'], 0, 5),
            'clientName' => $newAppointment['client_name'],
            'dogName' => $newAppointment['dog_name'],
            'phone' => $newAppointment['client_phone'] ?? '',
            'service' => $newAppointment['service_description'],
            'employee' => $newAppointment['employee_name'],
            'addedBy' => $newAppointment['added_by_name']
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleDelete($db, $segments) {
    if (empty($segments[0])) {
        http_response_code(400);
        echo json_encode(['error' => 'Appointment ID required']);
        return;
    }
    
    $appointmentId = $segments[0];
    
    $rowsAffected = $db->execute('DELETE FROM appointments WHERE id = ?', [$appointmentId]);
    
    if ($rowsAffected === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        return;
    }
    
    echo json_encode(['message' => 'Appointment deleted successfully']);
}

function findOrCreateClient($db, $clientName, $phone) {
    $formattedPhone = preg_replace('/[^\d]/', '', $phone);
    
    if (strlen($formattedPhone) >= 9) {
        // Try to find existing client by phone
        $client = $db->fetchOne(
            'SELECT id FROM clients WHERE REPLACE(REPLACE(phone, " ", ""), "-", "") = ?',
            [$formattedPhone]
        );
        
        if ($client) {
            // Update client name if provided
            if (!empty($clientName)) {
                $db->execute('UPDATE clients SET name = ? WHERE id = ?', [$clientName, $client['id']]);
            }
            return $client['id'];
        }
    }
    
    // Create new client
    $db->execute('INSERT INTO clients (name, phone) VALUES (?, ?)', [$clientName, $phone]);
    return $db->lastInsertId();
}

function findOrCreateDog($db, $clientId, $dogName) {
    // Try to find existing dog
    $dog = $db->fetchOne('SELECT id FROM dogs WHERE client_id = ? AND name = ?', [$clientId, $dogName]);
    
    if ($dog) {
        return $dog['id'];
    }
    
    // Create new dog
    $db->execute('INSERT INTO dogs (client_id, name) VALUES (?, ?)', [$clientId, $dogName]);
    return $db->lastInsertId();
}
?>