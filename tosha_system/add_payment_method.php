<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<div style='max-width: 800px; margin: 50px auto; padding: 20px; font-family: Arial, sans-serif;'>";
echo "<h2 style='color: #333;'>TOSHA EXPRESS - Add Payment Method Column</h2>";

// Check if column already exists
$columnExists = false;
try {
    $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'payment_method'");
    $colStmt->execute();
    $columnExists = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>Error checking column:</strong> " . $e->getMessage();
    echo "</div>";
}

if ($columnExists) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='margin: 0 0 10px 0; color: #155724;'>✅ Column Already Exists</h4>";
    echo "<p style='margin: 0;'>The payment_method column already exists in the tickets table.</p>";
    echo "</div>";
} else {
    try {
        // Add payment_method column to tickets table
        $query = "ALTER TABLE tickets ADD COLUMN payment_method ENUM('cash', 'mpesa') DEFAULT 'cash' AFTER travel_time";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #155724;'>✅ SUCCESS!</h4>";
        echo "<p style='margin: 0;'>The payment_method column has been successfully added to the tickets table.</p>";
        echo "</div>";
        
        // Verify the column was added
        $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'payment_method'");
        $colStmt->execute();
        $result = $colStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<div style='background: #e3f2fd; padding: 15px; border: 1px solid #bbdefb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4 style='color: #0d47a1; margin: 0 0 10px 0;'>Column Details:</h4>";
            echo "<p style='margin: 5px 0;'><strong>Name:</strong> " . $result['Field'] . "</p>";
            echo "<p style='margin: 5px 0;'><strong>Type:</strong> " . $result['Type'] . "</p>";
            echo "<p style='margin: 5px 0;'><strong>Default:</strong> " . ($result['Default'] ?? 'NULL') . "</p>";
            echo "</div>";
        }
        
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #721c24;'>❌ ERROR</h4>";
        echo "<p style='margin: 0;'>Error adding payment_method column: " . htmlspecialchars($errorMsg) . "</p>";
        if (strpos($errorMsg, 'Duplicate column name') !== false) {
            echo "<p style='margin: 10px 0 0 0;'>The column may already exist. Please refresh this page to check.</p>";
        }
        echo "</div>";
    }
}

echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>";
echo "<a href='ticket_booking.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>← Back to Ticket Booking</a>";
echo "<a href='dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;'>Go to Dashboard</a>";
echo "</div>";

echo "</div>";
?>

