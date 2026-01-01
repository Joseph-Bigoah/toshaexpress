<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Update the parcels table to include 'approved' status
    $query = "ALTER TABLE parcels MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'delivered') DEFAULT 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo "✅ Successfully updated parcels table to include 'approved' status.<br>";
    
    // Show current status values
    $query = "SHOW COLUMNS FROM parcels LIKE 'status'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Updated Status Values:</h3>";
    echo "<p><strong>Status Column:</strong> " . $result['Type'] . "</p>";
    
    // Show current parcel counts by status
    $query = "SELECT status, COUNT(*) as count FROM parcels GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current Parcel Status Counts:</h3>";
    echo "<ul>";
    foreach ($status_counts as $status) {
        echo "<li><strong>" . ucfirst($status['status']) . ":</strong> " . $status['count'] . " parcels</li>";
    }
    echo "</ul>";
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ Database Update Complete!</h4>";
    echo "<p style='margin: 0; color: #155724;'>The parcels table now supports the following statuses:</p>";
    echo "<ul style='margin: 10px 0 0 0; color: #155724;'>";
    echo "<li><strong>Pending:</strong> Parcel recorded, waiting for approval</li>";
    echo "<li><strong>Approved:</strong> Parcel approved and ready for collection</li>";
    echo "<li><strong>In Transit:</strong> Parcel collected and in transit</li>";
    echo "<li><strong>Delivered:</strong> Parcel delivered to receiver</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='parcel_management.php'>← Back to Parcel Management</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>❌ Error Updating Database</h4>";
    echo "<p style='margin: 0; color: #721c24;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
