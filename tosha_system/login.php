<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $query = "SELECT id, username, password, role FROM admins WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug information (remove in production)
            if (isset($_GET['debug'])) {
                echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "<strong>Debug Info:</strong><br>";
                echo "Username: " . htmlspecialchars($username) . "<br>";
                echo "Password: " . htmlspecialchars($password) . "<br>";
                echo "User found: " . ($user ? 'Yes' : 'No') . "<br>";
                if ($user) {
                    echo "DB Password Hash: " . htmlspecialchars($user['password']) . "<br>";
                    echo "Expected Hash: " . md5($password) . "<br>";
                    echo "Hashes Match: " . ($user['password'] === md5($password) ? 'Yes' : 'No') . "<br>";
                }
                echo "</div>";
            }
            
            if ($user && $user['password'] === md5($password)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid username or password. <a href="debug_login.php" style="color: #007bff;">Debug Login</a>';
            }
        } else {
            $error_message = 'Database connection failed.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Admin Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            /* Background image behind login card with yellow-dark-spots overlay */
            background-color: var(--primary-yellow);
            background-image:
                radial-gradient(rgba(13, 20, 66, 0.20) 2px, transparent 2px),
                radial-gradient(rgba(13, 20, 66, 0.12) 2px, transparent 2px),
                linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)),
                url('assets/images/login_bg.bg.jpg');
            background-position: 0 0, 11px 11px, center, center;
            background-size: 22px 22px, 22px 22px, cover, cover;
            background-repeat: repeat, repeat, no-repeat, no-repeat;
            background-attachment: scroll, scroll, fixed, fixed;
        }
        
        .login-container {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.1) 100%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .login-header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .login-header .motto {
            font-size: 1.2rem;
            font-style: italic;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 2rem;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(184, 134, 11, 0.15);
            background: #ffffff;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(184, 134, 11, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .fade-in {
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
            position: relative;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary-blue), var(--dark-blue), var(--primary-blue), var(--dark-blue));
            border-radius: 22px;
            z-index: -1;
            animation: borderGlow 3s ease-in-out infinite;
        }
        
        @keyframes borderGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .form-group label {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-body small {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .login-body {
            border-radius: 0 0 20px 20px;
        }
    </style>
</head>
<body>
    <div class="login-container fade-in">
        <div class="login-header">
            <h1>TOSHA EXPRESS</h1>
            <p class="motto">"Safest Mean Of Transport At Affordable Fares"</p>
        </div>
        
        <div class="login-body">
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Login
                    </button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 0.75rem;">
                <small style="color: #555; font-weight: 600; letter-spacing: 0.3px; font-size: 0.75rem;">
                    Developed by @Joseph Bigoah Puok
                </small>
            </div>
        </div>
    </div>
</body>
</html>
