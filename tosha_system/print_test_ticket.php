<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Test Ticket</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: white;
            color: black;
        }
        
        .ticket {
            width: 300px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 0;
            background: white;
        }
        
        .ticket-header {
            background: #000;
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
            background: #f0f0f0;
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
        
        .ticket-footer {
            background: #f8f9fa;
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
            }
            
            .ticket {
                margin: 0;
                box-shadow: none;
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
        <h2>TOSHA EXPRESS - Test Ticket</h2>
        <p>This is a test ticket to verify printer functionality</p>
        <button class="btn" onclick="window.print()">🖨️ Print Test Ticket</button>
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
                <span class="ticket-value">TEST-<?php echo date('Ymd') . rand(1000, 9999); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Passenger:</span>
                <span class="ticket-value">Test Passenger</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Phone:</span>
                <span class="ticket-value">+254 700 000 000</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Route:</span>
                <span class="ticket-value">Nairobi → Nakuru</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Bus:</span>
                <span class="ticket-value">TOSHA EXPRESS 001 (KCA 001A)</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Driver:</span>
                <span class="ticket-value">John Mwangi</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Seat No:</span>
                <span class="ticket-value">S01</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Travel Date:</span>
                <span class="ticket-value"><?php echo date('M d, Y'); ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Travel Time:</span>
                <span class="ticket-value">08:00 AM</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Booking Date:</span>
                <span class="ticket-value"><?php echo date('M d, Y g:i A'); ?></span>
            </div>
        </div>
        
        <div class="ticket-fare">
            <div>Fare Amount:</div>
            <div class="amount">KSh 1,500.00</div>
        </div>
        
        <div class="ticket-barcode">
            ||| TEST123456789012345 |||
        </div>
        
        <div class="ticket-footer">
            <p><strong>TEST TICKET - PRINTER VERIFICATION</strong></p>
            <p>• This is a test ticket for printer setup</p>
            <p>• Verify print quality and alignment</p>
            <p>• Check paper feed and cutting</p>
            <p>• Contact: +254 700 000 000</p>
            <p>Thank you for choosing TOSHA EXPRESS!</p>
        </div>
    </div>

    <script>
        function printToExternal() {
            // Try to print to external printer
            if (navigator.serial) {
                printToSerialPrinter();
            } else if (navigator.usb) {
                printToUSBPrinter();
            } else {
                alert('External printer not available. Using system print dialog.');
                window.print();
            }
        }
        
        async function printToSerialPrinter() {
            try {
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                
                const writer = port.writable.getWriter();
                const ticketData = generateTestTicketData();
                
                await writer.write(new TextEncoder().encode(ticketData));
                writer.releaseLock();
                await port.close();
                
                alert('Test ticket sent to external printer successfully!');
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
                
                const ticketData = generateTestTicketData();
                await device.transferOut(1, new TextEncoder().encode(ticketData));
                
                alert('Test ticket sent to USB printer successfully!');
            } catch (error) {
                console.error('USB printer error:', error);
                alert('Failed to print to USB printer. Using system print dialog.');
                window.print();
            }
        }
        
        function generateTestTicketData() {
            let ticketData = '';
            ticketData += '================================\n';
            ticketData += '        TOSHA EXPRESS\n';
            ticketData += '  Safest Mean Of Transport\n';
            ticketData += '      At Affordable Fares\n';
            ticketData += '================================\n\n';
            ticketData += `Ticket No: TEST-${new Date().toISOString().slice(0,10).replace(/-/g,'')}${Math.floor(Math.random()*10000)}\n`;
            ticketData += 'Passenger: Test Passenger\n';
            ticketData += 'Phone: +254 700 000 000\n';
            ticketData += 'Route: Nairobi → Nakuru\n';
            ticketData += 'Bus: TOSHA EXPRESS 001 (KCA 001A)\n';
            ticketData += 'Driver: John Mwangi\n';
            ticketData += 'Seat No: S01\n';
            ticketData += `Travel Date: ${new Date().toLocaleDateString()}\n`;
            ticketData += 'Travel Time: 08:00 AM\n';
            ticketData += `Booking Date: ${new Date().toLocaleString()}\n\n`;
            ticketData += 'Fare Amount: KSh 1,500.00\n\n';
            ticketData += '================================\n';
            ticketData += 'TEST TICKET - PRINTER VERIFICATION\n';
            ticketData += '• This is a test ticket for printer setup\n';
            ticketData += '• Verify print quality and alignment\n';
            ticketData += '• Check paper feed and cutting\n';
            ticketData += '• Contact: +254 700 000 000\n';
            ticketData += 'Thank you for choosing TOSHA EXPRESS!\n';
            ticketData += '================================\n';
            ticketData += '\n\n\n'; // Extra paper feed
            
            return ticketData;
        }
        
        // Auto-print when page loads
        window.addEventListener('load', function() {
            setTimeout(() => {
                window.print();
            }, 1000);
        });
    </script>
</body>
</html>
