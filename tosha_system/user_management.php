<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

// Only admin can access this page
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        
        if (empty($name) || empty($username) || empty($password) || empty($role)) {
            $error_message = 'Please fill in all fields.';
        } else {
            try {
                // Check if username already exists
                $query = "SELECT id FROM users WHERE username = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'Username already exists.';
                } else {
                    $query = "INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $username, md5($password), $role]);
                    
                    $success_message = 'User added successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error adding user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $user_id = $_POST['user_id'];
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        try {
            if (!empty($password)) {
                $query = "UPDATE users SET name = ?, username = ?, password = ?, role = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $username, md5($password), $role, $status, $user_id]);
            } else {
                $query = "UPDATE users SET name = ?, username = ?, role = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $username, $role, $status, $user_id]);
            }
            
            $success_message = 'User updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Error updating user: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'];
        
        try {
            // Don't allow deleting own account
            if ($user_id == $_SESSION['user_id']) {
                $error_message = 'You cannot delete your own account.';
            } else {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                
                $success_message = 'User deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting user: ' . $e->getMessage();
        }
    }
}

// Get all users
$query = "SELECT * FROM users ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $user_id = $_GET['edit'];
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - User Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label for="name">Full Name:</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               <?php echo $edit_user ? '' : 'required'; ?>>
                        <?php if ($edit_user): ?>
                            <small class="text-muted">Leave blank to keep current password</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="">Select role</option>
                            <option value="admin" <?php echo ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="clerk" <?php echo ($edit_user['role'] ?? '') === 'clerk' ? 'selected' : ''; ?>>Clerk</option>
                        </select>
                    </div>
                    
                    <?php if ($edit_user): ?>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo ($edit_user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="user_management.php" class="btn btn-danger btn-lg">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">All Users</div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                    Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .table-responsive {
            overflow-x: auto;
        }
        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>

    <script>
        function deleteUser(userId, userName) {
            if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                                '<input type="hidden" name="user_id" value="' + userId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
