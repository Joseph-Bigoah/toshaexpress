<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Get all buses
$query = "SELECT * FROM buses WHERE status = 'active' ORDER BY bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_bus = null;
$seats = [];
$bus_stats = [];

if (isset($_GET['bus_id']) && !empty($_GET['bus_id'])) {
    $bus_id = $_GET['bus_id'];
    
    // Get bus details
    $query = "SELECT * FROM buses WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bus_id]);
    $selected_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_bus) {
        // Get all seats for this bus
        $query = "SELECT * FROM seats WHERE bus_id = ? ORDER BY seat_no";
        $stmt = $db->prepare($query);
        $stmt->execute([$bus_id]);
        $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get seat statistics
        $query = "SELECT 
                    COUNT(*) as total_seats,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_seats,
                    SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked_seats,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_seats
                  FROM seats WHERE bus_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$bus_id]);
        $bus_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle seat operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $seat_id = $_POST['seat_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    $seat_no = $_POST['seat_no'] ?? '';
    $bus_id = $_POST['bus_id'] ?? '';
    
    if ($action === 'update_seat_status' && !empty($seat_id) && !empty($new_status)) {
        try {
            $query = "UPDATE seats SET status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $seat_id]);
            $success_message = "Seat status updated successfully!";
            
            // Refresh the page to show updated data
            header("Location: seat_management.php?bus_id=" . $_GET['bus_id']);
            exit();
        } catch (Exception $e) {
            $error_message = "Error updating seat status: " . $e->getMessage();
        }
    }
    
    if ($action === 'edit_seat' && !empty($seat_id) && !empty($seat_no)) {
        try {
            // Check if seat number already exists for this bus
            $query = "SELECT id FROM seats WHERE bus_id = ? AND seat_no = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$bus_id, $seat_no, $seat_id]);
            if ($stmt->fetch()) {
                $error_message = "Seat number already exists for this bus!";
            } else {
                $query = "UPDATE seats SET seat_no = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$seat_no, $seat_id]);
                $success_message = "Seat number updated successfully!";
                
                // Refresh the page to show updated data
                header("Location: seat_management.php?bus_id=" . $_GET['bus_id']);
                exit();
            }
        } catch (Exception $e) {
            $error_message = "Error updating seat: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete_seat' && !empty($seat_id)) {
        try {
            // Check if seat is booked
            $query = "SELECT status FROM seats WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$seat_id]);
            $seat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($seat && $seat['status'] === 'booked') {
                $error_message = "Cannot delete a booked seat! Please change status first.";
            } else {
                $query = "DELETE FROM seats WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$seat_id]);
                $success_message = "Seat deleted successfully!";
                
                // Refresh the page to show updated data
                header("Location: seat_management.php?bus_id=" . $_GET['bus_id']);
                exit();
            }
        } catch (Exception $e) {
            $error_message = "Error deleting seat: " . $e->getMessage();
        }
    }
    
    if ($action === 'add_seat' && !empty($seat_no) && !empty($bus_id)) {
        try {
            // Check if seat number already exists for this bus
            $query = "SELECT id FROM seats WHERE bus_id = ? AND seat_no = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$bus_id, $seat_no]);
            if ($stmt->fetch()) {
                $error_message = "Seat number already exists for this bus!";
            } else {
                $query = "INSERT INTO seats (bus_id, seat_no, status) VALUES (?, ?, 'available')";
                $stmt = $db->prepare($query);
                $stmt->execute([$bus_id, $seat_no]);
                $success_message = "Seat added successfully!";
                
                // Refresh the page to show updated data
                header("Location: seat_management.php?bus_id=" . $_GET['bus_id']);
                exit();
            }
        } catch (Exception $e) {
            $error_message = "Error adding seat: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Seat Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .seat-management-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .bus-selector {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .seat-map-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .bus-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .bus-card:hover {
            border-color: #1a237e;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.2);
        }
        
        .bus-card.selected {
            border-color: #1a237e;
            background: #e3f2fd;
        }
        
        .bus-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bus-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #1a237e;
        }
        
        .bus-plate {
            color: #666;
            font-size: 0.9rem;
        }
        
        .seat-map {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 20px 0;
            max-width: 400px;
        }
        
        .seat {
            width: 60px;
            height: 60px;
            border: 2px solid #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .seat.available {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }
        
        .seat.available:hover {
            background: #218838;
            transform: scale(1.05);
        }
        
        .seat.booked {
            background: #dc3545;
            color: white;
            border-color: #c82333;
            cursor: pointer;
            position: relative;
        }
        
        .seat.booked:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .seat.booked::after {
            content: "🖨️";
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 10px;
        }
        
        .seat.maintenance {
            background: #6c757d;
            color: white;
            border-color: #545b62;
        }
        
        .seat.selected {
            background: #1a237e;
            color: white;
            border-color: #0d1442;
            transform: scale(1.1);
        }
        
        .seat-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }
        
        .legend-color.available {
            background: #28a745;
            border-color: #1e7e34;
        }
        
        .legend-color.booked {
            background: #dc3545;
            border-color: #c82333;
        }
        
        .legend-color.maintenance {
            background: #6c757d;
            border-color: #545b62;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .stat-card.total { border-left-color: #007bff; }
        .stat-card.available { border-left-color: #28a745; }
        .stat-card.booked { border-left-color: #dc3545; }
        .stat-card.maintenance { border-left-color: #6c757d; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .seat-actions {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .status-selector {
            display: flex;
            gap: 10px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 8px 16px;
            border: 2px solid;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .status-btn.available {
            border-color: #28a745;
            color: #28a745;
        }
        
        .status-btn.booked {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .status-btn.maintenance {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .status-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .status-btn.selected {
            background: #1a237e;
            color: white;
            border-color: #1a237e;
        }
        
        .seat-management-tabs {
            display: flex;
            gap: 5px;
            margin: 15px 0;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #f8f9fa;
            color: #666;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: #1a237e;
            color: white;
        }
        
        .tab-btn:hover {
            background: #e9ecef;
        }
        
        .tab-btn.active:hover {
            background: #0d1442;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #1a237e;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .delete-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .add-seat-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
        
        @media (max-width: 768px) {
            .seat-management-container {
                grid-template-columns: 1fr;
            }
            
            .seat-map {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .seat {
                width: 50px;
                height: 50px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Seat Management - Bus Seat Availability</div>
            
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
            
            <div class="seat-management-container">
                <div class="bus-selector">
                    <h3>Select Bus</h3>
                    <p>Choose a bus to view and manage its seat availability</p>
                    
                    <?php foreach ($buses as $bus): ?>
                    <div class="bus-card <?php echo ($selected_bus && $selected_bus['id'] == $bus['id']) ? 'selected' : ''; ?>" 
                         onclick="selectBus(<?php echo $bus['id']; ?>)">
                        <div class="bus-info">
                            <div>
                                <div class="bus-name"><?php echo htmlspecialchars($bus['bus_name']); ?></div>
                                <div class="bus-plate"><?php echo htmlspecialchars($bus['plate_no']); ?></div>
                            </div>
                            <div>
                                <span class="badge badge-info"><?php echo ucfirst($bus['status']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="seat-map-container">
                    <?php if ($selected_bus): ?>
                        <h3>Seat Map - <?php echo htmlspecialchars($selected_bus['bus_name']); ?></h3>
                        <p>
                            Driver: <?php echo htmlspecialchars($selected_bus['driver_name']); ?> | Plate: <?php echo htmlspecialchars($selected_bus['plate_no']); ?>
                            <span style="float:right; display:flex; gap:8px;">
                                <button class="btn btn-success btn-sm" onclick="exportSeatsExcel()">📄 Export Excel</button>
                                <a href="print_all_seats.php?bus_id=<?php echo $selected_bus['id']; ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Print All Seats</a>
                            </span>
                        </p>
                        
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <div class="stat-number"><?php echo $bus_stats['total_seats']; ?></div>
                                <div class="stat-label">Total Seats</div>
                            </div>
                            <div class="stat-card available">
                                <div class="stat-number"><?php echo $bus_stats['available_seats']; ?></div>
                                <div class="stat-label">Available</div>
                            </div>
                            <div class="stat-card booked">
                                <div class="stat-number"><?php echo $bus_stats['booked_seats']; ?></div>
                                <div class="stat-label">Booked</div>
                            </div>
                            <div class="stat-card maintenance">
                                <div class="stat-number"><?php echo $bus_stats['maintenance_seats']; ?></div>
                                <div class="stat-label">Maintenance</div>
                            </div>
                        </div>
                        
                        <div class="seat-legend">
                            <div class="legend-item">
                                <div class="legend-color available"></div>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color booked"></div>
                                <span>Booked (Click to Print)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color maintenance"></div>
                                <span>Maintenance</span>
                            </div>
                        </div>
                        
                        <div class="seat-map">
                            <?php foreach ($seats as $seat): ?>
                            <div class="seat <?php echo $seat['status']; ?>" 
                                 data-seat-id="<?php echo $seat['id']; ?>"
                                 data-seat-no="<?php echo htmlspecialchars($seat['seat_no']); ?>"
                                 data-current-status="<?php echo $seat['status']; ?>"
                                 onclick="selectSeat(this)">
                                <?php echo htmlspecialchars($seat['seat_no']); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Hidden export table for Excel -->
                        <table id="seatsExportTable" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Seat No</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seats as $seat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($seat['seat_no']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($seat['status'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="seat-actions" id="seatActions" style="display: none;">
                            <h4>Seat Management</h4>
                            <p>Selected Seat: <strong id="selectedSeatNo"></strong></p>
                            <p>Current Status: <strong id="currentStatus"></strong></p>
                            
                            <div class="seat-management-tabs">
                                <button class="tab-btn active" onclick="showTab('status')">Change Status</button>
                                <button class="tab-btn" onclick="showTab('edit')">Edit Seat</button>
                                <button class="tab-btn" onclick="showTab('delete')">Delete Seat</button>
                            </div>
                            
                            <!-- Status Change Tab -->
                            <div id="statusTab" class="tab-content active">
                                <div class="status-selector">
                                    <button class="status-btn available" onclick="updateSeatStatus('available')">Available</button>
                                    <button class="status-btn booked" onclick="updateSeatStatus('booked')">Booked</button>
                                    <button class="status-btn maintenance" onclick="updateSeatStatus('maintenance')">Maintenance</button>
                                </div>
                            </div>
                            
                            <!-- Edit Seat Tab -->
                            <div id="editTab" class="tab-content">
                                <div class="form-group">
                                    <label for="editSeatNo">Seat Number:</label>
                                    <input type="text" id="editSeatNo" class="form-control" placeholder="Enter new seat number">
                                    <button class="btn btn-primary" onclick="editSeat()">Update Seat Number</button>
                                </div>
                            </div>
                            
                            <!-- Delete Seat Tab -->
                            <div id="deleteTab" class="tab-content">
                                <div class="delete-warning">
                                    <p><strong>⚠️ Warning:</strong> This will permanently delete the seat.</p>
                                    <p>Booked seats cannot be deleted. Change status first if needed.</p>
                                    <button class="btn btn-danger" onclick="deleteSeat()">Delete Seat</button>
                                </div>
                            </div>
                            
                            <!-- Hidden Forms -->
                            <form id="seatUpdateForm" method="POST" style="display: none;">
                                <input type="hidden" name="action" value="update_seat_status">
                                <input type="hidden" name="seat_id" id="seatId">
                                <input type="hidden" name="new_status" id="newStatus">
                            </form>
                            
                            <form id="seatEditForm" method="POST" style="display: none;">
                                <input type="hidden" name="action" value="edit_seat">
                                <input type="hidden" name="seat_id" id="editSeatId">
                                <input type="hidden" name="seat_no" id="editSeatNoValue">
                                <input type="hidden" name="bus_id" value="<?php echo $selected_bus['id'] ?? ''; ?>">
                            </form>
                            
                            <form id="seatDeleteForm" method="POST" style="display: none;">
                                <input type="hidden" name="action" value="delete_seat">
                                <input type="hidden" name="seat_id" id="deleteSeatId">
                            </form>
                        </div>
                        
                        <!-- Add New Seat Section -->
                        <div class="add-seat-section">
                            <h4>Add New Seat</h4>
                            <div class="form-group">
                                <input type="text" id="newSeatNo" class="form-control" placeholder="Enter seat number (e.g., A1, B2, 15)">
                                <button class="btn btn-success" onclick="addSeat()">Add Seat</button>
                            </div>
                            
                            <form id="addSeatForm" method="POST" style="display: none;">
                                <input type="hidden" name="action" value="add_seat">
                                <input type="hidden" name="seat_no" id="addSeatNoValue">
                                <input type="hidden" name="bus_id" value="<?php echo $selected_bus['id'] ?? ''; ?>">
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px; color: #666;">
                            <h3>Select a Bus</h3>
                            <p>Choose a bus from the left panel to view its seat map and manage seat availability.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedSeatElement = null;
        
        function selectBus(busId) {
            window.location.href = 'seat_management.php?bus_id=' + busId;
        }
        
        function selectSeat(seatElement) {
            // Remove previous selection
            if (selectedSeatElement) {
                selectedSeatElement.classList.remove('selected');
            }
            
            // Add selection to clicked seat
            seatElement.classList.add('selected');
            selectedSeatElement = seatElement;
            
            // Show seat actions
            document.getElementById('seatActions').style.display = 'block';
            document.getElementById('selectedSeatNo').textContent = seatElement.dataset.seatNo;
            document.getElementById('currentStatus').textContent = seatElement.dataset.currentStatus;
            document.getElementById('seatId').value = seatElement.dataset.seatId;
            
            // If seat is booked, show print option
            if (seatElement.dataset.currentStatus === 'booked') {
                showBookedSeatOptions(seatElement);
            }
        }
        
        function showBookedSeatOptions(seatElement) {
            // Create a popup for booked seat options
            const popup = document.createElement('div');
            popup.className = 'booked-seat-popup';
            popup.innerHTML = `
                <div class="popup-content">
                    <h3>Booked Seat - ${seatElement.dataset.seatNo}</h3>
                    <p>This seat is currently booked. What would you like to do?</p>
                    <div class="popup-actions">
                        <button class="btn btn-primary" onclick="printTicketForSeat('${seatElement.dataset.seatId}')">
                            🖨️ Print Ticket
                        </button>
                        <button class="btn btn-warning" onclick="viewTicketDetails('${seatElement.dataset.seatId}')">
                            👁️ View Details
                        </button>
                        <button class="btn btn-secondary" onclick="closePopup()">
                            Cancel
                        </button>
                    </div>
                </div>
            `;
            
            // Add popup styles
            const style = document.createElement('style');
            style.textContent = `
                .booked-seat-popup {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .popup-content {
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 400px;
                    width: 90%;
                }
                .popup-actions {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                    margin-top: 20px;
                    flex-wrap: wrap;
                }
                .popup-actions .btn {
                    min-width: 120px;
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(popup);
        }
        
        function printTicketForSeat(seatId) {
            // Open a blank tab synchronously to avoid popup blockers
            const printWindow = window.open('about:blank');

            // If popup was blocked, fallback to same-tab navigation later
            const popupBlocked = !printWindow || printWindow.closed || typeof printWindow.closed === 'undefined';

            // Fetch ticket details for this seat
            fetch('get_ticket_by_seat.php?seat_id=' + seatId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.ticket) {
                        const url = 'print_ticket.php?ticket_no=' + encodeURIComponent(data.ticket.ticket_no);
                        if (!popupBlocked) {
                            printWindow.location = url;
                        } else {
                            // Fallback if popup blocked
                            window.location.href = url;
                        }
                        closePopup();
                    } else {
                        if (!popupBlocked) {
                            printWindow.close();
                        }
                        alert('No ticket found for this seat');
                        closePopup();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (!popupBlocked) {
                        printWindow.close();
                    }
                    alert('Error fetching ticket details');
                    closePopup();
                });
        }
        
        function viewTicketDetails(seatId) {
            // Get ticket details for this seat
            fetch('get_ticket_by_seat.php?seat_id=' + seatId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.ticket) {
                        // Show ticket details in a modal
                        showTicketDetailsModal(data.ticket);
                        closePopup();
                    } else {
                        alert('No ticket found for this seat');
                        closePopup();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching ticket details');
                    closePopup();
                });
        }
        
        function showTicketDetailsModal(ticket) {
            const modal = document.createElement('div');
            modal.className = 'ticket-details-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Ticket Details</h3>
                        <button class="close-btn" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="ticket-info">
                            <p><strong>Ticket No:</strong> ${ticket.ticket_no}</p>
                            <p><strong>Passenger:</strong> ${ticket.passenger_name}</p>
                            <p><strong>Phone:</strong> ${ticket.phone_number}</p>
                            <p><strong>Route:</strong> ${ticket.from_route} → ${ticket.to_route}</p>
                            <p><strong>Seat:</strong> ${ticket.seat_no}</p>
                            <p><strong>Date:</strong> ${ticket.travel_date}</p>
                            <p><strong>Time:</strong> ${ticket.travel_time}</p>
                            <p><strong>Fare:</strong> KSh ${ticket.fare}</p>
                            <p><strong>Status:</strong> ${ticket.status}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="printTicket('${ticket.ticket_no}')">Print Ticket</button>
                        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    </div>
                </div>
            `;
            
            // Add modal styles
            const style = document.createElement('style');
            style.textContent = `
                .ticket-details-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1001;
                }
                .modal-content {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                }
                .close-btn {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                }
                .modal-body {
                    padding: 20px;
                }
                .ticket-info p {
                    margin: 10px 0;
                    padding: 5px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .modal-footer {
                    padding: 20px;
                    border-top: 1px solid #eee;
                    text-align: center;
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(modal);
        }
        
        function printTicket(ticketNo) {
            window.open('print_ticket.php?ticket_no=' + ticketNo, '_blank');
            closeModal();
        }
        
        function closePopup() {
            const popup = document.querySelector('.booked-seat-popup');
            if (popup) {
                popup.remove();
            }
        }
        
        function closeModal() {
            const modal = document.querySelector('.ticket-details-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        function updateSeatStatus(newStatus) {
            if (!selectedSeatElement) return;
            
            if (confirm('Are you sure you want to change this seat status to ' + newStatus + '?')) {
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('seatUpdateForm').submit();
            }
        }
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.add('active');
            event.target.classList.add('active');
            
            // Pre-fill edit form if editing
            if (tabName === 'edit' && selectedSeatElement) {
                document.getElementById('editSeatNo').value = selectedSeatElement.dataset.seatNo;
            }
        }
        
        function editSeat() {
            if (!selectedSeatElement) return;
            
            const newSeatNo = document.getElementById('editSeatNo').value.trim();
            if (!newSeatNo) {
                alert('Please enter a seat number');
                return;
            }
            
            if (confirm('Are you sure you want to change this seat number to ' + newSeatNo + '?')) {
                document.getElementById('editSeatId').value = selectedSeatElement.dataset.seatId;
                document.getElementById('editSeatNoValue').value = newSeatNo;
                document.getElementById('seatEditForm').submit();
            }
        }
        
        function deleteSeat() {
            if (!selectedSeatElement) return;
            
            if (confirm('Are you sure you want to permanently delete this seat? This action cannot be undone!')) {
                document.getElementById('deleteSeatId').value = selectedSeatElement.dataset.seatId;
                document.getElementById('seatDeleteForm').submit();
            }
        }
        
        function addSeat() {
            const seatNo = document.getElementById('newSeatNo').value.trim();
            if (!seatNo) {
                alert('Please enter a seat number');
                return;
            }
            
            if (confirm('Add new seat ' + seatNo + '?')) {
                document.getElementById('addSeatNoValue').value = seatNo;
                document.getElementById('addSeatForm').submit();
            }
        }
        
        // Auto-refresh every 30 seconds to show real-time updates
        setInterval(function() {
            if (window.location.search.includes('bus_id=')) {
                window.location.reload();
            }
        }, 30000);

        function exportSeatsExcel() {
            try {
                const table = document.getElementById('seatsExportTable').outerHTML;
                const bus = <?php echo json_encode($selected_bus ? $selected_bus['bus_name'] : 'Seats'); ?>;
                const blob = new Blob(["\uFEFF" + table], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Seats_${bus}.xls`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } catch (e) {
                alert('Failed to export Excel');
            }
        }

        // (PDF export removed by request)
    </script>
</body>
</html>
