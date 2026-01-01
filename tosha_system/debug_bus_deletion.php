<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$bus_id = $_GET['bus_id'] ?? '';

if (empty($bus_id)) {
    echo "Please provide a bus ID: debug_bus_deletion.php?bus_id=X";
    exit();
}

try {
    // Get bus details
    $query = "SELECT * FROM buses WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bus) {
        echo "Bus not found!";
        exit();
    }
    
    echo "<h2>Debug: Bus Deletion Analysis</h2>";
    echo "<h3>Bus: {$bus['bus_name']} (ID: {$bus_id})</h3>";
    
    // Check active bookings
    $query = "SELECT COUNT(*) as count FROM tickets WHERE bus_id = ? AND travel_date >= CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $active_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p><strong>Active Bookings (today or future):</strong> {$active_bookings}</p>";
    
    if ($active_bookings > 0) {
        echo "<h4>Active Bookings Details:</h4>";
        $query = "SELECT ticket_no, passenger_name, travel_date, travel_time, seat_no 
                  FROM tickets t 
                  LEFT JOIN seats s ON t.seat_id = s.id 
                  WHERE t.bus_id = ? AND t.travel_date >= CURDATE() 
                  ORDER BY t.travel_date, t.travel_time";
        $stmt = $db->prepare($query);
        $stmt->execute([$bus_id]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Ticket No</th><th>Passenger</th><th>Date</th><th>Time</th><th>Seat</th></tr>";
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>{$booking['ticket_no']}</td>";
            echo "<td>{$booking['passenger_name']}</td>";
            echo "<td>{$booking['travel_date']}</td>";
            echo "<td>{$booking['travel_time']}</td>";
            echo "<td>{$booking['seat_no']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check all tickets (including past)
    $query = "SELECT COUNT(*) as count FROM tickets WHERE bus_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $total_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p><strong>Total Tickets (all time):</strong> {$total_tickets}</p>";
    
    // Check seats
    $query = "SELECT COUNT(*) as count FROM seats WHERE bus_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $total_seats = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p><strong>Total Seats:</strong> {$total_seats}</p>";
    
    // Check seat statuses
    $query = "SELECT status, COUNT(*) as count FROM seats WHERE bus_id = ? GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $seat_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Seat Statuses:</h4>";
    echo "<ul>";
    foreach ($seat_statuses as $status) {
        echo "<li>{$status['status']}: {$status['count']} seats</li>";
    }
    echo "</ul>";
    
    // Check if deletion would be allowed
    if ($active_bookings > 0) {
        echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4 style='color: #d32f2f; margin: 0 0 10px 0;'>❌ DELETION BLOCKED</h4>";
        echo "<p>This bus cannot be deleted because it has {$active_bookings} active booking(s).</p>";
        echo "<p><strong>Solution:</strong> Cancel or complete all active bookings first, then try deleting again.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4 style='color: #2e7d32; margin: 0 0 10px 0;'>✅ DELETION ALLOWED</h4>";
        echo "<p>This bus can be deleted safely. No active bookings found.</p>";
        echo "<p><strong>Note:</strong> This will delete {$total_tickets} ticket(s) and {$total_seats} seat(s) permanently.</p>";
        echo "</div>";
    }
    
    echo "<p><a href='bus_management.php'>← Back to Bus Management</a></p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
