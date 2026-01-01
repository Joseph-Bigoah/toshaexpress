<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$ticket_no = $_GET['ticket_no'] ?? '';

if (empty($ticket_no)) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
          LEFT JOIN buses b ON t.bus_id = b.id 
          WHERE t.ticket_no = ?";
$stmt = $db->prepare($query);
$stmt->execute([$ticket_no]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo "Ticket not found.";
    exit();
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

$payment_display = formatPaymentDisplay($ticket['payment_method'] ?? null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Ticket Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        .receipt {
            border: 2px solid #333;
            padding: 20px;
            text-align: center;
        }
        .header {
            background: #DC143C;
            color: white;
            padding: 15px;
            margin: -20px -20px 20px -20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-style: italic;
        }
        .ticket-info {
            text-align: left;
            margin: 20px 0;
        }
        .ticket-info div {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        .ticket-info .label {
            font-weight: bold;
        }
        .ticket-info .value {
            color: #333;
        }
        .fare {
            background: #f0f0f0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .fare .amount {
            font-size: 24px;
            font-weight: bold;
            color: #DC143C;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
        .barcode {
            margin: 20px 0;
            font-family: monospace;
            font-size: 16px;
            letter-spacing: 2px;
        }
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>TOSHA EXPRESS</h1>
            <p>"SAFEST MEAN OF TRANSPORT AT AFFORDABLE FARES"</p>
        </div>
        
        <div class="ticket-info">
            <div>
                <span class="label">Ticket No:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
            </div>
            <div>
                <span class="label">Passenger:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['passenger_name']); ?></span>
            </div>
            <div>
                <span class="label">Phone:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['phone']); ?></span>
            </div>
            <div>
                <span class="label">Route:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['from_route'] . ' → ' . $ticket['to_route']); ?></span>
            </div>
            <div>
                <span class="label">Bus:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['bus_name'] . ' (' . $ticket['plate_no'] . ')'); ?></span>
            </div>
            <div>
                <span class="label">Driver:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['driver_name']); ?></span>
            </div>
            <div>
                <span class="label">Seat No:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['seat_no']); ?></span>
            </div>
            <div>
                <span class="label">Travel Date:</span>
                <span class="value"><?php echo date('M d, Y', strtotime($ticket['travel_date'])); ?></span>
            </div>
            <div>
                <span class="label">Travel Time:</span>
                <span class="value"><?php echo date('g:i A', strtotime($ticket['travel_time'])); ?></span>
            </div>
            <div>
                <span class="label">Payment Method:</span>
                <span class="value"><?php echo htmlspecialchars($payment_display); ?></span>
            </div>
            <div>
                <span class="label">Booking Date:</span>
                <span class="value"><?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="fare">
            <div>Fare Amount:</div>
            <div class="amount">KSh <?php echo number_format($ticket['fare'], 2); ?></div>
        </div>
        
        <div class="barcode">
            ||| <?php echo str_pad($ticket['ticket_no'], 20, '0', STR_PAD_LEFT); ?> |||
        </div>
        
        <div class="footer">
            <p><strong>Important:</strong></p>
            <p>• Please arrive 30 minutes before departure time</p>
            <p>• Keep this ticket safe for boarding</p>
            <p>• No refunds for no-shows</p>
            <p>• Contact: +254 700 000 000</p>
            <p>Thank you for choosing TOSHA EXPRESS!</p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="background: #DC143C; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">Print Receipt</button>
        <button onclick="window.close()" style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Close</button>
    </div>
</body>
</html>
