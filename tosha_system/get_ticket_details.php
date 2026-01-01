<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

header('Content-Type: application/json');

$ticket_no = $_GET['ticket_no'] ?? '';

if (empty($ticket_no)) {
    echo json_encode([
        'success' => false,
        'message' => 'No ticket number provided'
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
              LEFT JOIN buses b ON t.bus_id = b.id 
              WHERE t.ticket_no = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$ticket_no]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ticket) {
        echo json_encode([
            'success' => true,
            'ticket' => $ticket
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
