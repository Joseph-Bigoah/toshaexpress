<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

header('Content-Type: application/json');

$seat_id = $_GET['seat_id'] ?? '';

if (empty($seat_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Seat ID required'
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Detect if tickets table has seat_id column
    $hasSeatId = false;
    try {
        $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'seat_id'");
        $colStmt->execute();
        $hasSeatId = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $_) { /* ignore */ }

    if ($hasSeatId) {
        // Get ticket details using seat_id
        $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name
                  FROM tickets t 
                  LEFT JOIN buses b ON t.bus_id = b.id 
                  WHERE t.seat_id = ? 
                  ORDER BY t.created_at DESC 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$seat_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Legacy fallback: resolve seat_no and bus_id and query by those
        $seatQuery = $db->prepare("SELECT seat_no, bus_id FROM seats WHERE id = ?");
        $seatQuery->execute([$seat_id]);
        $seat = $seatQuery->fetch(PDO::FETCH_ASSOC);

        if ($seat) {
            $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name
                      FROM tickets t 
                      LEFT JOIN buses b ON t.bus_id = b.id 
                      WHERE t.seat_no = ? AND t.bus_id = ? 
                      ORDER BY t.created_at DESC 
                      LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$seat['seat_no'], $seat['bus_id']]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $ticket = false;
        }
    }
    
    if ($ticket) {
        echo json_encode([
            'success' => true,
            'ticket' => $ticket
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No ticket found for this seat'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
