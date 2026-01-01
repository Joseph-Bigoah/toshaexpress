<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Add seat_id column to tickets table if it doesn't exist
    $query = "ALTER TABLE tickets ADD COLUMN seat_id INT AFTER seat_no";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo "Added seat_id column to tickets table.<br>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "seat_id column already exists.<br>";
    } else {
        echo "Error adding seat_id column: " . $e->getMessage() . "<br>";
    }
}

try {
    // Add foreign key constraint for seat_id
    $query = "ALTER TABLE tickets ADD FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo "Added foreign key constraint for seat_id.<br>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "Foreign key constraint already exists.<br>";
    } else {
        echo "Error adding foreign key constraint: " . $e->getMessage() . "<br>";
    }
}

try {
    // Update existing tickets to have seat_id
    $query = "UPDATE tickets t 
              JOIN seats s ON t.bus_id = s.bus_id AND t.seat_no = s.seat_no 
              SET t.seat_id = s.id 
              WHERE t.seat_id IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $updated = $stmt->rowCount();
    echo "Updated $updated existing tickets with seat_id.<br>";
} catch (Exception $e) {
    echo "Error updating existing tickets: " . $e->getMessage() . "<br>";
}

echo "<br>Database schema update completed!<br>";
echo "<a href='bus_management.php'>← Back to Bus Management</a>";
?>
