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
            $columnExists = true; // Avoid repeated attempts if SHOW fails
        }

        if (!$columnExists) {
            try {
                $db->exec("ALTER TABLE tickets ADD COLUMN payment_method ENUM('cash','mpesa') DEFAULT 'cash' AFTER travel_time");
            } catch (Exception $_) {
                // Ignore failure; legacy schema continues to function
            }
        }
    }
}

ensurePaymentMethodColumn($db);

$success_message = '';
$error_message = '';

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
    $selected_seats = $_POST['selected_seats'] ?? [];
    
    if (empty($passenger_name) || empty($phone) || empty($from_route) || empty($to_route) || 
        empty($bus_id) || empty($travel_date) || empty($travel_time) || empty($selected_seats)) {
        $error_message = 'Please fill in all fields and select at least one seat.';
    } else {
        // Validate that selected seats are actually available
        $available_seats = [];
        $query = "SELECT seat_no FROM seats WHERE bus_id = ? AND status = 'available'";
        $stmt = $db->prepare($query);
        $stmt->execute([$bus_id]);
        $available_seats = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'seat_no');
        
        $invalid_seats = array_diff($selected_seats, $available_seats);
        if (!empty($invalid_seats)) {
            $error_message = 'Some selected seats are no longer available: ' . implode(', ', $invalid_seats);
        } else {
            try {
                $db->beginTransaction();
                
                // Get route fare
                $query = "SELECT fare FROM routes WHERE start_point = ? AND destination = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$from_route, $to_route]);
                $route = $stmt->fetch(PDO::FETCH_ASSOC);
                $fare = $route['fare'] ?? 0;
                
                $booked_tickets = [];
                
                // Detect if tickets table has seat_id column
                $hasSeatId = false;
                try {
                    $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'seat_id'");
                    $colStmt->execute();
                    $hasSeatId = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $_) { /* ignore */ }
                
                // Detect if tickets table has payment_method column
                $hasPaymentMethod = false;
                try {
                    $colStmt = $db->prepare("SHOW COLUMNS FROM tickets LIKE 'payment_method'");
                    $colStmt->execute();
                    $hasPaymentMethod = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $_) { /* ignore */ }

                foreach ($selected_seats as $seat_no) {
                    // Check if seat is available
                    $query = "SELECT status FROM seats WHERE bus_id = ? AND seat_no = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id, $seat_no]);
                    $seat = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($seat && $seat['status'] === 'booked') {
                        throw new Exception("Seat $seat_no is already booked.");
                    }
                    
                    // Generate unique ticket number
                    do {
                        $ticket_no = 'TK' . date('Ymd') . rand(1000, 9999);
                        $query = "SELECT id FROM tickets WHERE ticket_no = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$ticket_no]);
                    } while ($stmt->rowCount() > 0);
                    
                    if ($hasSeatId) {
                        // Get seat_id for the seat
                        $query = "SELECT id FROM seats WHERE bus_id = ? AND seat_no = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$bus_id, $seat_no]);
                        $seat_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        $seat_id = $seat_data['id'];

                        if ($hasPaymentMethod) {
                            // Insert ticket including seat_id and payment_method
                            $query = "INSERT INTO tickets (ticket_no, passenger_name, phone, from_route, to_route, 
                                     bus_id, seat_no, seat_id, fare, booking_date, booking_time, travel_date, travel_time, payment_method) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$ticket_no, $passenger_name, $phone, $from_route, $to_route, 
                                           $bus_id, $seat_no, $seat_id, $fare, $travel_date, $travel_time, $payment_method]);
                        } else {
                            // Insert ticket including seat_id but without payment_method (legacy schema)
                            $query = "INSERT INTO tickets (ticket_no, passenger_name, phone, from_route, to_route, 
                                     bus_id, seat_no, seat_id, fare, booking_date, booking_time, travel_date, travel_time) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$ticket_no, $passenger_name, $phone, $from_route, $to_route, 
                                           $bus_id, $seat_no, $seat_id, $fare, $travel_date, $travel_time]);
                        }
                    } else {
                        if ($hasPaymentMethod) {
                            // Insert ticket without seat_id but with payment_method
                            $query = "INSERT INTO tickets (ticket_no, passenger_name, phone, from_route, to_route, 
                                     bus_id, seat_no, fare, booking_date, booking_time, travel_date, travel_time, payment_method) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$ticket_no, $passenger_name, $phone, $from_route, $to_route, 
                                           $bus_id, $seat_no, $fare, $travel_date, $travel_time, $payment_method]);
                        } else {
                            // Insert ticket without seat_id and without payment_method (legacy schema)
                            $query = "INSERT INTO tickets (ticket_no, passenger_name, phone, from_route, to_route, 
                                     bus_id, seat_no, fare, booking_date, booking_time, travel_date, travel_time) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$ticket_no, $passenger_name, $phone, $from_route, $to_route, 
                                           $bus_id, $seat_no, $fare, $travel_date, $travel_time]);
                        }
                    }
                    
                    // Update seat status
                    $query = "UPDATE seats SET status = 'booked' WHERE bus_id = ? AND seat_no = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bus_id, $seat_no]);
                    
                    $booked_tickets[] = $ticket_no;
                }
                
                $db->commit();
                
                // Display success message with payment method
                $ticket_count = count($booked_tickets);
                $payment_display = strtoupper(htmlspecialchars($payment_method));
                $success_message = "Ticket(s) booked successfully! Payment Method: <strong>$payment_display</strong>";
                
                // Clear form
                $passenger_name = $phone = $from_route = $to_route = $travel_date = $travel_time = '';
                $selected_seats = [];
                $payment_method = 'cash'; // Reset payment method to default
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = $e->getMessage();
            }
        }
    }
}

// Get selected bus for seat map
$selected_bus_id = $_POST['bus_id'] ?? '';
$seats = [];
if ($selected_bus_id) {
    $query = "SELECT seat_no, status FROM seats WHERE bus_id = ? ORDER BY seat_no";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_bus_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Ticket Booking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Book New Ticket</div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="bookingForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="passenger_name">Passenger Name:</label>
                            <input type="text" id="passenger_name" name="passenger_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($passenger_name ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                        </div>
                        
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
                            <label for="bus_id">Select Bus:</label>
                            <select id="bus_id" name="bus_id" class="form-control" required onchange="loadSeats()">
                                <option value="">Select bus</option>
                                <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo $bus['id']; ?>" 
                                        <?php echo ($selected_bus_id == $bus['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus['bus_name'] . ' - ' . $bus['plate_no']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="travel_date">Travel Date:</label>
                            <input type="date" id="travel_date" name="travel_date" class="form-control" 
                                   value="<?php echo $travel_date ?? date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="travel_time">Travel Time:</label>
                            <select id="travel_time" name="travel_time" class="form-control" required>
                                <option value="">Select time</option>
                                <option value="06:00">06:00 AM</option>
                                <option value="08:00">08:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="14:00">02:00 PM</option>
                                <option value="16:00">04:00 PM</option>
                                <option value="18:00">06:00 PM</option>
                                <option value="20:00">08:00 PM</option>
                            </select>
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
                                <label for="mpesa_phone">M-Pesa Phone Number:</label>
                                <input type="tel" id="mpesa_phone" name="mpesa_phone" class="form-control" 
                                       placeholder="07XXXXXXXX or 01XXXXXXXX" 
                                       value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                <small style="display:block; margin-top:0.25rem; color:#666;">
                                    Enter the customer's phone number for M-Pesa payment (07XXXXXXXX or 01XXXXXXXX)
                                </small>
                            </div>
                            <button type="button" id="mpesaButton" class="btn btn-success" style="cursor: pointer; pointer-events: auto;">
                                📲 Pay with M-Pesa
                            </button>
                            <div id="mpesaStatus" style="display:none; margin-top:0.75rem; padding:0.75rem; border-radius:5px; font-size:0.9rem;"></div>
                        </div>
                    </div>
                    <input type="hidden" id="total_fare_input" name="total_fare" value="<?php echo isset($_POST['total_fare']) ? htmlspecialchars($_POST['total_fare']) : '0.00'; ?>">
                    
                    <div>
                        <div class="form-group">
                            <label>Select Seats:</label>
                            <div id="seatMap" class="seat-map">
                                <?php if ($selected_bus_id && !empty($seats)): ?>
                                    <?php foreach ($seats as $seat): ?>
                                    <div class="seat <?php echo $seat['status']; ?>" 
                                         data-seat="<?php echo $seat['seat_no']; ?>"
                                         onclick="toggleSeat(this)">
                                        <?php echo $seat['seat_no']; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: #666; grid-column: 1/-1;">
                                        Please select a bus to view seat map
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div id="selectedSeats" style="margin-top: 1rem;">
                                <strong>Selected Seats: </strong>
                                <span id="selectedSeatsList">None</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Fare Information:</label>
                            <div id="fareInfo" style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                <p><strong>Route:</strong> <span id="routeInfo">-</span></p>
                                <p><strong>Fare per seat:</strong> KSh <span id="fareAmount">0.00</span></p>
                                <p><strong>Total fare:</strong> KSh <span id="totalFare">0.00</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">Book Ticket(s)</button>
                    <a href="dashboard.php" class="btn btn-danger btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedSeats = [];

        const paymentMethodSelect = document.getElementById('payment_method');
        const mpesaSection = document.getElementById('mpesaPaymentSection');
        const mpesaStatus = document.getElementById('mpesaStatus');
        const mpesaButton = document.getElementById('mpesaButton');
        const totalFareInput = document.getElementById('total_fare_input');
        const phoneInput = document.getElementById('phone');
        const mpesaPhoneInput = document.getElementById('mpesa_phone');
        
        // Sync phone fields bidirectionally
        if (phoneInput && mpesaPhoneInput) {
            phoneInput.addEventListener('input', function() {
                mpesaPhoneInput.value = this.value;
            });
            mpesaPhoneInput.addEventListener('input', function() {
                phoneInput.value = this.value;
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
                if (mpesaPhoneInput && phoneInput) {
                    mpesaPhoneInput.value = phoneInput.value;
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
        
        function loadSeats() {
            const busId = document.getElementById('bus_id').value;
            if (busId) {
                // Load seats via AJAX
                fetch('get_seats.php?bus_id=' + busId)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('seatMap').innerHTML = html;
                        selectedSeats = [];
                        updateSelectedSeats();
                        updateFare();
                    })
                    .catch(error => {
                        console.error('Error loading seats:', error);
                        document.getElementById('seatMap').innerHTML = '<p style="text-align: center; color: #666; grid-column: 1/-1;">Error loading seats. Please try again.</p>';
                    });
            } else {
                document.getElementById('seatMap').innerHTML = '<p style="text-align: center; color: #666; grid-column: 1/-1;">Please select a bus to view seat map</p>';
                selectedSeats = [];
                updateSelectedSeats();
                updateFare();
            }
        }
        
        function toggleSeat(seatElement) {
            const seatNo = seatElement.dataset.seat;
            
            if (seatElement.classList.contains('booked')) {
                return; // Can't select booked seats
            }
            
            if (seatElement.classList.contains('selected')) {
                // Deselect seat
                seatElement.classList.remove('selected');
                selectedSeats = selectedSeats.filter(seat => seat !== seatNo);
            } else {
                // Select seat
                seatElement.classList.add('selected');
                selectedSeats.push(seatNo);
            }
            
            updateSelectedSeats();
            updateFare();
        }
        
        function updateSelectedSeats() {
            const selectedSeatsList = document.getElementById('selectedSeatsList');
            if (selectedSeats.length > 0) {
                selectedSeatsList.textContent = selectedSeats.join(', ');
            } else {
                selectedSeatsList.textContent = 'None';
            }
            
            // Add hidden inputs for selected seats
            const existingInputs = document.querySelectorAll('input[name="selected_seats[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedSeats.forEach(seat => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_seats[]';
                input.value = seat;
                document.getElementById('bookingForm').appendChild(input);
            });
        }
        
        function updateFare() {
            const fromRoute = document.getElementById('from_route').value;
            const toRoute = document.getElementById('to_route').value;
            
            if (fromRoute && toRoute) {
                // Fetch fare from server
                fetch('get_fare.php?from=' + encodeURIComponent(fromRoute) + '&to=' + encodeURIComponent(toRoute))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const baseFare = parseFloat(data.fare);
                            const totalFare = baseFare * selectedSeats.length;
                            
                            document.getElementById('routeInfo').textContent = fromRoute + ' → ' + toRoute;
                            document.getElementById('fareAmount').textContent = baseFare.toFixed(2);
                            document.getElementById('totalFare').textContent = totalFare.toFixed(2);
                            totalFareInput.value = totalFare.toFixed(2);
                        } else {
                            // Fallback to default fare
                            const baseFare = 1500;
                            const totalFare = baseFare * selectedSeats.length;
                            
                            document.getElementById('routeInfo').textContent = fromRoute + ' → ' + toRoute;
                            document.getElementById('fareAmount').textContent = baseFare.toFixed(2);
                            document.getElementById('totalFare').textContent = totalFare.toFixed(2);
                            totalFareInput.value = totalFare.toFixed(2);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching fare:', error);
                        // Fallback to default fare
                        const baseFare = 1500;
                        const totalFare = baseFare * selectedSeats.length;
                        
                        document.getElementById('routeInfo').textContent = fromRoute + ' → ' + toRoute;
                        document.getElementById('fareAmount').textContent = baseFare.toFixed(2);
                        document.getElementById('totalFare').textContent = totalFare.toFixed(2);
                        totalFareInput.value = totalFare.toFixed(2);
                    });
            } else {
                document.getElementById('routeInfo').textContent = '-';
                document.getElementById('fareAmount').textContent = '0.00';
                document.getElementById('totalFare').textContent = '0.00';
                totalFareInput.value = '0.00';
            }
        }
        
        // Update fare when route changes
        document.getElementById('from_route').addEventListener('change', updateFare);
        document.getElementById('to_route').addEventListener('change', updateFare);

        async function initiateMpesaPayment() {
            // Ensure button and status elements exist
            if (!mpesaButton || !mpesaStatus) {
                console.error('M-Pesa button or status element not found');
                alert('Error: M-Pesa payment elements not found. Please refresh the page.');
                return;
            }
            
            // Use M-Pesa phone field if available and has value, otherwise fall back to main phone field
            const mpesaPhoneField = document.getElementById('mpesa_phone');
            const mainPhoneField = document.getElementById('phone');
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
            
            const passengerNameInput = document.getElementById('passenger_name');
            const fromRoute = document.getElementById('from_route').value;
            const toRoute = document.getElementById('to_route').value;
            const totalFare = parseFloat(totalFareInput.value || '0');
            
            // Get phone number and clean it
            let phone = phoneInput ? phoneInput.value.trim() : '';
            // Remove spaces and other formatting characters, but keep + and digits
            phone = phone.replace(/[\s\-\(\)]/g, '');
            // Validate Kenyan phone number: accepts 07XXXXXXXX or 01XXXXXXXX
            // Formats: 07XXXXXXXX, 01XXXXXXXX, +2547XXXXXXXX, +2541XXXXXXXX, 2547XXXXXXXX, 2541XXXXXXXX
            // Pattern: 0 followed by 7 or 1, then 8 digits (local) OR 254 followed by 7 or 1, then 8 digits (international)
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
            if (!fromRoute || !toRoute || selectedSeats.length === 0 || totalFare <= 0) {
                mpesaStatus.style.display = 'block';
                mpesaStatus.style.background = '#fff3cd';
                mpesaStatus.style.border = '1px solid #ffecb5';
                mpesaStatus.style.color = '#664d03';
                mpesaStatus.textContent = 'Please select route and at least one seat before initiating payment.';
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
                const response = await fetch('initiate_mpesa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: phone,
                        amount: totalFare,
                        passenger_name: passengerNameInput ? passengerNameInput.value.trim() : '',
                        route: `${fromRoute} → ${toRoute}`,
                        seats: selectedSeats
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
