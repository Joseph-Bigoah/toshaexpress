<?php
require_once 'config/database.php';

$bus_id = $_GET['bus_id'] ?? '';

if (empty($bus_id)) {
    echo '<p style="text-align: center; color: #666; grid-column: 1/-1;">Please select a bus to view seat map</p>';
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo '<p style="text-align: center; color: #666; grid-column: 1/-1;">Database connection failed</p>';
    exit();
}

try {
    // Get seats for the selected bus
    $query = "SELECT seat_no, status FROM seats WHERE bus_id = ? ORDER BY seat_no";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($seats)) {
        echo '<p style="text-align: center; color: #666; grid-column: 1/-1;">No seats found for this bus</p>';
        exit();
    }
    
    // Generate seat map HTML
    foreach ($seats as $seat) {
        echo '<div class="seat ' . htmlspecialchars($seat['status']) . '" ';
        echo 'data-seat="' . htmlspecialchars($seat['seat_no']) . '" ';
        echo 'onclick="toggleSeat(this)">';
        echo htmlspecialchars($seat['seat_no']);
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<p style="text-align: center; color: #666; grid-column: 1/-1;">Error loading seats: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
