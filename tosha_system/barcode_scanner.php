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
    <title>TOSHA EXPRESS - Barcode Scanner</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <style>
        .scanner-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .scanner-box {
            background: white;
            border: 2px solid #1a237e;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        
        #scanner {
            width: 100%;
            max-width: 500px;
            height: 300px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }
        
        .scanner-placeholder {
            color: #666;
            font-size: 16px;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 100px;
            border: 2px solid #1a237e;
            border-radius: 10px;
            background: transparent;
            pointer-events: none;
        }
        
        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border: 2px solid #1a237e;
            border-radius: 10px;
            animation: scanPulse 2s infinite;
        }
        
        @keyframes scanPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .result-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            display: none;
        }
        
        .ticket-info {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #eee;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .passenger-name {
            background: #e3f2fd;
            color: #0d1442;
            padding: 10px;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
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
        
        .status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #e3f2fd;
            color: #0d1442;
            border: 1px solid #1a237e;
        }
        
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .scanning-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="scanner-container">
            <div class="card">
                <div class="card-header">Barcode Scanner - Scan Ticket Barcodes</div>
                
                <div class="scanner-box">
                    <h3>Scan Ticket Barcode</h3>
                    <p>Use your device camera to scan a TOSHA EXPRESS ticket barcode</p>
                    
                    <div id="scanner">
                        <div class="scanner-placeholder">
                            📱 Camera will appear here when you click "Start Scanner"
                        </div>
                        <div class="scanner-overlay"></div>
                    </div>
                    
                    <button id="startScanner" class="btn btn-success">📷 Start Scanner</button>
                    <button id="stopScanner" class="btn btn-danger" style="display: none;">⏹️ Stop Scanner</button>
                    <button id="testBarcode" class="btn">🧪 Test with Sample Barcode</button>
                </div>
                
                <div id="result" style="display: none;">
                    <div class="result-box">
                        <h4>Scan Result:</h4>
                        <div id="resultContent"></div>
                    </div>
                </div>
                
                <div class="ticket-info" id="ticketInfo" style="display: none;">
                    <h4>Ticket Information:</h4>
                    <div class="passenger-name" id="passengerName"></div>
                    <div id="ticketDetails"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let scannerActive = false;
        let mediaStream = null;
        
        document.getElementById('startScanner').addEventListener('click', startScanner);
        document.getElementById('stopScanner').addEventListener('click', stopScanner);
        document.getElementById('testBarcode').addEventListener('click', testWithSampleBarcode);
        
        function startScanner() {
            if (scannerActive) return;
            
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            })
            .then(function(stream) {
                mediaStream = stream;
                const video = document.createElement('video');
                video.srcObject = stream;
                video.style.width = '100%';
                video.style.height = '100%';
                video.style.objectFit = 'cover';
                video.play();
                
                document.getElementById('scanner').innerHTML = '';
                document.getElementById('scanner').appendChild(video);
                
                // Add scanning indicator
                const indicator = document.createElement('div');
                indicator.className = 'scanning-indicator';
                indicator.textContent = 'SCANNING...';
                document.getElementById('scanner').appendChild(indicator);
                
                document.getElementById('startScanner').style.display = 'none';
                document.getElementById('stopScanner').style.display = 'inline-block';
                scannerActive = true;
                
                // Start barcode detection
                startBarcodeDetection(video);
            })
            .catch(function(err) {
                console.error('Error accessing camera:', err);
                alert('Error accessing camera. Please ensure camera permissions are granted.');
            });
        }
        
        function stopScanner() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
            
            document.getElementById('scanner').innerHTML = '<div class="scanner-placeholder">📱 Camera will appear here when you click "Start Scanner"</div><div class="scanner-overlay"></div>';
            document.getElementById('startScanner').style.display = 'inline-block';
            document.getElementById('stopScanner').style.display = 'none';
            scannerActive = false;
        }
        
        function startBarcodeDetection(video) {
            // Initialize Quagga for barcode detection
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: video,
                    constraints: {
                        width: 640,
                        height: 480,
                        facingMode: "environment"
                    }
                },
                decoder: {
                    readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "code_39_vin_reader", "codabar_reader", "upc_reader", "upc_e_reader", "i2of5_reader"]
                },
                locate: true,
                locator: {
                    patchSize: "medium",
                    halfSample: true
                }
            }, function(err) {
                if (err) {
                    console.error('Quagga initialization error:', err);
                    return;
                }
                Quagga.start();
            });
            
            // Listen for barcode detection
            Quagga.onDetected(function(data) {
                if (data && data.codeResult && data.codeResult.code) {
                    const ticketNumber = data.codeResult.code;
                    console.log('Barcode detected:', ticketNumber);
                    handleBarcodeScan(ticketNumber);
                }
            });
        }
        
        function handleBarcodeScan(ticketNumber) {
            // Stop scanning temporarily
            Quagga.stop();
            
            // Fetch ticket details
            fetch('get_ticket_details.php?ticket_no=' + encodeURIComponent(ticketNumber))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTicketInfo(data.ticket);
                    } else {
                        displayError('Ticket not found: ' + ticketNumber);
                    }
                })
                .catch(error => {
                    console.error('Error fetching ticket details:', error);
                    displayError('Error fetching ticket details');
                })
                .finally(() => {
                    // Resume scanning after a delay
                    setTimeout(() => {
                        if (scannerActive) {
                            Quagga.start();
                        }
                    }, 3000);
                });
        }
        
        function displayTicketInfo(ticket) {
            document.getElementById('result').style.display = 'block';
            document.getElementById('ticketInfo').style.display = 'block';
            
            // Display passenger name prominently
            document.getElementById('passengerName').textContent = 'Passenger: ' + ticket.passenger_name;
            
            // Display ticket details
            const details = `
                <div class="info-row">
                    <span class="info-label">Ticket Number:</span>
                    <span class="info-value">${ticket.ticket_no}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">${ticket.phone}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Route:</span>
                    <span class="info-value">${ticket.from_route} → ${ticket.to_route}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bus:</span>
                    <span class="info-value">${ticket.bus_name} (${ticket.plate_no})</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Seat:</span>
                    <span class="info-value">${ticket.seat_no}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Travel Date:</span>
                    <span class="info-value">${new Date(ticket.travel_date).toLocaleDateString()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Travel Time:</span>
                    <span class="info-value">${new Date('1970-01-01T' + ticket.travel_time).toLocaleTimeString()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value">${(ticket.payment_method || 'Cash').toUpperCase()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fare:</span>
                    <span class="info-value">KSh ${parseFloat(ticket.fare).toFixed(2)}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">${ticket.status}</span>
                </div>
            `;
            
            document.getElementById('ticketDetails').innerHTML = details;
            document.getElementById('resultContent').innerHTML = '<div class="status success">✓ Ticket found and verified!</div>';
        }
        
        function displayError(message) {
            document.getElementById('result').style.display = 'block';
            document.getElementById('ticketInfo').style.display = 'none';
            document.getElementById('resultContent').innerHTML = '<div class="status error">❌ ' + message + '</div>';
        }
        
        function testWithSampleBarcode() {
            // Simulate scanning a barcode with sample data
            const sampleTicketNumber = 'TK20250101001';
            handleBarcodeScan(sampleTicketNumber);
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopScanner();
            if (typeof Quagga !== 'undefined') {
                Quagga.stop();
            }
        });
    </script>
</body>
</html>
