<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

if (!function_exists('ensurePaymentMethodColumn')) {
    function ensurePaymentMethodColumn(PDO $db) {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'payment_method'");
            $colStmt->execute();
            $columnExists = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $_) {
            $columnExists = true;
        }

        if (!$columnExists) {
            try {
                $db->exec("ALTER TABLE tickets ADD COLUMN payment_method ENUM('cash','mpesa') DEFAULT 'cash' AFTER travel_time");
            } catch (Exception $_) {
                // Ignore
            }
        }
    }
}

ensurePaymentMethodColumn($db);

$ticket_id = $_GET['id'] ?? '';
$success_message = '';
$error_message = '';

if (empty($ticket_id)) {
    header('Location: manage_bookings.php');
    exit();
}

// Get ticket details
$query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
          LEFT JOIN buses b ON t.bus_id = b.id 
          WHERE t.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: manage_bookings.php');
    exit();
}

// Get routes
$query = "SELECT * FROM routes ORDER BY start_point, destination";
$stmt = $db->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get buses
$query = "SELECT * FROM buses WHERE status = 'active' ORDER BY bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available seats for the current bus
$query = "SELECT seat_no, status FROM seats WHERE bus_id = ? ORDER BY seat_no";
$stmt = $db->prepare($query);
$stmt->execute([$ticket['bus_id']]);
$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $passenger_name = trim($_POST['passenger_name']);
    $phone = trim($_POST['phone']);
    $from_route = trim($_POST['from_route']);
    $to_route = trim($_POST['to_route']);
    $bus_id = $_POST['bus_id'];
    $travel_date = $_POST['travel_date'];
    $travel_time = $_POST['travel_time'];
    $payment_method = strtolower(trim($_POST['payment_method'] ?? 'cash'));
    if (!in_array($payment_method, ['cash', 'mpesa'], true)) {
        $payment_method = 'cash';
    }
    $new_seat_no = $_POST['seat_no'];
    
    if (empty($passenger_name) || empty($phone) || empty($from_route) || empty($to_route) || 
        empty($bus_id) || empty($travel_date) || empty($travel_time) || empty($new_seat_no)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $db->beginTransaction();
            
            // Get route fare
            $query = "SELECT fare FROM routes WHERE start_point = ? AND destination = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$from_route, $to_route]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            $fare = $route['fare'] ?? $ticket['fare'];
            
            // If changing bus or seat, free up old seat first
            if ($ticket['bus_id'] != $bus_id || $ticket['seat_no'] != $new_seat_no) {
                // Free up old seat
                $query = "UPDATE seats SET status = 'available' WHERE bus_id = ? AND seat_no = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$ticket['bus_id'], $ticket['seat_no']]);
                
                // Check if new seat is available
                $query = "SELECT status FROM seats WHERE bus_id = ? AND seat_no = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$bus_id, $new_seat_no]);
                $seat = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($seat && $seat['status'] === 'booked') {
                    throw new Exception("Seat $new_seat_no is already booked.");
                }
                
                // Book new seat
                $query = "UPDATE seats SET status = 'booked' WHERE bus_id = ? AND seat_no = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$bus_id, $new_seat_no]);
            }
            
            // Detect if tickets table has payment_method column
            $hasPaymentMethod = false;
            try {
                $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'payment_method'");
                $colStmt->execute();
                $hasPaymentMethod = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $_) { /* ignore */ }
            
            // Update ticket
            if ($hasPaymentMethod) {
                $query = "UPDATE tickets SET passenger_name = ?, phone = ?, from_route = ?, to_route = ?, 
                         bus_id = ?, seat_no = ?, fare = ?, travel_date = ?, travel_time = ?, payment_method = ? 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$passenger_name, $phone, $from_route, $to_route, $bus_id, $new_seat_no, 
                               $fare, $travel_date, $travel_time, $payment_method, $ticket_id]);
            } else {
                $query = "UPDATE tickets SET passenger_name = ?, phone = ?, from_route = ?, to_route = ?, 
                         bus_id = ?, seat_no = ?, fare = ?, travel_date = ?, travel_time = ? 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$passenger_name, $phone, $from_route, $to_route, $bus_id, $new_seat_no, 
                               $fare, $travel_date, $travel_time, $ticket_id]);
            }
            
            $db->commit();
            $success_message = 'Booking updated successfully!';
            
            // Refresh ticket data
            $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
                      LEFT JOIN buses b ON t.bus_id = b.id 
                      WHERE t.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Error updating booking: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Edit Booking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Edit Booking - <?php echo htmlspecialchars($ticket['ticket_no']); ?></div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                    <a href="print_ticket.php?ticket_no=<?php echo $ticket['ticket_no']; ?>" target="_blank" 
                       style="color: #007bff; font-weight: bold; margin-left: 10px;">🖨️ Print Updated Ticket</a>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="passenger_name">Passenger Name:</label>
                            <input type="text" id="passenger_name" name="passenger_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['passenger_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="from_route">From:</label>
                            <select id="from_route" name="from_route" class="form-control" required>
                                <option value="">Select departure point</option>
                                <?php foreach ($routes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route['start_point']); ?>" 
                                        <?php echo $ticket['from_route'] === $route['start_point'] ? 'selected' : ''; ?>>
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
                                        <?php echo $ticket['to_route'] === $route['destination'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($route['destination']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="bus_id">Select Bus:</label>
                            <select id="bus_id" name="bus_id" class="form-control" required onchange="loadSeats()">
                                <option value="">Select bus</option>
                                <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo $bus['id']; ?>" 
                                        <?php echo $ticket['bus_id'] == $bus['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus['bus_name'] . ' - ' . $bus['plate_no']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="travel_date">Travel Date:</label>
                            <input type="date" id="travel_date" name="travel_date" class="form-control" 
                                   value="<?php echo $ticket['travel_date']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="travel_time">Travel Time:</label>
                            <select id="travel_time" name="travel_time" class="form-control" required>
                                <option value="">Select time</option>
                                <option value="06:00" <?php echo $ticket['travel_time'] === '06:00' ? 'selected' : ''; ?>>06:00 AM</option>
                                <option value="08:00" <?php echo $ticket['travel_time'] === '08:00' ? 'selected' : ''; ?>>08:00 AM</option>
                                <option value="10:00" <?php echo $ticket['travel_time'] === '10:00' ? 'selected' : ''; ?>>10:00 AM</option>
                                <option value="12:00" <?php echo $ticket['travel_time'] === '12:00' ? 'selected' : ''; ?>>12:00 PM</option>
                                <option value="14:00" <?php echo $ticket['travel_time'] === '14:00' ? 'selected' : ''; ?>>02:00 PM</option>
                                <option value="16:00" <?php echo $ticket['travel_time'] === '16:00' ? 'selected' : ''; ?>>04:00 PM</option>
                                <option value="18:00" <?php echo $ticket['travel_time'] === '18:00' ? 'selected' : ''; ?>>06:00 PM</option>
                                <option value="20:00" <?php echo $ticket['travel_time'] === '20:00' ? 'selected' : ''; ?>>08:00 PM</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method:</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="cash" <?php echo ($ticket['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="mpesa" <?php echo ($ticket['payment_method'] ?? '') === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="seat_no">Select Seat:</label>
                            <select id="seat_no" name="seat_no" class="form-control" required>
                                <option value="">Select seat</option>
                                <?php foreach ($seats as $seat): ?>
                                <option value="<?php echo $seat['seat_no']; ?>" 
                                        <?php echo $ticket['seat_no'] === $seat['seat_no'] ? 'selected' : ''; ?>
                                        <?php echo $seat['status'] === 'booked' && $ticket['seat_no'] !== $seat['seat_no'] ? 'disabled' : ''; ?>>
                                    <?php echo $seat['seat_no']; ?> 
                                    <?php echo $seat['status'] === 'booked' && $ticket['seat_no'] !== $seat['seat_no'] ? '(Booked)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">Update Booking</button>
                    <a href="manage_bookings.php" class="btn btn-danger btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function loadSeats() {
            const busId = document.getElementById('bus_id').value;
            if (busId) {
                fetch('get_seats.php?bus_id=' + busId)
                    .then(response => response.text())
                    .then(html => {
                        // Update seat dropdown
                        const seatSelect = document.getElementById('seat_no');
                        const currentSeat = seatSelect.value;
                        
                        // Parse the HTML to extract seat options
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        const seatElements = tempDiv.querySelectorAll('.seat');
                        
                        seatSelect.innerHTML = '<option value="">Select seat</option>';
                        
                        seatElements.forEach(seat => {
                            const option = document.createElement('option');
                            option.value = seat.dataset.seat;
                            option.textContent = seat.dataset.seat + (seat.classList.contains('booked') ? ' (Booked)' : '');
                            option.disabled = seat.classList.contains('booked');
                            
                            if (seat.dataset.seat === currentSeat) {
                                option.selected = true;
                            }
                            
                            seatSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading seats:', error);
                    });
            }
        }
    </script>
</body>
</html>
