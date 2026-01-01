<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = $_POST['ticket_id'] ?? '';
    
    if ($action === 'cancel' && !empty($ticket_id)) {
        try {
            $db->beginTransaction();
            
            // Get ticket details
            $query = "SELECT * FROM tickets WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                throw new Exception("Ticket not found.");
            }
            
            // Check if ticket can be cancelled (not for past dates)
            if (strtotime($ticket['travel_date']) < strtotime(date('Y-m-d'))) {
                throw new Exception("Cannot cancel ticket for past travel date.");
            }
            
            // Update ticket status to cancelled
            $query = "UPDATE tickets SET status = 'cancelled' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket_id]);
            
            // Free up the seat
            $query = "UPDATE seats SET status = 'available' WHERE bus_id = ? AND seat_no = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket['bus_id'], $ticket['seat_no']]);
            
            $db->commit();
            $success_message = "Ticket " . $ticket['ticket_no'] . " has been cancelled and seat " . $ticket['seat_no'] . " is now available.";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error cancelling ticket: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && !empty($ticket_id)) {
        // Redirect to edit page
        header('Location: edit_booking.php?id=' . $ticket_id);
        exit();
    } elseif ($action === 'delete' && !empty($ticket_id)) {
        try {
            $db->beginTransaction();

            // Get ticket details
            $query = "SELECT * FROM tickets WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                throw new Exception("Ticket not found.");
            }

            // Free up the seat (best-effort)
            $query = "UPDATE seats SET status = 'available' WHERE bus_id = ? AND seat_no = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket['bus_id'], $ticket['seat_no']]);

            // Delete the ticket permanently
            $query = "DELETE FROM tickets WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$ticket_id]);

            $db->commit();
            $success_message = "Ticket " . $ticket['ticket_no'] . " has been deleted and seat " . $ticket['seat_no'] . " is now available.";

        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error deleting ticket: " . $e->getMessage();
        }
    } elseif ($action === 'clear_all') {
        // Bulk delete all tickets matching provided filters
        try {
            $db->beginTransaction();

            // Build where from posted filters
            $bulkWhere = [];
            $bulkParams = [];

            $p_search = trim($_POST['search'] ?? '');
            if ($p_search !== '') {
                $bulkWhere[] = "(t.ticket_no LIKE ? OR t.passenger_name LIKE ? OR t.phone LIKE ?)";
                $like = "%$p_search%";
                array_push($bulkParams, $like, $like, $like);
            }

            $p_status = $_POST['status'] ?? '';
            if ($p_status === 'confirmed' || $p_status === 'cancelled') {
                $bulkWhere[] = "t.status = ?";
                $bulkParams[] = $p_status;
            }

            $p_date_from = $_POST['date_from'] ?? '';
            if ($p_date_from !== '') {
                $bulkWhere[] = "t.travel_date >= ?";
                $bulkParams[] = $p_date_from;
            }

            $p_date_to = $_POST['date_to'] ?? '';
            if ($p_date_to !== '') {
                $bulkWhere[] = "t.travel_date <= ?";
                $bulkParams[] = $p_date_to;
            }

            $bulkWhereSQL = '';
            if (!empty($bulkWhere)) {
                $bulkWhereSQL = 'WHERE ' . implode(' AND ', $bulkWhere);
            }

            // Fetch tickets to delete
            $sel = $db->prepare("SELECT t.id, t.ticket_no, t.bus_id, t.seat_no FROM tickets t $bulkWhereSQL");
            $sel->execute($bulkParams);
            $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $db->rollBack();
                $error_message = 'No bookings match the current filters to clear.';
            } else {
                // Free seats and delete tickets
                $freeStmt = $db->prepare("UPDATE seats SET status = 'available' WHERE bus_id = ? AND seat_no = ?");
                $delStmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
                foreach ($rows as $r) {
                    // Best-effort free
                    $freeStmt->execute([$r['bus_id'], $r['seat_no']]);
                    $delStmt->execute([$r['id']]);
                }
                $db->commit();
                $success_message = count($rows) . ' booking(s) deleted and seats freed.';
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $error_message = 'Error clearing bookings: ' . $e->getMessage();
        }
    }
}

// Get all bookings with pagination and filters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$whereParams = [];

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $whereClauses[] = "(t.ticket_no LIKE ? OR t.passenger_name LIKE ? OR t.phone LIKE ?)";
    $like = "%$search%";
    array_push($whereParams, $like, $like, $like);
}

$status = $_GET['status'] ?? '';
if ($status === 'confirmed' || $status === 'cancelled') {
    $whereClauses[] = "t.status = ?";
    $whereParams[] = $status;
}

$date_from = $_GET['date_from'] ?? '';
if ($date_from !== '') {
    $whereClauses[] = "t.travel_date >= ?";
    $whereParams[] = $date_from;
}

$date_to = $_GET['date_to'] ?? '';
if ($date_to !== '') {
    $whereClauses[] = "t.travel_date <= ?";
    $whereParams[] = $date_to;
}

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Main query (note: LIMIT/OFFSET interpolated as integers to avoid PDO LIMIT binding issues)
$query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t ";
$query .= "LEFT JOIN buses b ON t.bus_id = b.id ";
$query .= $whereSQL . " ";
$query .= "ORDER BY t.created_at DESC ";
$query .= "LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $db->prepare($query);
$stmt->execute($whereParams);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count for pagination
$countQuery = "SELECT COUNT(*) as total FROM tickets t " . $whereSQL;
$stmt = $db->prepare($countQuery);
$stmt->execute($whereParams);
$total_bookings = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_bookings / $limit));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Manage Bookings</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .booking-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #e3f2fd;
            color: #0d1442;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .pagination a {
            padding: 8px 16px;
            margin: 0 4px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: #007bff;
            border-radius: 4px;
        }
        
        .pagination a:hover {
            background: #e9ecef;
        }
        
        .pagination .current {
            background: #007bff;
            color: white;
        }
        
        .search-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Manage Bookings - Cancel & Edit</div>
            
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
            
            <div class="search-filters">
                <h4>Search & Filter Bookings</h4>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label for="search">Search:</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Ticket No, Passenger Name, Phone" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="confirmed" <?php echo ($_GET['status'] ?? '') === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">From Date:</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date:</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group" style="text-align: center;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="manage_bookings.php" class="btn btn-danger">Clear</a>
                        <!-- Bulk Clear All (Filtered) -->
                        <form method="POST" style="display:inline; margin-left:10px;" onsubmit="return confirmBulkClear();">
                            <input type="hidden" name="action" value="clear_all">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>">
                            <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                            <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Clear All (Filtered)</button>
                        </form>
                        <!-- Bulk Clear All (All Bookings) -->
                        <form method="POST" style="display:inline; margin-left:10px;" onsubmit="return confirmBulkClearAll();">
                            <input type="hidden" name="action" value="clear_all">
                            <button type="submit" class="btn btn-danger">🗑️ Clear All (All Bookings)</button>
                        </form>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket No</th>
                            <th>Passenger</th>
                            <th>Phone</th>
                            <th>Route</th>
                            <th>Bus</th>
                            <th>Seat</th>
                            <th>Travel Date</th>
                            <th>Fare</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['ticket_no']); ?></td>
                            <td><?php echo htmlspecialchars($booking['passenger_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars($booking['from_route'] . ' → ' . $booking['to_route']); ?></td>
                            <td><?php echo htmlspecialchars($booking['bus_name'] . ' (' . $booking['plate_no'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($booking['seat_no']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                            <td>KSh <?php echo number_format($booking['fare'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="booking-actions">
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <a href="print_ticket.php?ticket_no=<?php echo $booking['ticket_no']; ?>" 
                                           target="_blank" class="btn btn-primary btn-sm">Print</a>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="editBooking(<?php echo $booking['id']; ?>)">Edit</button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="cancelBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['ticket_no']); ?>')">Cancel</button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['ticket_no']); ?>')">Delete</button>
                                    <?php else: ?>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['ticket_no']); ?>')">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Booking Confirmation Modal -->
    <div id="cancelModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%;">
            <h3>Cancel Booking</h3>
            <p>Are you sure you want to cancel this booking?</p>
            <p><strong>Ticket No:</strong> <span id="cancelTicketNo"></span></p>
            <p><strong>Note:</strong> The seat will be made available for rebooking.</p>
            <div style="text-align: center; margin-top: 20px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="ticket_id" id="cancelTicketId">
                    <button type="submit" class="btn btn-danger">Yes, Cancel Booking</button>
                </form>
                <button onclick="closeCancelModal()" class="btn btn-primary" style="margin-left: 10px;">No, Keep Booking</button>
            </div>
        </div>
    </div>

    <!-- Delete Booking Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 420px; width: 90%;">
            <h3>Delete Booking</h3>
            <p>Are you absolutely sure you want to permanently delete this booking?</p>
            <p><strong>Ticket No:</strong> <span id="deleteTicketNo"></span></p>
            <p style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 6px;">This action cannot be undone. The seat will be made available.</p>
            <div style="text-align: center; margin-top: 20px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ticket_id" id="deleteTicketId">
                    <button type="submit" class="btn btn-danger">Yes, Delete Permanently</button>
                </form>
                <button onclick="closeDeleteModal()" class="btn btn-primary" style="margin-left: 10px;">No, Keep</button>
            </div>
        </div>
    </div>

    <script>
        function editBooking(ticketId) {
            if (confirm('Edit this booking? You will be redirected to the edit page.')) {
                window.location.href = 'edit_booking.php?id=' + ticketId;
            }
        }
        
        function cancelBooking(ticketId, ticketNo) {
            document.getElementById('cancelTicketId').value = ticketId;
            document.getElementById('cancelTicketNo').textContent = ticketNo;
            document.getElementById('cancelModal').style.display = 'block';
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        function deleteBooking(ticketId, ticketNo) {
            document.getElementById('deleteTicketId').value = ticketId;
            document.getElementById('deleteTicketNo').textContent = ticketNo;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        function confirmBulkClear() {
            return confirm('This will permanently delete ALL bookings matching the current filters and free their seats. Continue?');
        }

        function confirmBulkClearAll() {
            return confirm('DANGER: This will permanently delete ALL bookings in the system and free their seats. This cannot be undone. Continue?');
        }
    </script>
</body>
</html>
