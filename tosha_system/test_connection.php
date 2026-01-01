<?php
// Test database connection and system status
require_once 'config/database.php';

echo "<h1>TOSHA EXPRESS - System Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #FFD700; } .test-item { background: white; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #4169E1; } .success { border-left-color: #28a745; } .error { border-left-color: #1a237e; }</style>";

// Test 1: Database Connection
echo "<div class='test-item'>";
echo "<h3>Test 1: Database Connection</h3>";
try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Check Tables
echo "<div class='test-item'>";
echo "<h3>Test 2: Database Tables</h3>";
try {
    $tables = ['admins', 'buses', 'routes', 'tickets', 'seats', 'parcels', 'users'];
    $all_tables_exist = true;
    
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' missing</p>";
            $all_tables_exist = false;
        }
    }
    
    if ($all_tables_exist) {
        echo "<p style='color: green; font-weight: bold;'>✓ All required tables exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error checking tables: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 3: Check Sample Data
echo "<div class='test-item'>";
echo "<h3>Test 3: Sample Data</h3>";
try {
    // Check admin user
    $query = "SELECT COUNT(*) as count FROM admins WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "<p style='color: green;'>✓ Admin user exists</p>";
        
        // Test password
        $query = "SELECT password FROM admins WHERE username = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $expected_hash = md5('admin123');
        
        if ($admin['password'] === $expected_hash) {
            echo "<p style='color: green;'>✓ Admin password is correct (admin123)</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Admin password needs to be updated. <a href='fix_password.php'>Fix Password</a></p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Admin user missing</p>";
    }
    
    // Check routes
    $query = "SELECT COUNT(*) as count FROM routes";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "<p style='color: green;'>✓ Sample routes exist (" . $result['count'] . " routes)</p>";
    } else {
        echo "<p style='color: red;'>❌ No routes found</p>";
    }
    
    // Check buses
    $query = "SELECT COUNT(*) as count FROM buses";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "<p style='color: green;'>✓ Sample buses exist (" . $result['count'] . " buses)</p>";
    } else {
        echo "<p style='color: red;'>❌ No buses found</p>";
    }
    
    // Check seats
    $query = "SELECT COUNT(*) as count FROM seats";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "<p style='color: green;'>✓ Seats generated (" . $result['count'] . " seats)</p>";
    } else {
        echo "<p style='color: red;'>❌ No seats found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error checking sample data: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: File Permissions
echo "<div class='test-item'>";
echo "<h3>Test 4: File System</h3>";
$required_files = [
    'login.php',
    'dashboard.php',
    'ticket_booking.php',
    'parcel_management.php',
    'bus_management.php',
    'route_management.php',
    'user_management.php',
    'reports.php',
    'assets/css/style.css',
    'config/database.php',
    'includes/session.php'
];

$all_files_exist = true;
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
        $all_files_exist = false;
    }
}

if ($all_files_exist) {
    echo "<p style='color: green; font-weight: bold;'>✓ All required files exist</p>";
}
echo "</div>";

// Summary
echo "<div class='test-item' style='background: #f8f9fa; border-left-color: #17a2b8;'>";
echo "<h3>System Status Summary</h3>";
echo "<p><strong>If all tests passed, your TOSHA EXPRESS system is ready to use!</strong></p>";
echo "<p><strong>Default Login:</strong> admin / admin123</p>";
echo "<p><a href='login.php' style='background: #DC143C; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>Go to Login Page</a></p>";
echo "</div>";
?>
