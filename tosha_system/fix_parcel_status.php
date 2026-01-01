<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Fixing Parcel Status Column</h2>";

try {
    // Update the parcels table to include 'approved' status
    $query = "ALTER TABLE parcels MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'delivered') DEFAULT 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ SUCCESS!</h4>";
    echo "<p style='margin: 0; color: #155724;'>Parcel status column has been successfully updated to include 'approved' status.</p>";
    echo "</div>";
    
    // Show current status values
    $query = "SHOW COLUMNS FROM parcels LIKE 'status'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Updated Status Column:</h3>";
    echo "<p><strong>Type:</strong> " . $result['Type'] . "</p>";
    echo "<p><strong>Default:</strong> " . $result['Default'] . "</p>";
    
    // Test the update by trying to insert a test value
    echo "<h3>Testing Status Values:</h3>";
    $test_statuses = ['pending', 'approved', 'in_transit', 'delivered'];
    
    foreach ($test_statuses as $status) {
        try {
            $query = "SELECT COUNT(*) as count FROM parcels WHERE status = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$status]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<p>✅ '$status' - $count parcels</p>";
        } catch (Exception $e) {
            echo "<p>❌ '$status' - Error: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<div style='background: #e3f2fd; padding: 15px; border: 1px solid #bbdefb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #0d47a1; margin: 0 0 10px 0;'>🎉 Database Update Complete!</h4>";
    echo "<p style='margin: 0; color: #0d47a1;'>You can now use the 'Approved' status in the parcel management system.</p>";
    echo "<p style='margin: 10px 0 0 0; color: #0d47a1;'><a href='parcel_management.php' style='color: #0d47a1; font-weight: bold;'>← Go to Parcel Management</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>❌ ERROR</h4>";
    echo "<p style='margin: 0; color: #721c24;'>Error updating database: " . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Make sure you have admin privileges on the database</li>";
    echo "<li>Check if the parcels table exists</li>";
    echo "<li>Verify database connection is working</li>";
    echo "</ol>";
}
?>
