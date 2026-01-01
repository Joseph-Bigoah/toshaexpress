<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Ensure payment columns exist in parcels table
if (!function_exists('ensureParcelPaymentColumns')) {
    function ensureParcelPaymentColumns(PDO $db) {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM parcels LIKE 'payment_method'");
            $colStmt->execute();
            $columnExists = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $_) {
            $columnExists = true;
        }

        if (!$columnExists) {
            try {
                $db->exec("ALTER TABLE parcels ADD COLUMN payment_method ENUM('cash','mpesa') DEFAULT 'cash' AFTER cost");
            } catch (Exception $_) {
                // Ignore failure
            }
        }
        
        // Check for mpesa_receipt column
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM parcels LIKE 'mpesa_receipt'");
            $colStmt->execute();
            $receiptExists = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $_) {
            $receiptExists = true;
        }

        if (!$receiptExists) {
            try {
                $db->exec("ALTER TABLE parcels ADD COLUMN mpesa_receipt VARCHAR(50) AFTER payment_method");
            } catch (Exception $_) {
                // Ignore failure
            }
        }
    }
}

ensureParcelPaymentColumns($db);

$success_message = '';
$error_message = '';

// Get routes
$query = "SELECT * FROM routes ORDER BY start_point, destination";
$stmt = $db->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $parcel_id = $_POST['parcel_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($parcel_id) && !empty($new_status)) {
            try {
                // First, try to update the database schema if needed
                $query = "ALTER TABLE parcels MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'delivered') DEFAULT 'pending'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Now update the parcel status
                $query = "UPDATE parcels SET status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$new_status, $parcel_id]);
                $success_message = "Parcel status updated to " . ucfirst($new_status) . " successfully!";
            } catch (Exception $e) {
                // If the schema update fails, try without it
                try {
                    $query = "UPDATE parcels SET status = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$new_status, $parcel_id]);
                    $success_message = "Parcel status updated to " . ucfirst($new_status) . " successfully!";
                } catch (Exception $e2) {
                    $error_message = "Error updating parcel status. Please run the database update script first: " . $e2->getMessage() . 
                                   "<br><a href='update_parcel_status.php' target='_blank'>Click here to update database schema</a>";
                }
            }
        }
    } elseif ($action === 'delete_parcel') {
        $parcel_id = $_POST['parcel_id'] ?? '';
        if (!empty($parcel_id)) {
            try {
                $query = "DELETE FROM parcels WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$parcel_id]);
                $success_message = "Parcel deleted successfully.";
            } catch (Exception $e) {
                $error_message = "Error deleting parcel: " . $e->getMessage();
            }
        }
    } elseif ($action === 'clear_all_parcels') {
        try {
            $query = "DELETE FROM parcels";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $success_message = "All parcels have been deleted successfully.";
        } catch (Exception $e) {
            $error_message = "Error clearing parcels: " . $e->getMessage();
        }
    } else {
        // Original parcel creation logic
        $sender_name = trim($_POST['sender_name']);
        $sender_phone = trim($_POST['sender_phone']);
        $receiver_name = trim($_POST['receiver_name']);
        $receiver_phone = trim($_POST['receiver_phone']);
        $from_route = trim($_POST['from_route']);
        $to_route = trim($_POST['to_route']);
        $description = trim($_POST['description']);
        $weight = floatval($_POST['weight']);
        $cost = floatval($_POST['cost']);
        $payment_method = strtolower(trim($_POST['payment_method'] ?? 'cash'));
        if (!in_array($payment_method, ['cash', 'mpesa'], true)) {
            $payment_method = 'cash';
        }
    
    if (empty($sender_name) || empty($sender_phone) || empty($receiver_name) || 
        empty($receiver_phone) || empty($from_route) || empty($to_route) || 
        empty($description) || $weight <= 0 || $cost <= 0) {
        $error_message = 'Please fill in all fields with valid values.';
    } else {
        try {
            // Check if payment_method column exists
            $hasPaymentMethod = false;
            try {
                $colStmt = $db->prepare("SHOW COLUMNS FROM parcels LIKE 'payment_method'");
                $colStmt->execute();
                $hasPaymentMethod = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $_) { /* ignore */ }
            
            // Generate parcel number
            $parcel_no = 'PK' . date('Ymd') . rand(1000, 9999);
            
            // Insert parcel
            if ($hasPaymentMethod) {
                $query = "INSERT INTO parcels (parcel_no, sender_name, sender_phone, receiver_name, 
                         receiver_phone, from_route, to_route, description, weight, cost, payment_method) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$parcel_no, $sender_name, $sender_phone, $receiver_name, 
                               $receiver_phone, $from_route, $to_route, $description, $weight, $cost, $payment_method]);
            } else {
                $query = "INSERT INTO parcels (parcel_no, sender_name, sender_phone, receiver_name, 
                         receiver_phone, from_route, to_route, description, weight, cost) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$parcel_no, $sender_name, $sender_phone, $receiver_name, 
                               $receiver_phone, $from_route, $to_route, $description, $weight, $cost]);
            }
            
            $payment_display = strtoupper($payment_method);
            $success_message = "Parcel recorded successfully! Parcel Number: $parcel_no. Payment Method: <strong>$payment_display</strong>";
            
            // Clear form
            $sender_name = $sender_phone = $receiver_name = $receiver_phone = '';
            $from_route = $to_route = $description = '';
            $weight = $cost = 0;
            $payment_method = 'cash';
            
        } catch (Exception $e) {
            $error_message = 'Error recording parcel: ' . $e->getMessage();
        }
        }
    }
}

// Get recent parcels
$query = "SELECT * FROM parcels ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Parcel Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Record New Parcel</div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php
            // Check if database supports approved status
            try {
                $query = "SHOW COLUMNS FROM parcels LIKE 'status'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $supports_approved = strpos($result['Type'], 'approved') !== false;
                
                if (!$supports_approved) {
                    echo '<div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;">';
                    echo '<h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Database Update Required</h4>';
                    echo '<p style="margin: 0 0 10px 0;">The database needs to be updated to support the "Approved" status for parcels.</p>';
                    echo '<p style="margin: 0;"><a href="fix_parcel_status.php" target="_blank" style="color: #856404; font-weight: bold;">Click here to update the database</a></p>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                // Silently ignore database check errors
            }
            ?>
            
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="color: var(--primary-blue); margin-bottom: 1rem;">Sender Information</h4>
                        
                        <div class="form-group">
                            <label for="sender_name">Sender Name:</label>
                            <input type="text" id="sender_name" name="sender_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($sender_name ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="sender_phone">Sender Phone:</label>
                            <input type="tel" id="sender_phone" name="sender_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($sender_phone ?? ''); ?>" required>
                        </div>
                        
                        <h4 style="color: var(--primary-blue); margin: 2rem 0 1rem 0;">Receiver Information</h4>
                        
                        <div class="form-group">
                            <label for="receiver_name">Receiver Name:</label>
                            <input type="text" id="receiver_name" name="receiver_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($receiver_name ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="receiver_phone">Receiver Phone:</label>
                            <input type="tel" id="receiver_phone" name="receiver_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($receiver_phone ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--primary-blue); margin-bottom: 1rem;">Parcel Details</h4>
                        
                        <div class="form-group">
                            <label for="from_route">From:</label>
                            <select id="from_route" name="from_route" class="form-control" required>
                                <option value="">Select departure point</option>
                                <?php foreach ($routes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route['start_point']); ?>" 
                                        <?php echo ($from_route ?? '') === $route['start_point'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($route['start_point']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="to_route">To:</label>
                            <select id="to_route" name="to_route" class="form-control" required>
                                <option value="">Select destination</option>
                                <?php foreach ($routes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route['destination']); ?>" 
                                        <?php echo ($to_route ?? '') === $route['destination'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($route['destination']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Parcel Description:</label>
                            <textarea id="description" name="description" class="form-control" rows="3" 
                                      placeholder="Describe the parcel contents..." required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="weight">Weight (kg):</label>
                            <input type="number" id="weight" name="weight" class="form-control" 
                                   step="0.1" min="0.1" value="<?php echo $weight ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cost">Cost (KSh):</label>
                            <input type="number" id="cost" name="cost" class="form-control" 
                                   step="0.01" min="0.01" value="<?php echo $cost ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method:</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="cash" <?php echo (($payment_method ?? 'cash') === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="mpesa" <?php echo (($payment_method ?? '') === 'mpesa') ? 'selected' : ''; ?>>M-Pesa (STK Push)</option>
                            </select>
                        </div>

                        <div id="mpesaPaymentSection" style="display: none; margin-bottom: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label for="mpesa_phone">M-Pesa Phone Number (Sender):</label>
                                <input type="tel" id="mpesa_phone" name="mpesa_phone" class="form-control" 
                                       placeholder="07XXXXXXXX or 01XXXXXXXX" 
                                       value="<?php echo htmlspecialchars($sender_phone ?? ''); ?>">
                                <small style="display:block; margin-top:0.25rem; color:#666;">
                                    Enter the sender's phone number for M-Pesa payment (07XXXXXXXX or 01XXXXXXXX)
                                </small>
                            </div>
                            <button type="button" id="mpesaButton" class="btn btn-success" style="cursor: pointer; pointer-events: auto;">
                                📲 Pay with M-Pesa
                            </button>
                            <div id="mpesaStatus" style="display:none; margin-top:0.75rem; padding:0.75rem; border-radius:5px; font-size:0.9rem;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-success btn-lg">Record Parcel</button>
                    <a href="dashboard.php" class="btn btn-danger btn-lg">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Recent Parcels
                <form method="POST" style="display:inline; float:right;" onsubmit="return confirmClearAllParcels();">
                    <input type="hidden" name="action" value="clear_all_parcels">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Clear All Parcels</button>
                </form>
                <span style="float:right; margin-right:8px; display:inline-flex; gap:6px;">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportParcelsExcel()">📄 Export Excel</button>
                    <a href="print_all_parcels.php" target="_blank" class="btn btn-primary btn-sm">🖨️ Print All Parcels</a>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Parcel No</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Route</th>
                            <th>Weight</th>
                            <th>Cost</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_parcels as $parcel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($parcel['parcel_no']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['sender_name']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['receiver_name']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['from_route'] . ' → ' . $parcel['to_route']); ?></td>
                            <td><?php echo $parcel['weight']; ?> kg</td>
                            <td>KSh <?php echo number_format($parcel['cost'], 2); ?></td>
                            <td>
                                <?php 
                                $paymentMethod = $parcel['payment_method'] ?? 'cash';
                                $mpesaReceipt = $parcel['mpesa_receipt'] ?? '';
                                ?>
                                <span class="badge badge-<?php echo $paymentMethod === 'mpesa' ? 'success' : 'secondary'; ?>">
                                    <?php echo strtoupper($paymentMethod); ?>
                                </span>
                                <?php if ($mpesaReceipt): ?>
                                    <br><small style="font-size: 0.75rem; color: #666;">Receipt: <?php echo htmlspecialchars($mpesaReceipt); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $parcel['status'] === 'delivered' ? 'success' : 
                                        ($parcel['status'] === 'in_transit' ? 'warning' : 
                                        ($parcel['status'] === 'approved' ? 'primary' : 'info')); 
                                ?>">
                                    <?php echo ucfirst($parcel['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($parcel['created_at'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-primary btn-sm" onclick="printReceipt('<?php echo $parcel['parcel_no']; ?>')">
                                        Print
                                    </button>
                                    <?php if ($parcel['status'] === 'pending'): ?>
                                        <button class="btn btn-success btn-sm" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'approved')" title="Approve Parcel">
                                            ✅ Approve
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($parcel['status'] === 'approved'): ?>
                                        <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'in_transit')" title="Mark as In Transit">
                                            🚚 In Transit
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($parcel['status'] === 'in_transit'): ?>
                                        <button class="btn btn-info btn-sm" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'delivered')" title="Mark as Delivered">
                                            📦 Delivered
                                        </button>
                                    <?php endif; ?>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            More
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'pending')">Reset to Pending</a>
                                            <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'approved')">Set to Approved</a>
                                            <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'in_transit')">Set to In Transit</a>
                                            <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $parcel['id']; ?>, 'delivered')">Set to Delivered</a>
                                        </div>
                                    </div>
                                    <form method="POST" onsubmit="return confirmDeleteParcel('<?php echo $parcel['parcel_no']; ?>');">
                                        <input type="hidden" name="action" value="delete_parcel">
                                        <input type="hidden" name="parcel_id" value="<?php echo $parcel['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Hidden export table -->
                <table id="parcelsExportTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>Parcel No</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Route</th>
                            <th>Weight</th>
                            <th>Cost</th>
                            <th>Payment Method</th>
                            <th>M-Pesa Receipt</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_parcels as $parcel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($parcel['parcel_no']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['sender_name']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['receiver_name']); ?></td>
                            <td><?php echo htmlspecialchars($parcel['from_route'] . ' → ' . $parcel['to_route']); ?></td>
                            <td><?php echo $parcel['weight']; ?> kg</td>
                            <td><?php echo number_format($parcel['cost'], 2); ?></td>
                            <td><?php echo strtoupper($parcel['payment_method'] ?? 'cash'); ?></td>
                            <td><?php echo htmlspecialchars($parcel['mpesa_receipt'] ?? ''); ?></td>
                            <td><?php echo ucfirst($parcel['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($parcel['created_at'])); ?></td>
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
        .badge-warning { background: #ffc107; color: black; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        
        .btn-group {
            display: flex;
            gap: 5px;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .dropdown-menu a {
            color: black;
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
        }
        
        .dropdown-menu a:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-toggle::after {
            content: " ▼";
            font-size: 0.7rem;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
            border: 1px solid #ffc107;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
            border: 1px solid #17a2b8;
        }
        
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
    </style>

    <script>
        function printReceipt(parcelNo) {
            // Open print receipt page
            window.open('print_parcel_receipt.php?parcel_no=' + parcelNo, '_blank');
        }
        
        function updateStatus(parcelId, newStatus) {
            if (confirm('Are you sure you want to change the status to ' + newStatus + '?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_status">' +
                                '<input type="hidden" name="parcel_id" value="' + parcelId + '">' +
                                '<input type="hidden" name="new_status" value="' + newStatus + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmDeleteParcel(parcelNo) {
            return confirm('Delete parcel ' + parcelNo + ' permanently?');
        }

        function confirmClearAllParcels() {
            return confirm('This will permanently delete ALL parcels. Continue?');
        }

        function exportParcelsExcel() {
            try {
                const table = document.getElementById('parcelsExportTable').outerHTML;
                const blob = new Blob(["\uFEFF" + table], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'Parcels.xls';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } catch (e) {
                alert('Failed to export Excel');
            }
        }

        // (PDF export removed by request)
        
        // M-Pesa payment integration
        const paymentMethodSelect = document.getElementById('payment_method');
        const mpesaSection = document.getElementById('mpesaPaymentSection');
        const mpesaStatus = document.getElementById('mpesaStatus');
        const mpesaButton = document.getElementById('mpesaButton');
        const senderPhoneInput = document.getElementById('sender_phone');
        const mpesaPhoneInput = document.getElementById('mpesa_phone');
        const costInput = document.getElementById('cost');
        
        // Sync phone fields bidirectionally
        if (senderPhoneInput && mpesaPhoneInput) {
            senderPhoneInput.addEventListener('input', function() {
                mpesaPhoneInput.value = this.value;
            });
            mpesaPhoneInput.addEventListener('input', function() {
                senderPhoneInput.value = this.value;
            });
        }
        
        // Attach click event to M-Pesa button
        if (mpesaButton) {
            mpesaButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                initiateMpesaPayment();
            });
        }
        
        function toggleMpesaSection() {
            if (paymentMethodSelect.value === 'mpesa') {
                mpesaSection.style.display = 'block';
                // Sync phone value when M-Pesa section is shown
                if (mpesaPhoneInput && senderPhoneInput) {
                    mpesaPhoneInput.value = senderPhoneInput.value;
                }
                // Ensure button is enabled and clickable
                if (mpesaButton) {
                    mpesaButton.disabled = false;
                    mpesaButton.style.cursor = 'pointer';
                    mpesaButton.style.pointerEvents = 'auto';
                }
            } else {
                mpesaSection.style.display = 'none';
                mpesaStatus.style.display = 'none';
                mpesaStatus.textContent = '';
            }
        }
        paymentMethodSelect.addEventListener('change', toggleMpesaSection);
        toggleMpesaSection();
        
        async function initiateMpesaPayment() {
            // Ensure button and status elements exist
            if (!mpesaButton || !mpesaStatus) {
                console.error('M-Pesa button or status element not found');
                alert('Error: M-Pesa payment elements not found. Please refresh the page.');
                return;
            }
            
            // Use M-Pesa phone field if available and has value, otherwise fall back to sender phone field
            const mpesaPhoneField = document.getElementById('mpesa_phone');
            const mainPhoneField = document.getElementById('sender_phone');
            let phoneInput = mainPhoneField;
            
            if (mpesaPhoneField && mpesaPhoneField.value && mpesaPhoneField.value.trim()) {
                phoneInput = mpesaPhoneField;
            } else if (mainPhoneField && mainPhoneField.value && mainPhoneField.value.trim()) {
                phoneInput = mainPhoneField;
                // Sync to M-Pesa field if it exists
                if (mpesaPhoneField) {
                    mpesaPhoneField.value = mainPhoneField.value;
                }
            }
            
            const senderNameInput = document.getElementById('sender_name');
            const fromRoute = document.getElementById('from_route').value;
            const toRoute = document.getElementById('to_route').value;
            const cost = parseFloat(costInput.value || '0');
            
            // Get phone number and clean it
            let phone = phoneInput ? phoneInput.value.trim() : '';
            // Remove spaces and other formatting characters
            phone = phone.replace(/[\s\-\(\)]/g, '');
            // Validate Kenyan phone number
            const phonePattern = /^(?:\+?254[17]|0[17])\d{8}$/;
            
            // Sync both fields with the cleaned value for display
            if (mpesaPhoneField && mainPhoneField && phoneInput) {
                const displayValue = phoneInput.value.trim();
                mpesaPhoneField.value = displayValue;
                mainPhoneField.value = displayValue;
            }
            
            // Validate phone number
            if (!phone) {
                mpesaStatus.style.display = 'block';
                mpesaStatus.style.background = '#f8d7da';
                mpesaStatus.style.border = '1px solid #f5c2c7';
                mpesaStatus.style.color = '#842029';
                mpesaStatus.textContent = 'Please enter a phone number.';
                return;
            }
            
            if (!phonePattern.test(phone)) {
                mpesaStatus.style.display = 'block';
                mpesaStatus.style.background = '#f8d7da';
                mpesaStatus.style.border = '1px solid #f5c2c7';
                mpesaStatus.style.color = '#842029';
                mpesaStatus.textContent = 'Invalid phone number format. Please use: 07XXXXXXXX, 01XXXXXXXX, +2547XXXXXXXX, or +2541XXXXXXXX.';
                return;
            }
            
            // Validate other required fields
            if (!fromRoute || !toRoute || cost <= 0) {
                mpesaStatus.style.display = 'block';
                mpesaStatus.style.background = '#fff3cd';
                mpesaStatus.style.border = '1px solid #ffecb5';
                mpesaStatus.style.color = '#664d03';
                mpesaStatus.textContent = 'Please fill in all required fields and enter a valid cost before initiating payment.';
                return;
            }
            
            // Show loading status
            mpesaStatus.style.display = 'block';
            mpesaStatus.style.background = '#cff4fc';
            mpesaStatus.style.border = '1px solid #b6effb';
            mpesaStatus.style.color = '#055160';
            mpesaStatus.textContent = 'Sending M-Pesa STK push to ' + phone + '...';
            mpesaButton.disabled = true;
            mpesaButton.style.cursor = 'not-allowed';
            mpesaButton.style.opacity = '0.6';
            
            try {
                const response = await fetch('initiate_mpesa_parcel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: phone,
                        amount: cost,
                        sender_name: senderNameInput ? senderNameInput.value.trim() : '',
                        route: `${fromRoute} → ${toRoute}`
                    })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    mpesaStatus.style.background = '#d1e7dd';
                    mpesaStatus.style.border = '1px solid #badbcc';
                    mpesaStatus.style.color = '#0f5132';
                    mpesaStatus.textContent = result.message || 'M-Pesa prompt sent to ' + phone + '. Awaiting customer confirmation.';
                } else {
                    throw new Error(result.message || 'Failed to initiate M-Pesa payment.');
                }
            } catch (error) {
                console.error('M-Pesa initiation error:', error);
                mpesaStatus.style.background = '#f8d7da';
                mpesaStatus.style.border = '1px solid #f5c2c7';
                mpesaStatus.style.color = '#842029';
                mpesaStatus.textContent = 'Error: ' + (error.message || 'Failed to initiate M-Pesa payment. Please check your connection and try again.');
            } finally {
                mpesaButton.disabled = false;
                mpesaButton.style.cursor = 'pointer';
                mpesaButton.style.opacity = '1';
            }
        }
    </script>
</body>
</html>
