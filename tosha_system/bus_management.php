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
        $bus_name = trim($_POST['bus_name']);
        $plate_no = trim($_POST['plate_no']);
        $color = trim($_POST['color']);
        $driver_name = trim($_POST['driver_name']);
        
        if (empty($bus_name) || empty($plate_no) || empty($color) || empty($driver_name)) {
            $error_message = 'Please fill in all fields.';
        } else {
            try {
                // Insert bus
                $query = "INSERT INTO buses (bus_name, plate_no, color, driver_name) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$bus_name, $plate_no, $color, $driver_name]);
                
                $bus_id = $db->lastInsertId();
                
                // Generate seats for the new bus
                for ($i = 1; $i <= 60; $i++) {
                    $seat_no = 'S' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $query = "INSERT INTO seats (bus_id, seat_no) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id, $seat_no]);
                }
                
                $success_message = 'Bus added successfully!';
            } catch (Exception $e) {
                $error_message = 'Error adding bus: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $bus_id = $_POST['bus_id'];
        $bus_name = trim($_POST['bus_name']);
        $plate_no = trim($_POST['plate_no']);
        $color = trim($_POST['color']);
        $driver_name = trim($_POST['driver_name']);
        $status = $_POST['status'];
        
        try {
            $query = "UPDATE buses SET bus_name = ?, plate_no = ?, color = ?, driver_name = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$bus_name, $plate_no, $color, $driver_name, $status, $bus_id]);
            
            $success_message = 'Bus updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Error updating bus: ' . $e->getMessage();
        }
    } elseif ($action === 'generate_seats') {
        $bus_id = $_POST['bus_id'] ?? null;
        
        try {
            if ($bus_id) {
                // Generate seats for a specific bus
                $query = "SELECT id, bus_name FROM buses WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$bus_id]);
                $bus = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bus) {
                    $error_message = 'Bus not found.';
                } else {
                    // Check if seats already exist
                    $query = "SELECT COUNT(*) as count FROM seats WHERE bus_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id]);
                    $seat_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($seat_count > 0) {
                        $error_message = "Bus '{$bus['bus_name']}' already has {$seat_count} seat(s). Delete existing seats first if you want to regenerate.";
                    } else {
                        // Generate 60 seats for the bus
                        $db->beginTransaction();
                        try {
                            for ($i = 1; $i <= 60; $i++) {
                                $seat_no = 'S' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                $query = "INSERT INTO seats (bus_id, seat_no) VALUES (?, ?)";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$bus_id, $seat_no]);
                            }
                            $db->commit();
                            $success_message = "Successfully generated 60 seats for bus '{$bus['bus_name']}'!";
                        } catch (Exception $e) {
                            $db->rollBack();
                            throw $e;
                        }
                    }
                }
            } else {
                // Generate seats for all buses that don't have seats
                $query = "SELECT b.id, b.bus_name, COUNT(s.id) as seat_count 
                          FROM buses b 
                          LEFT JOIN seats s ON b.id = s.bus_id 
                          GROUP BY b.id, b.bus_name 
                          HAVING seat_count = 0";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $buses_without_seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($buses_without_seats)) {
                    $success_message = 'All buses already have seats!';
                } else {
                    $db->beginTransaction();
                    try {
                        $generated_count = 0;
                        foreach ($buses_without_seats as $bus) {
                            for ($i = 1; $i <= 60; $i++) {
                                $seat_no = 'S' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                $query = "INSERT INTO seats (bus_id, seat_no) VALUES (?, ?)";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$bus['id'], $seat_no]);
                            }
                            $generated_count++;
                        }
                        $db->commit();
                        $success_message = "Successfully generated seats for {$generated_count} bus(es)!";
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error generating seats: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $bus_id = $_POST['bus_id'];
        
        try {
            // Check if bus has active bookings
            $query = "SELECT COUNT(*) as count FROM tickets WHERE bus_id = ? AND travel_date >= CURDATE()";
            $stmt = $db->prepare($query);
            $stmt->execute([$bus_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug: Get bus name for better error message
            $query = "SELECT bus_name FROM buses WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$bus_id]);
            $bus_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $bus_name = $bus_info ? $bus_info['bus_name'] : 'Unknown';
            
            if ($result['count'] > 0) {
                $error_message = "Cannot delete bus '{$bus_name}' - it has {$result['count']} active booking(s). Please cancel or complete all bookings first.";
            } else {
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Delete all seats for this bus first
                    $query = "DELETE FROM seats WHERE bus_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id]);
                    
                    // Delete all tickets for this bus (historical data)
                    $query = "DELETE FROM tickets WHERE bus_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id]);
                    
                    // Delete the bus
                    $query = "DELETE FROM buses WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id]);
                    
                    // Commit transaction
                    $db->commit();
                    $success_message = 'Bus and all associated data deleted successfully!';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting bus: ' . $e->getMessage();
        }
    }
}

// Get all buses with seat counts
$query = "SELECT b.*, COUNT(s.id) as seat_count 
          FROM buses b 
          LEFT JOIN seats s ON b.id = s.bus_id 
          GROUP BY b.id 
          ORDER BY b.bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bus for editing
$edit_bus = null;
if (isset($_GET['edit'])) {
    $bus_id = $_GET['edit'];
    $query = "SELECT * FROM buses WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $edit_bus = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Bus Management</title>
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
                <?php echo $edit_bus ? 'Edit Bus' : 'Add New Bus'; ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_bus ? 'edit' : 'add'; ?>">
                <?php if ($edit_bus): ?>
                    <input type="hidden" name="bus_id" value="<?php echo $edit_bus['id']; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label for="bus_name">Bus Name:</label>
                        <input type="text" id="bus_name" name="bus_name" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_bus['bus_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="plate_no">Plate Number:</label>
                        <input type="text" id="plate_no" name="plate_no" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_bus['plate_no'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Color:</label>
                        <select id="color" name="color" class="form-control" required>
                            <option value="">Select color</option>
                            <option value="Yellow" <?php echo ($edit_bus['color'] ?? '') === 'Yellow' ? 'selected' : ''; ?>>Yellow</option>
                            <option value="Red" <?php echo ($edit_bus['color'] ?? '') === 'Red' ? 'selected' : ''; ?>>Red</option>
                            <option value="Blue" <?php echo ($edit_bus['color'] ?? '') === 'Blue' ? 'selected' : ''; ?>>Blue</option>
                            <option value="White" <?php echo ($edit_bus['color'] ?? '') === 'White' ? 'selected' : ''; ?>>White</option>
                            <option value="Green" <?php echo ($edit_bus['color'] ?? '') === 'Green' ? 'selected' : ''; ?>>Green</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="driver_name">Driver Name:</label>
                        <input type="text" id="driver_name" name="driver_name" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_bus['driver_name'] ?? ''); ?>" required>
                    </div>
                    
                    <?php if ($edit_bus): ?>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo ($edit_bus['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_bus['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?php echo $edit_bus ? 'Update Bus' : 'Add Bus'; ?>
                    </button>
                    <?php if ($edit_bus): ?>
                        <a href="bus_management.php" class="btn btn-danger btn-lg">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>All Buses</span>
                <form method="POST" action="" style="display: inline-block; margin: 0;">
                    <input type="hidden" name="action" value="generate_seats">
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Generate 60 seats for all buses that don\'t have seats?')">
                        🔧 Generate Seats for All Buses
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bus Name</th>
                            <th>Plate Number</th>
                            <th>Color</th>
                            <th>Driver</th>
                            <th>Capacity</th>
                            <th>Seats</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                            <td><?php echo htmlspecialchars($bus['plate_no']); ?></td>
                            <td>
                                <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo strtolower($bus['color']); ?>; border: 1px solid #ccc; vertical-align: middle; margin-right: 5px;"></span>
                                <?php echo htmlspecialchars($bus['color']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($bus['driver_name']); ?></td>
                            <td><?php echo $bus['capacity']; ?> seats</td>
                            <td>
                                <?php 
                                $seat_count = (int)($bus['seat_count'] ?? 0);
                                if ($seat_count === 0): 
                                ?>
                                    <span class="badge badge-danger" style="background: #dc3545; color: white;">No Seats</span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="background: #28a745; color: white;"><?php echo $seat_count; ?> seats</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $bus['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($bus['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($seat_count === 0): ?>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="action" value="generate_seats">
                                        <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Generate 60 seats for this bus">
                                            🔧 Generate Seats
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="?edit=<?php echo $bus['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <button class="btn btn-danger btn-sm" onclick="deleteBus(<?php echo $bus['id']; ?>, '<?php echo htmlspecialchars($bus['bus_name']); ?>')">
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
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .table-responsive {
            overflow-x: auto;
        }
    </style>

    <script>
        function deleteBus(busId, busName) {
            const confirmMessage = `⚠️ WARNING: Delete Bus "${busName}"\n\n` +
                                 `This will permanently delete:\n` +
                                 `• The bus record\n` +
                                 `• All 60 seats for this bus\n` +
                                 `• All ticket history for this bus\n\n` +
                                 `This action CANNOT be undone!\n\n` +
                                 `Are you absolutely sure you want to continue?`;
            
            if (confirm(confirmMessage)) {
                // Double confirmation for safety
                if (confirm('Final confirmation: Delete bus "' + busName + '" and ALL associated data?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                                    '<input type="hidden" name="bus_id" value="' + busId + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
    </script>
</body>
</html>
