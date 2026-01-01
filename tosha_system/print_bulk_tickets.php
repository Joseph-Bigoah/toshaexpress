<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$tickets = [];
$print_type = $_GET['type'] ?? '';
$ticket_ids = $_GET['ticket_ids'] ?? '';

try {
    if (!empty($ticket_ids)) {
        // Print specific tickets by IDs
        $ids = explode(',', $ticket_ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                  FROM tickets t 
                  LEFT JOIN buses b ON t.bus_id = b.id 
                  WHERE t.id IN ($placeholders)
                  ORDER BY t.travel_date, t.travel_time, t.seat_no";
        $stmt = $db->prepare($query);
        $stmt->execute($ids);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif (!empty($print_type)) {
        // Quick print based on type
        switch ($print_type) {
            case 'today':
                $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                          FROM tickets t 
                          LEFT JOIN buses b ON t.bus_id = b.id 
                          WHERE t.travel_date = CURDATE()
                          ORDER BY t.travel_time, t.seat_no";
                break;
                
            case 'tomorrow':
                $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                          FROM tickets t 
                          LEFT JOIN buses b ON t.bus_id = b.id 
                          WHERE t.travel_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                          ORDER BY t.travel_time, t.seat_no";
                break;
                
            case 'confirmed':
                $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                          FROM tickets t 
                          LEFT JOIN buses b ON t.bus_id = b.id 
                          WHERE t.status = 'confirmed'
                          ORDER BY t.travel_date, t.travel_time, t.seat_no";
                break;
                
            case 'all':
            default:
                $query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name 
                          FROM tickets t 
                          LEFT JOIN buses b ON t.bus_id = b.id 
                          ORDER BY t.travel_date, t.travel_time, t.seat_no";
                break;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($tickets)) {
        die('No tickets found to print.');
    }
    
} catch (Exception $e) {
    die('Error fetching tickets: ' . $e->getMessage());
}

if (!function_exists('formatPaymentDisplay')) {
    function formatPaymentDisplay($value) {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return 'NOT SPECIFIED';
        }
        $normalized = strtolower($raw);
        if ($normalized === 'mpesa' || $normalized === 'm-pesa') {
            return 'M-PESA';
        }
        if ($normalized === 'cash') {
            return 'CASH';
        }
        return strtoupper($raw);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Bulk Ticket Print</title>
    <style>
        @page {
            size: A4;
            margin: 0.5in;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: white;
            color: #333;
        }
        
        .ticket {
            width: 100%;
            max-width: 300px;
            margin: 10px auto;
            border: 2px solid #1a237e;
            border-radius: 10px;
            background: white;
            page-break-inside: avoid;
            position: relative;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #1a237e, #0d1442);
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        .ticket-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .ticket-header .motto {
            margin: 5px 0 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .ticket-body {
            padding: 15px;
        }
        
        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.7rem;
            color: #666;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-size: 0.9rem;
            font-weight: bold;
            color: #333;
        }
        
        .ticket-route {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin: 10px 0;
            border-left: 4px solid #1a237e;
        }
        
        .route-text {
            font-size: 1rem;
            font-weight: bold;
            color: #1a237e;
        }
        
        .ticket-fare {
            background: #e3f2fd;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .fare-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1a237e;
        }
        
        .ticket-barcode {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .barcode-label {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .ticket-footer {
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 0.7rem;
            color: #666;
        }
        
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-btn {
            background: #1a237e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        
        .print-btn:hover {
            background: #0d1442;
        }
        
        .ticket-count {
            background: #e3f2fd;
            color: #1a237e;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        @media print {
            .print-controls {
                display: none;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .ticket {
                margin: 5px 0;
                page-break-inside: avoid;
            }
            
            .ticket-count {
                display: none;
            }
        }
        
        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="print-controls">
        <h4>Print Controls</h4>
        <button class="print-btn" onclick="window.print()">🖨️ Print All</button>
        <button class="print-btn" onclick="window.close()">❌ Close</button>
        <p style="margin: 10px 0 0 0; font-size: 0.8rem;">
            Total: <?php echo count($tickets); ?> tickets
        </p>
    </div>

    <div class="ticket-count">
        📋 Printing <?php echo count($tickets); ?> Ticket(s) - TOSHA EXPRESS
    </div>

    <div class="tickets-grid">
        <?php foreach ($tickets as $index => $ticket): ?>
        <div class="ticket">
            <div class="ticket-header">
                <h2>TOSHA EXPRESS</h2>
                <p class="motto">"Safest Mean Of Transport At Affordable Fares"</p>
            </div>
            
            <div class="ticket-body">
                <div class="ticket-info">
                    <div class="info-item">
                        <span class="info-label">Ticket No</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Seat No</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['seat_no']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Passenger</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['passenger_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['phone']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bus</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['bus_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Plate</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['plate_no']); ?></span>
                    </div>
                </div>
                
                <div class="ticket-route">
                    <div class="route-text">
                        <?php echo htmlspecialchars($ticket['from_route']); ?> → <?php echo htmlspecialchars($ticket['to_route']); ?>
                    </div>
                </div>
                
                <div class="ticket-info">
                    <div class="info-item">
                        <span class="info-label">Travel Date</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($ticket['travel_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Travel Time</span>
                        <span class="info-value"><?php echo date('H:i', strtotime($ticket['travel_time'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value"><?php echo htmlspecialchars(formatPaymentDisplay($ticket['payment_method'] ?? null)); ?></span>
                    </div>
                </div>
                
                <div class="ticket-fare">
                    <div class="info-label">Total Fare</div>
                    <div class="fare-amount">KSh <?php echo number_format($ticket['fare'], 2); ?></div>
                </div>
                
                <div class="ticket-barcode">
                    <div class="barcode-label">Barcode - Scan for Verification</div>
                    <svg id="barcode-<?php echo $ticket['id']; ?>" style="width: 100%; height: 50px;"></svg>
                    <div style="font-size: 0.6rem; margin-top: 5px; color: #666;">
                        Ticket: <?php echo htmlspecialchars($ticket['ticket_no']); ?>
                    </div>
                </div>
            </div>
            
            <div class="ticket-footer">
                <div>Driver: <?php echo htmlspecialchars($ticket['driver_name']); ?></div>
                <div>Booked: <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></div>
                <div>Status: <?php echo strtoupper($ticket['status']); ?></div>
                <div>Contact: 0722696460</div>
                <div style="font-size: 0.6rem; margin-top: 4px; color: #555;">System developed by Joseph Bigoah Puok</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Generate barcodes for all tickets
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($tickets as $ticket): ?>
            JsBarcode("#barcode-<?php echo $ticket['id']; ?>", "<?php echo htmlspecialchars($ticket['ticket_no']); ?>", {
                format: "CODE128",
                displayValue: false,
                width: 2,
                height: 50,
                margin: 0
            });
            <?php endforeach; ?>
            
            // Auto-print after a short delay
            setTimeout(function() {
                window.print();
            }, 1000);
        });
    </script>
</body>
</html>
