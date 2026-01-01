<?php
// TOSHA EXPRESS Database Setup Script
// Run this script to initialize the database

require_once 'config/database.php';

echo "<h1>TOSHA EXPRESS - Database Setup</h1>";
echo "<p>This script will create the database and tables for TOSHA EXPRESS system.</p>";

try {
    // Connect to MySQL without database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS tosha_express");
    echo "<p>✓ Database 'tosha_express' created successfully</p>";
    
    // Use the database
    $pdo->exec("USE tosha_express");
    
    // Read and execute SQL schema
    $sql = file_get_contents('sql/schema.sql');
    
    // Split SQL into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Skip errors for statements that might already exist
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "<p>⚠ Warning: " . $e->getMessage() . "</p>";
                }
            }
        }
    }
    
    echo "<p>✓ Database tables created successfully</p>";
    echo "<p>✓ Sample data inserted successfully</p>";
    
    // Test connection with the new database
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p>✓ Database connection test successful</p>";
        
        // Check if admin user exists
        $query = "SELECT COUNT(*) as count FROM admins WHERE username = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo "<p>✓ Admin user created successfully</p>";
        }
        
        // Check if routes exist
        $query = "SELECT COUNT(*) as count FROM routes";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo "<p>✓ Sample routes created successfully</p>";
        }
        
        // Check if buses exist
        $query = "SELECT COUNT(*) as count FROM buses";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo "<p>✓ Sample buses created successfully</p>";
        }
        
        echo "<h2>Setup Complete!</h2>";
        echo "<p><strong>Default Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li>Username: admin</li>";
        echo "<li>Password: admin123</li>";
        echo "</ul>";
        echo "<p><strong>Note:</strong> If you get 'Invalid username or password', please run the setup again or manually update the admin password in the database.</p>";
        echo "<p><a href='login.php' style='background: #DC143C; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
        
    } else {
        echo "<p>❌ Database connection test failed</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure XAMPP is running and MySQL is started.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #FFD700;
}
h1 {
    color: #DC143C;
    text-align: center;
}
p {
    background: white;
    padding: 10px;
    border-radius: 5px;
    margin: 10px 0;
    border-left: 4px solid #4169E1;
}
ul {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #DC143C;
}
</style>
