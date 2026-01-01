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
    <title>TOSHA EXPRESS - QR Code Scanner</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .scanner-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .scanner-box {
            background: white;
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        
        #scanner {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .scanner-placeholder {
            color: #666;
            font-size: 16px;
        }
        
        .result-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
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
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="scanner-container">
            <div class="card">
                <div class="card-header">QR Code Scanner - Verify Tickets</div>
                
                <div class="scanner-box">
                    <h3>Scan QR Code</h3>
                    <p>Use your device camera to scan a TOSHA EXPRESS ticket QR code</p>
                    
                    <div id="scanner">
                        <div class="scanner-placeholder">
                            📱 Camera will appear here when you click "Start Scanner"
                        </div>
                    </div>
                    
                    <button id="startScanner" class="btn btn-success">📷 Start Scanner</button>
                    <button id="stopScanner" class="btn btn-danger" style="display: none;">⏹️ Stop Scanner</button>
                    <button id="testQR" class="btn">🧪 Test with Sample QR</button>
                </div>
                
                <div id="result" style="display: none;">
                    <div class="result-box">
                        <h4>Scan Result:</h4>
                        <div id="resultContent"></div>
                    </div>
                </div>
                
                <div class="ticket-info" id="ticketInfo" style="display: none;">
                    <h4>Ticket Information:</h4>
                    <div id="ticketDetails"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <script>
        let scannerActive = false;
        let mediaStream = null;
        
        document.getElementById('startScanner').addEventListener('click', startScanner);
        document.getElementById('stopScanner').addEventListener('click', stopScanner);
        document.getElementById('testQR').addEventListener('click', testWithSampleQR);
        
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
                
                document.getElementById('startScanner').style.display = 'none';
                document.getElementById('stopScanner').style.display = 'inline-block';
                scannerActive = true;
                
                // Start QR code detection
                startQRDetection(video);
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
            
            document.getElementById('scanner').innerHTML = '<div class="scanner-placeholder">📱 Camera will appear here when you click "Start Scanner"</div>';
            document.getElementById('startScanner').style.display = 'inline-block';
            document.getElementById('stopScanner').style.display = 'none';
            scannerActive = false;
        }
        
        function startQRDetection(video) {
            // Simple QR code detection using canvas and image processing
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            function detectQR() {
                if (!scannerActive) return;
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                // Convert to image data and look for QR patterns
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                
                // Simple QR detection (in a real app, you'd use a proper QR library)
                // For now, we'll simulate detection
                setTimeout(detectQR, 100);
            }
            
            detectQR();
        }
        
        function testWithSampleQR() {
            // Simulate scanning a QR code with sample data
            const sampleData = {
                ticket_no: 'TK20250101001',
                passenger: 'John Doe',
                phone: '+254700000000',
                route: 'Nairobi → Mombasa',
                bus: 'TOSHA-001 (KCA 123A)',
                seat: 'A1',
                date: 'Jan 15, 2025',
                time: '8:00 AM',
                fare: '2500.00',
                company: 'TOSHA EXPRESS'
            };
            
            displayScanResult(JSON.stringify(sampleData, null, 2));
            displayTicketInfo(sampleData);
        }
        
        function displayScanResult(data) {
            document.getElementById('result').style.display = 'block';
            document.getElementById('resultContent').innerHTML = '<pre>' + data + '</pre>';
        }
        
        function displayTicketInfo(ticketData) {
            document.getElementById('ticketInfo').style.display = 'block';
            
            const details = `
                <div class="info-row">
                    <span class="info-label">Ticket Number:</span>
                    <span class="info-value">${ticketData.ticket_no}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Passenger:</span>
                    <span class="info-value">${ticketData.passenger}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">${ticketData.phone}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Route:</span>
                    <span class="info-value">${ticketData.route}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bus:</span>
                    <span class="info-value">${ticketData.bus}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Seat:</span>
                    <span class="info-value">${ticketData.seat}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Travel Date:</span>
                    <span class="info-value">${ticketData.date}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Travel Time:</span>
                    <span class="info-value">${ticketData.time}</span>
                </div>
                ${ticketData.payment_method ? `
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value">${ticketData.payment_method.toUpperCase()}</span>
                </div>
                ` : ''}
                <div class="info-row">
                    <span class="info-label">Fare:</span>
                    <span class="info-value">KSh ${ticketData.fare}</span>
                </div>
            `;
            
            document.getElementById('ticketDetails').innerHTML = details;
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopScanner();
        });
    </script>
</body>
</html>
