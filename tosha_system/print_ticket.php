<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'qr_generator.php';

requireLogin();

$ticket_no = $_GET['ticket_no'] ?? '';

if (empty($ticket_no)) {
    echo "<div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>";
    echo "<h2>Ticket Not Found</h2>";
    echo "<p>No ticket number provided.</p>";
    echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
    echo "</div>";
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if payment_method column exists
$query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
          LEFT JOIN buses b ON t.bus_id = b.id 
          WHERE t.ticket_no = ?";
$stmt = $db->prepare($query);
$stmt->execute([$ticket_no]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo "<div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>";
    echo "<h2>Ticket Not Found</h2>";
    echo "<p>Ticket number: <strong>" . htmlspecialchars($ticket_no) . "</strong></p>";
    echo "<p>This ticket does not exist in our system.</p>";
    echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
    echo "</div>";
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
    <title>TOSHA EXPRESS - Print Ticket</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            /* Match system theme: yellow with dark spots */
            background-color: #FFD700;
            background-image:
                radial-gradient(rgba(13, 20, 66, 0.20) 2px, transparent 2px),
                radial-gradient(rgba(13, 20, 66, 0.12) 2px, transparent 2px);
            background-position: 0 0, 11px 11px;
            background-size: 22px 22px;
            color: #333333;
        }
        
        .ticket {
            width: 300px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 0;
            background: white;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #FFC107, #B8860B);
            color: white;
            padding: 10px;
            text-align: center;
        }
        
        .ticket-header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .ticket-header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            font-style: italic;
        }
        
        .ticket-body {
            padding: 15px;
        }
        
        .ticket-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .ticket-row:last-child {
            border-bottom: none;
        }
        
        .ticket-label {
            font-weight: bold;
            min-width: 80px;
        }
        
        .ticket-value {
            text-align: right;
            flex: 1;
        }
        
        .ticket-fare {
            background: #fff8e1; /* soft yellow */
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        
        .ticket-fare .amount {
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }
        
        .ticket-barcode {
            margin: 20px 0;
            text-align: center;
            font-family: monospace;
            font-size: 16px;
            letter-spacing: 2px;
        }
        
        .barcode-box {
            margin: 20px 0;
            text-align: center;
            padding: 10px;
            background: #fff8e1;
            border-radius: 5px;
        }
        
        .barcode-label {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #666;
        }
        
        .ticket-footer {
            background: #fff8e1;
            padding: 10px;
            font-size: 10px;
            text-align: center;
            border-top: 1px solid #ccc;
        }
        
        .print-controls {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-danger {
            background: #1a237e;
        }
        
        @media print {
            .print-controls {
                display: none;
            }
            
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                /* Preserve themed background when printing */
                background-color: #FFD700 !important;
                background-image: radial-gradient(rgba(13, 20, 66, 0.20) 2px, transparent 2px),
                                  radial-gradient(rgba(13, 20, 66, 0.12) 2px, transparent 2px) !important;
                background-position: 0 0, 11px 11px !important;
                background-size: 22px 22px !important;
            }
            
            .ticket {
                margin: 0;
                box-shadow: none;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Ensure barcode is visible and high quality when printing */
            .barcode-box {
                display: block !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                page-break-inside: avoid;
            }
            #ticket-barcode { height: 80px; }

            /* Preserve background colors for header, fare, footer, and barcode area */
            .ticket-header,
            .ticket-fare,
            .ticket-footer,
            .ticket-barcode,
            .barcode-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @page {
            size: A4;
            margin: 0.5in;
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <h2>TOSHA EXPRESS - Ticket Printing</h2>
        <p>Ticket Number: <strong><?php echo htmlspecialchars($ticket['ticket_no']); ?></strong></p>
        <button class="btn" onclick="window.print()">🖨️ Print Ticket</button>
        <button class="btn btn-success" onclick="printToExternal()">🖨️ Print to External Printer</button>
        <button class="btn btn-danger" onclick="window.close()">❌ Close</button>
    </div>

    <div class="ticket">
        <div class="ticket-header">
            <h1>TOSHA EXPRESS</h1>
            <p>"Safest Mean Of Transport At Affordable Fares"</p>
        </div>
        
        <div class="ticket-body">
            <div class="ticket-row">
                <span class="ticket-label">Ticket No:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Passenger:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['passenger_name']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Phone:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['phone']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Route:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['from_route'] . ' → ' . $ticket['to_route']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Bus:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['bus_name'] . ' (' . $ticket['plate_no'] . ')'); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Driver:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['driver_name']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Seat No:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($ticket['seat_no']); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Travel Date:</span>
                <span class="ticket-value"><?php echo date('M d, Y', strtotime($ticket['travel_date'])); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Travel Time:</span>
                <span class="ticket-value"><?php echo date('g:i A', strtotime($ticket['travel_time'])); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Payment Method:</span>
                <span class="ticket-value"><?php echo htmlspecialchars($payment_display); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Booking Date:</span>
                <span class="ticket-value"><?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="ticket-fare">
            <div>Fare Amount:</div>
            <div class="amount">KSh <?php echo number_format($ticket['fare'], 2); ?></div>
        </div>
        
        <div class="ticket-barcode">
            ||| <?php echo str_pad($ticket['ticket_no'], 20, '0', STR_PAD_LEFT); ?> |||
        </div>
        
        <div class="barcode-box">
            <div class="barcode-label">Barcode - Scan for Ticket</div>
            <svg id="ticket-barcode" style="width: 100%; max-width: 260px; height: 80px;"></svg>
            <div style="font-size: 10px; margin-top: 5px; color: #666;">
                Passenger: <?php echo htmlspecialchars($ticket['passenger_name']); ?>
            </div>
        </div>
        
        <div class="ticket-footer">
            <p><strong>Important:</strong></p>
            <p>• Please arrive 30 minutes before departure time</p>
            <p>• Keep this ticket safe for boarding</p>
            <p>• No refunds for no-shows</p>
            <p>• Contact: 0722696460</p>
            <p>Thank you for choosing TOSHA EXPRESS!</p>
            <p style="font-size: 8px; margin-top: 6px; color: #555;">
                System developed by Joseph Bigoah Puok
            </p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.addEventListener('load', function() {
            // Uncomment the line below to auto-print
            // window.print();
        });
        
        function printToExternal() {
            // Try to print to external printer
            if (navigator.serial) {
                // Web Serial API for direct printer communication
                printToSerialPrinter();
            } else if (navigator.usb) {
                // Web USB API for USB printers
                printToUSBPrinter();
            } else {
                // Fallback to regular print dialog
                alert('External printer not available. Using system print dialog.');
                window.print();
            }
        }
        
        async function printToSerialPrinter() {
            try {
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                
                const writer = port.writable.getWriter();
                const ticketData = generateTicketData();
                
                await writer.write(new TextEncoder().encode(ticketData));
                writer.releaseLock();
                await port.close();
                
                alert('Ticket sent to external printer successfully!');
            } catch (error) {
                console.error('Serial printer error:', error);
                alert('Failed to print to external printer. Using system print dialog.');
                window.print();
            }
        }
        
        async function printToUSBPrinter() {
            try {
                const device = await navigator.usb.requestDevice({ filters: [] });
                await device.open();
                await device.selectConfiguration(1);
                await device.claimInterface(0);
                
                const ticketData = generateTicketData();
                await device.transferOut(1, new TextEncoder().encode(ticketData));
                
                alert('Ticket sent to USB printer successfully!');
            } catch (error) {
                console.error('USB printer error:', error);
                alert('Failed to print to USB printer. Using system print dialog.');
                window.print();
            }
        }
        
        function formatPaymentMethod(value) {
            if (!value) {
                return 'NOT SPECIFIED';
            }
            const normalized = value.toString().trim().toLowerCase();
            if (normalized === 'mpesa' || normalized === 'm-pesa') {
                return 'M-PESA';
            }
            if (normalized === 'cash') {
                return 'CASH';
            }
            return value.toString().toUpperCase();
        }
        
        function generateTicketData() {
            // Generate plain text ticket data for external printers
            const ticket = <?php echo json_encode($ticket); ?>;
            
            let ticketData = '';
            ticketData += '================================\n';
            ticketData += '        TOSHA EXPRESS\n';
            ticketData += '  Safest Mean Of Transport\n';
            ticketData += '      At Affordable Fares\n';
            ticketData += '================================\n\n';
            ticketData += `Ticket No: ${ticket.ticket_no}\n`;
            ticketData += `Passenger: ${ticket.passenger_name}\n`;
            ticketData += `Phone: ${ticket.phone}\n`;
            ticketData += `Route: ${ticket.from_route} → ${ticket.to_route}\n`;
            ticketData += `Bus: ${ticket.bus_name} (${ticket.plate_no})\n`;
            ticketData += `Driver: ${ticket.driver_name}\n`;
            ticketData += `Seat No: ${ticket.seat_no}\n`;
            ticketData += `Travel Date: ${new Date(ticket.travel_date).toLocaleDateString()}\n`;
            ticketData += `Travel Time: ${new Date('1970-01-01T' + ticket.travel_time).toLocaleTimeString()}\n`;
            ticketData += `Payment Method: ${formatPaymentMethod(ticket.payment_method)}\n`;
            ticketData += `Booking Date: ${new Date(ticket.created_at).toLocaleString()}\n\n`;
            ticketData += `Fare Amount: KSh ${parseFloat(ticket.fare).toFixed(2)}\n\n`;
            ticketData += '================================\n';
            ticketData += 'Important:\n';
            ticketData += '• Arrive 30 minutes before departure\n';
            ticketData += '• Keep ticket safe for boarding\n';
            ticketData += '• No refunds for no-shows\n';
            ticketData += '• Contact: 0722696460\n';
            ticketData += 'Thank you for choosing TOSHA EXPRESS!\n';
            ticketData += 'System developed by Joseph Bigoah Puok\n';
            ticketData += '================================\n';
            ticketData += '\n\n\n'; // Extra paper feed
            
            return ticketData;
        }
        
        // ESC/POS printer commands (for thermal printers)
        function generateESCPOSData() {
            const ticket = <?php echo json_encode($ticket); ?>;
            
            let escposData = '';
            
            // Initialize printer
            escposData += '\x1B\x40'; // ESC @ (Initialize)
            escposData += '\x1B\x61\x01'; // ESC a 1 (Center alignment)
            escposData += '\x1B\x21\x30'; // ESC ! 0 (Double height, double width)
            escposData += 'TOSHA EXPRESS\n';
            escposData += '\x1B\x21\x00'; // ESC ! 0 (Normal text)
            escposData += 'Safest Mean Of Transport\n';
            escposData += 'At Affordable Fares\n';
            escposData += '\x1B\x61\x00'; // ESC a 0 (Left alignment)
            escposData += '================================\n';
            escposData += `Ticket No: ${ticket.ticket_no}\n`;
            escposData += `Passenger: ${ticket.passenger_name}\n`;
            escposData += `Phone: ${ticket.phone}\n`;
            escposData += `Route: ${ticket.from_route} → ${ticket.to_route}\n`;
            escposData += `Bus: ${ticket.bus_name} (${ticket.plate_no})\n`;
            escposData += `Driver: ${ticket.driver_name}\n`;
            escposData += `Seat No: ${ticket.seat_no}\n`;
            escposData += `Travel Date: ${new Date(ticket.travel_date).toLocaleDateString()}\n`;
            escposData += `Travel Time: ${new Date('1970-01-01T' + ticket.travel_time).toLocaleTimeString()}\n`;
            escposData += `Payment Method: ${formatPaymentMethod(ticket.payment_method)}\n`;
            escposData += `Booking Date: ${new Date(ticket.created_at).toLocaleString()}\n`;
            escposData += '================================\n';
            escposData += `Fare Amount: KSh ${parseFloat(ticket.fare).toFixed(2)}\n`;
            escposData += '================================\n';
            escposData += 'Important:\n';
            escposData += '• Arrive 30 minutes before departure\n';
            escposData += '• Keep ticket safe for boarding\n';
            escposData += '• No refunds for no-shows\n';
            escposData += '• Contact: 0722696460\n';
            escposData += 'Thank you for choosing TOSHA EXPRESS!\n';
            escposData += 'System developed by Joseph Bigoah Puok\n';
            escposData += '================================\n';
            escposData += '\n\n\n'; // Extra paper feed
            escposData += '\x1D\x56\x00'; // GS V 0 (Cut paper)
            
            return escposData;
        }
        // Render Code128 barcode for ticket number
        (function() {
            try {
                const value = <?php echo json_encode($ticket['ticket_no']); ?>;
                if (window.JsBarcode) {
                    JsBarcode('#ticket-barcode', value, {
                        format: 'CODE128',
                        lineColor: '#000',
                        width: 2,
                        height: 60,
                        displayValue: true,
                        fontSize: 12,
                        margin: 0,
                    });
                }
            } catch (e) {
                console.warn('Barcode render failed:', e);
            }
        })();
    </script>
</body>
</html>
