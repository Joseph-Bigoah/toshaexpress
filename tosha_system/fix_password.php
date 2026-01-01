<?php
// Quick password fix script for TOSHA EXPRESS
require_once 'config/database.php';

echo "<h1>TOSHA EXPRESS - Password Fix</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #FFD700; } .fix-item { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #4169E1; }</style>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        // Update admin password to admin123
        $admin_password_hash = md5('admin123');
        $query = "UPDATE admins SET password = ? WHERE username = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute([$admin_password_hash]);
        
        echo "<div class='fix-item'>";
        echo "<h3>Password Update</h3>";
        echo "<p style='color: green;'>✓ Admin password updated to 'admin123'</p>";
        echo "<p><strong>Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li>Username: admin</li>";
        echo "<li>Password: admin123</li>";
        echo "</ul>";
        echo "<p><a href='login.php' style='background: #DC143C; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
        echo "</div>";
        
    } else {
        echo "<div class='fix-item'>";
        echo "<p style='color: red;'>❌ Database connection failed. Please check your XAMPP settings.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='fix-item'>";
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
