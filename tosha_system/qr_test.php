<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'qr_generator.php';

requireLogin();

// Get a sample ticket for testing
$database = new Database();
$db = $database->getConnection();

$query = "SELECT t.*, b.bus_name, b.plate_no, b.driver_name FROM tickets t 
          LEFT JOIN buses b ON t.bus_id = b.id 
          ORDER BY t.created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$sample_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sample_ticket) {
    // Create a sample ticket for demonstration
    $sample_ticket = [
        'ticket_no' => 'TK20250101001',
        'passenger_name' => 'John Doe',
        'phone' => '+254700000000',
        'from_route' => 'Nairobi',
        'to_route' => 'Mombasa',
        'bus_name' => 'TOSHA-001',
        'plate_no' => 'KCA 123A',
        'driver_name' => 'Peter Kimani',
        'seat_no' => 'A1',
        'travel_date' => '2025-01-15',
        'travel_time' => '08:00:00',
        'fare' => 2500.00,
        'created_at' => date('Y-m-d H:i:s')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - QR Code Test</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .qr-test-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .qr-demo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        .qr-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .qr-code {
            margin: 20px 0;
        }
        
        .qr-code img {
            max-width: 200px;
            height: auto;
            border: 2px solid #000;
            border-radius: 10px;
        }
        
        .qr-data {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .ticket-preview {
            background: #f8f9fa;
            border: 2px solid #000;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .ticket-header {
            background: #000;
            color: white;
            padding: 10px;
            text-align: center;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
        }
        
        .ticket-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .ticket-label {
            font-weight: bold;
            min-width: 80px;
        }
        
        .ticket-value {
            text-align: right;
            flex: 1;
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
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        @media (max-width: 768px) {
            .qr-demo {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>TOSHA EXPRESS</h1>
            <p class="motto">"Safest Mean Of Transport At Affordable Fares"</p>
        </div>
    </div>

    <div class="navbar">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="ticket_booking.php">Book Ticket</a></li>
                <li><a href="manage_bookings.php">Manage Bookings</a></li>
                <li><a href="qr_scanner.php">QR Scanner</a></li>
                <li><a href="qr_test.php" class="active">QR Test</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <div class="qr-test-container">
            <div class="card">
                <div class="card-header">QR Code Generation Test</div>
                
                <div class="qr-demo">
                    <div class="qr-section">
                        <h3>Simple Text QR Code</h3>
                        <p>Contains basic ticket information in readable text format</p>
                        
                        <div class="qr-code">
                            <?php 
                            $simpleQRData = generateSimpleQRData($sample_ticket);
                            $simpleQRUrl = generateQRCode($simpleQRData, 200);
                            ?>
                            <img src="<?php echo $simpleQRUrl; ?>" alt="Simple QR Code" />
                        </div>
                        
                        <div class="qr-data">
                            <strong>QR Data:</strong><br>
                            <?php echo nl2br(htmlspecialchars($simpleQRData)); ?>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        <h3>JSON Data QR Code</h3>
                        <p>Contains structured ticket data in JSON format</p>
                        
                        <div class="qr-code">
                            <?php 
                            $jsonQRData = generateTicketQRData($sample_ticket);
                            $jsonQRUrl = generateQRCode($jsonQRData, 200);
                            ?>
                            <img src="<?php echo $jsonQRUrl; ?>" alt="JSON QR Code" />
                        </div>
                        
                        <div class="qr-data">
                            <strong>QR Data:</strong><br>
                            <?php echo nl2br(htmlspecialchars($jsonQRData)); ?>
                        </div>
                    </div>
                </div>
                
                <div class="ticket-preview">
                    <div class="ticket-header">
                        <h2>TOSHA EXPRESS</h2>
                        <p>"Safest Mean Of Transport At Affordable Fares"</p>
                    </div>
                    
                    <div class="ticket-row">
                        <span class="ticket-label">Ticket No:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['ticket_no']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Passenger:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['passenger_name']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Phone:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['phone']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Route:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['from_route'] . ' → ' . $sample_ticket['to_route']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Bus:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['bus_name'] . ' (' . $sample_ticket['plate_no'] . ')'); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Seat No:</span>
                        <span class="ticket-value"><?php echo htmlspecialchars($sample_ticket['seat_no']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Travel Date:</span>
                        <span class="ticket-value"><?php echo date('M d, Y', strtotime($sample_ticket['travel_date'])); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Travel Time:</span>
                        <span class="ticket-value"><?php echo date('g:i A', strtotime($sample_ticket['travel_time'])); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-label">Fare:</span>
                        <span class="ticket-value">KSh <?php echo number_format($sample_ticket['fare'], 2); ?></span>
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <div style="font-size: 12px; margin-bottom: 10px;">QR Code - Scan for Details</div>
                        <img src="<?php echo $simpleQRUrl; ?>" alt="QR Code" style="max-width: 120px; height: auto;" />
                        <div style="font-size: 10px; margin-top: 5px; color: #666;">
                            Passenger: <?php echo htmlspecialchars($sample_ticket['passenger_name']); ?>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="print_ticket.php?ticket_no=<?php echo $sample_ticket['ticket_no']; ?>" target="_blank" class="btn btn-success">🖨️ Print Sample Ticket</a>
                    <a href="qr_scanner.php" class="btn btn-warning">📱 Test QR Scanner</a>
                    <a href="dashboard.php" class="btn">🏠 Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
