<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle bulk print requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'print_filtered') {
        $filters = [
            'bus_id' => $_POST['bus_id'] ?? '',
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? '',
            'status' => $_POST['status'] ?? '',
            'route' => $_POST['route'] ?? ''
        ];
        
        // Build query based on filters
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['bus_id'])) {
            $where_conditions[] = "t.bus_id = ?";
            $params[] = $filters['bus_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "t.travel_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "t.travel_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['route'])) {
            $where_conditions[] = "(t.from_route LIKE ? OR t.to_route LIKE ?)";
            $params[] = '%' . $filters['route'] . '%';
            $params[] = '%' . $filters['route'] . '%';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                  FROM tickets t 
                  LEFT JOIN buses b ON t.bus_id = b.id 
                  $where_clause 
                  ORDER BY t.travel_date, t.travel_time, t.seat_no";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tickets)) {
            $error_message = 'No tickets found matching the selected criteria.';
        } else {
            // Redirect to print page with ticket data
            $ticket_ids = array_column($tickets, 'id');
            header("Location: print_bulk_tickets.php?ticket_ids=" . implode(',', $ticket_ids));
            exit();
        }
    }
}

// Get all buses for filter dropdown
$query = "SELECT * FROM buses WHERE status = 'active' ORDER BY bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent tickets for preview
$query = "SELECT t.*, b.bus_name FROM tickets t 
          LEFT JOIN buses b ON t.bus_id = b.id 
          ORDER BY t.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Bulk Ticket Printing</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .bulk-print-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .preview-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #1a237e;
        }
        
        .print-actions {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .btn-bulk {
            padding: 12px 24px;
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
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        
        .ticket-preview {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }
        
        .ticket-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .ticket-item:hover {
            background: #f8f9fa;
        }
        
        .ticket-info {
            flex: 1;
        }
        
        .ticket-no {
            font-weight: bold;
            color: #1a237e;
        }
        
        .ticket-details {
            font-size: 0.9rem;
            color: #666;
        }
        
        .ticket-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .quick-print-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quick-print-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .quick-print-btn {
            padding: 15px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .quick-print-btn:hover {
            border-color: #1a237e;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.2);
        }
        
        .quick-print-btn h4 {
            margin: 0 0 10px 0;
            color: #1a237e;
        }
        
        .quick-print-btn p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .bulk-print-container {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .print-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">🖨️ Bulk Ticket Printing</div>
            
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
            
            <!-- Quick Print Options -->
            <div class="quick-print-section">
                <h3>Quick Print Options</h3>
                <div class="quick-print-grid">
                    <div class="quick-print-btn" onclick="quickPrint('today')">
                        <h4>📅 Today's Tickets</h4>
                        <p>Print all tickets for today</p>
                    </div>
                    <div class="quick-print-btn" onclick="quickPrint('tomorrow')">
                        <h4>📅 Tomorrow's Tickets</h4>
                        <p>Print all tickets for tomorrow</p>
                    </div>
                    <div class="quick-print-btn" onclick="quickPrint('confirmed')">
                        <h4>✅ Confirmed Only</h4>
                        <p>Print only confirmed tickets</p>
                    </div>
                    <div class="quick-print-btn" onclick="quickPrint('all')">
                        <h4>📋 All Tickets</h4>
                        <p>Print all tickets in system</p>
                    </div>
                </div>
            </div>
            
            <div class="bulk-print-container">
                <!-- Filter Section -->
                <div class="filter-section">
                    <h3>Filter & Print Tickets</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="print_filtered">
                        
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="bus_id">Select Bus:</label>
                                <select name="bus_id" id="bus_id">
                                    <option value="">All Buses</option>
                                    <?php foreach ($buses as $bus): ?>
                                    <option value="<?php echo $bus['id']; ?>">
                                        <?php echo htmlspecialchars($bus['bus_name'] . ' - ' . $bus['plate_no']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="status">Ticket Status:</label>
                                <select name="status" id="status">
                                    <option value="">All Status</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_from">From Date:</label>
                                <input type="date" name="date_from" id="date_from">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to">To Date:</label>
                                <input type="date" name="date_to" id="date_to">
                            </div>
                            
                            <div class="filter-group" style="grid-column: 1 / -1;">
                                <label for="route">Route (Optional):</label>
                                <input type="text" name="route" id="route" placeholder="Enter route (e.g., Nairobi, Nakuru)">
                            </div>
                        </div>
                        
                        <div class="print-actions">
                            <button type="submit" class="btn-bulk btn-primary">
                                🖨️ Print Filtered Tickets
                            </button>
                            <button type="button" class="btn-bulk btn-warning" onclick="previewTickets()">
                                👁️ Preview Tickets
                            </button>
                            <button type="reset" class="btn-bulk btn-secondary">
                                🔄 Clear Filters
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Preview Section -->
                <div class="preview-section">
                    <h3>Recent Tickets Preview</h3>
                    <div class="ticket-preview">
                        <?php foreach ($recent_tickets as $ticket): ?>
                        <div class="ticket-item">
                            <div class="ticket-info">
                                <div class="ticket-no"><?php echo htmlspecialchars($ticket['ticket_no']); ?></div>
                                <div class="ticket-details">
                                    <?php echo htmlspecialchars($ticket['passenger_name']); ?> | 
                                    <?php echo htmlspecialchars($ticket['from_route'] . ' → ' . $ticket['to_route']); ?> | 
                                    <?php echo date('M d, Y', strtotime($ticket['travel_date'])); ?>
                                </div>
                            </div>
                            <div class="ticket-status status-<?php echo $ticket['status']; ?>">
                                <?php echo ucfirst($ticket['status']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickPrint(type) {
            let url = 'print_bulk_tickets.php?type=' + type;
            window.open(url, '_blank');
        }
        
        function previewTickets() {
            // Get form data
            const form = document.querySelector('form');
            const formData = new FormData(form);
            
            // Create preview URL
            let url = 'preview_tickets.php?';
            for (let [key, value] of formData.entries()) {
                if (value) {
                    url += key + '=' + encodeURIComponent(value) + '&';
                }
            }
            
            window.open(url, '_blank');
        }
        
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
            
            document.getElementById('date_from').value = today;
            document.getElementById('date_to').value = tomorrow;
        });
    </script>
</body>
</html>
