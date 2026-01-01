<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $start_point = trim($_POST['start_point']);
        $destination = trim($_POST['destination']);
        $fare = floatval($_POST['fare']);
        $distance_km = intval($_POST['distance_km']);
        
        if (empty($start_point) || empty($destination) || $fare <= 0) {
            $error_message = 'Please fill in all fields with valid values.';
        } else {
            try {
                // Check if route already exists
                $query = "SELECT id FROM routes WHERE start_point = ? AND destination = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_point, $destination]);
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'Route already exists.';
                } else {
                    $query = "INSERT INTO routes (start_point, destination, fare, distance_km) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$start_point, $destination, $fare, $distance_km]);
                    
                    $success_message = 'Route added successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error adding route: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $route_id = $_POST['route_id'];
        $start_point = trim($_POST['start_point']);
        $destination = trim($_POST['destination']);
        $fare = floatval($_POST['fare']);
        $distance_km = intval($_POST['distance_km']);
        
        try {
            $query = "UPDATE routes SET start_point = ?, destination = ?, fare = ?, distance_km = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$start_point, $destination, $fare, $distance_km, $route_id]);
            
            $success_message = 'Route updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Error updating route: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $route_id = $_POST['route_id'];
        
        try {
            // Check if route has active bookings
            $query = "SELECT COUNT(*) as count FROM tickets WHERE (from_route = (SELECT CONCAT(start_point, ' → ', destination) FROM routes WHERE id = ?) OR to_route = (SELECT CONCAT(start_point, ' → ', destination) FROM routes WHERE id = ?)) AND travel_date >= CURDATE()";
            $stmt = $db->prepare($query);
            $stmt->execute([$route_id, $route_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $error_message = 'Cannot delete route with active bookings.';
            } else {
                $query = "DELETE FROM routes WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$route_id]);
                
                $success_message = 'Route deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting route: ' . $e->getMessage();
        }
    }
}

// Get all routes
$query = "SELECT * FROM routes ORDER BY start_point, destination";
$stmt = $db->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get route for editing
$edit_route = null;
if (isset($_GET['edit'])) {
    $route_id = $_GET['edit'];
    $query = "SELECT * FROM routes WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$route_id]);
    $edit_route = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Route Management</title>
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
                <?php echo $edit_route ? 'Edit Route' : 'Add New Route'; ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_route ? 'edit' : 'add'; ?>">
                <?php if ($edit_route): ?>
                    <input type="hidden" name="route_id" value="<?php echo $edit_route['id']; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label for="start_point">From:</label>
                        <input type="text" id="start_point" name="start_point" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_route['start_point'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">To:</label>
                        <input type="text" id="destination" name="destination" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_route['destination'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fare">Fare (KSh):</label>
                        <input type="number" id="fare" name="fare" class="form-control" 
                               step="0.01" min="0.01" value="<?php echo $edit_route['fare'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="distance_km">Distance (km):</label>
                        <input type="number" id="distance_km" name="distance_km" class="form-control" 
                               min="0" value="<?php echo $edit_route['distance_km'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?php echo $edit_route ? 'Update Route' : 'Add Route'; ?>
                    </button>
                    <?php if ($edit_route): ?>
                        <a href="route_management.php" class="btn btn-danger btn-lg">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">All Routes</div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Fare (KSh)</th>
                            <th>Distance (km)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($route['start_point']); ?></td>
                            <td><?php echo htmlspecialchars($route['destination']); ?></td>
                            <td>KSh <?php echo number_format($route['fare'], 2); ?></td>
                            <td><?php echo $route['distance_km']; ?> km</td>
                            <td>
                                <a href="?edit=<?php echo $route['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <button class="btn btn-danger btn-sm" onclick="deleteRoute(<?php echo $route['id']; ?>, '<?php echo htmlspecialchars($route['start_point'] . ' → ' . $route['destination']); ?>')">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .table-responsive {
            overflow-x: auto;
        }
    </style>

    <script>
        function deleteRoute(routeId, routeName) {
            if (confirm('Are you sure you want to delete route "' + routeName + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                                '<input type="hidden" name="route_id" value="' + routeId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
