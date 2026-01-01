<?php
// Debug login script for TOSHA EXPRESS
require_once 'config/database.php';

echo "<h1>TOSHA EXPRESS - Login Debug</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f8f9fa; } .debug-item { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; } .success { border-left-color: #28a745; } .error { border-left-color: #1a237e; } .warning { border-left-color: #ffc107; }</style>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<div class='debug-item success'>";
        echo "<h3>✓ Database Connection</h3>";
        echo "<p>Database connection successful</p>";
        echo "</div>";
        
        // Check if admin user exists
        $query = "SELECT * FROM admins WHERE username = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<div class='debug-item success'>";
            echo "<h3>✓ Admin User Found</h3>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($admin['username']) . "</p>";
            echo "<p><strong>Role:</strong> " . htmlspecialchars($admin['role']) . "</p>";
            echo "<p><strong>Password Hash in DB:</strong> " . htmlspecialchars($admin['password']) . "</p>";
            echo "</div>";
            
            // Test password hashing
            $test_password = 'admin123';
            $expected_hash = md5($test_password);
            $actual_hash = $admin['password'];
            
            echo "<div class='debug-item'>";
            echo "<h3>Password Hash Test</h3>";
            echo "<p><strong>Test Password:</strong> admin123</p>";
            echo "<p><strong>Expected Hash:</strong> " . $expected_hash . "</p>";
            echo "<p><strong>Actual Hash:</strong> " . $actual_hash . "</p>";
            
            if ($expected_hash === $actual_hash) {
                echo "<p style='color: green; font-weight: bold;'>✓ Password hashes match!</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ Password hashes do NOT match!</p>";
                echo "<p>This is why login is failing.</p>";
            }
            echo "</div>";
            
            // Fix the password if it doesn't match
            if ($expected_hash !== $actual_hash) {
                echo "<div class='debug-item warning'>";
                echo "<h3>Fixing Password...</h3>";
                
                $query = "UPDATE admins SET password = ? WHERE username = 'admin'";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([$expected_hash]);
                
                if ($result) {
                    echo "<p style='color: green;'>✓ Password updated successfully!</p>";
                    echo "<p>You can now login with: admin / admin123</p>";
                } else {
                    echo "<p style='color: red;'>❌ Failed to update password</p>";
                }
                echo "</div>";
            }
            
        } else {
            echo "<div class='debug-item error'>";
            echo "<h3>❌ Admin User Not Found</h3>";
            echo "<p>No admin user found in database. Please run the setup script first.</p>";
            echo "</div>";
        }
        
        // Test login logic
        echo "<div class='debug-item'>";
        echo "<h3>Login Logic Test</h3>";
        $test_username = 'admin';
        $test_password = 'admin123';
        
        $query = "SELECT id, username, password, role FROM admins WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$test_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['password'] === md5($test_password)) {
            echo "<p style='color: green;'>✓ Login logic test PASSED</p>";
            echo "<p>Username and password validation works correctly.</p>";
        } else {
            echo "<p style='color: red;'>❌ Login logic test FAILED</p>";
            echo "<p>There's still an issue with the login validation.</p>";
        }
        echo "</div>";
        
    } else {
        echo "<div class='debug-item error'>";
        echo "<h3>❌ Database Connection Failed</h3>";
        echo "<p>Cannot connect to database. Please check XAMPP settings.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='debug-item error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<div class='debug-item' style='background: #e7f3ff; border-left-color: #0066cc;'>";
echo "<h3>Next Steps</h3>";
echo "<p>1. If password was fixed, try logging in again</p>";
echo "<p>2. If issues persist, try running: <a href='setup.php'>Setup Script</a></p>";
echo "<p>3. Check XAMPP is running (Apache + MySQL)</p>";
echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Login Again</a></p>";
echo "</div>";
?>
