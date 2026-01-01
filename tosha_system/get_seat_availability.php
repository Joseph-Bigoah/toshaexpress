<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

header('Content-Type: application/json');

$bus_id = $_GET['bus_id'] ?? '';

if (empty($bus_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Bus ID required'
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get seat availability for the bus
    $query = "SELECT seat_no, status FROM seats WHERE bus_id = ? ORDER BY seat_no";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get seat statistics
    $query = "SELECT 
                COUNT(*) as total_seats,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_seats,
                SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked_seats,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_seats
              FROM seats WHERE bus_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'seats' => $seats,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
