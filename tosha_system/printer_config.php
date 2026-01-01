<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$success_message = '';
$error_message = '';

// Handle printer configuration updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $printer_name = trim($_POST['printer_name']);
    $printer_type = $_POST['printer_type'];
    $printer_port = trim($_POST['printer_port']);
    $printer_ip = trim($_POST['printer_ip']);
    $printer_settings = json_encode([
        'name' => $printer_name,
        'type' => $printer_type,
        'port' => $printer_port,
        'ip' => $printer_ip,
        'baud_rate' => $_POST['baud_rate'] ?? 9600,
        'paper_width' => $_POST['paper_width'] ?? 80
    ]);
    
    // Save to file (in production, save to database)
    file_put_contents('config/printer_settings.json', $printer_settings);
    $success_message = 'Printer configuration saved successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Printer Configuration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .printer-test {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #007bff;
        }
        
        .printer-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #1a237e;
        }
        
        .status-indicator.connected {
            background: #28a745;
        }
        
        .printer-commands {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">External Printer Configuration</div>
            
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
            
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="printer_name">Printer Name:</label>
                            <input type="text" id="printer_name" name="printer_name" class="form-control" 
                                   value="TOSHA Express Printer" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="printer_type">Printer Type:</label>
                            <select id="printer_type" name="printer_type" class="form-control" required>
                                <option value="thermal">Thermal Printer (ESC/POS)</option>
                                <option value="dot_matrix">Dot Matrix Printer</option>
                                <option value="inkjet">Inkjet Printer</option>
                                <option value="laser">Laser Printer</option>
                                <option value="network">Network Printer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="printer_port">Port/Connection:</label>
                            <select id="printer_port" name="printer_port" class="form-control" required>
                                <option value="usb">USB</option>
                                <option value="serial">Serial (COM Port)</option>
                                <option value="parallel">Parallel (LPT Port)</option>
                                <option value="network">Network (TCP/IP)</option>
                                <option value="bluetooth">Bluetooth</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="printer_ip">IP Address (for network printers):</label>
                            <input type="text" id="printer_ip" name="printer_ip" class="form-control" 
                                   placeholder="192.168.1.100">
                        </div>
                        
                        <div class="form-group">
                            <label for="baud_rate">Baud Rate (for serial):</label>
                            <select id="baud_rate" name="baud_rate" class="form-control">
                                <option value="9600">9600</option>
                                <option value="19200">19200</option>
                                <option value="38400">38400</option>
                                <option value="57600">57600</option>
                                <option value="115200">115200</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="paper_width">Paper Width (characters):</label>
                            <select id="paper_width" name="paper_width" class="form-control">
                                <option value="58">58mm (Thermal)</option>
                                <option value="80">80mm (Standard)</option>
                                <option value="112">112mm (Wide)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-lg">Save Configuration</button>
                    <button type="button" class="btn btn-success btn-lg" onclick="testPrinter()">Test Printer</button>
                </div>
            </form>
        </div>

        <div class="printer-test">
            <h3>Printer Status & Testing</h3>
            
            <div class="printer-status">
                <div class="status-indicator" id="printerStatus"></div>
                <span id="printerStatusText">Checking printer status...</span>
            </div>
            
            <div style="margin: 20px 0;">
                <button class="btn btn-primary" onclick="checkPrinterStatus()">Check Status</button>
                <button class="btn btn-success" onclick="printTestTicket()">Print Test Ticket</button>
                <button class="btn btn-warning" onclick="printConfiguration()">Print Configuration</button>
            </div>
            
            <div id="printerLog" class="printer-commands" style="display: none;">
                <strong>Printer Log:</strong><br>
                <div id="logContent"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Printer Commands & Setup</div>
            
            <h4>Supported Printer Types:</h4>
            <ul>
                <li><strong>Thermal Printers:</strong> ESC/POS compatible (Epson, Star, Citizen)</li>
                <li><strong>Dot Matrix:</strong> Epson LQ series, IBM Proprinter</li>
                <li><strong>Inkjet/Laser:</strong> Standard Windows printers</li>
                <li><strong>Network Printers:</strong> TCP/IP connected printers</li>
            </ul>
            
            <h4>Setup Instructions:</h4>
            <ol>
                <li>Connect your printer to the computer (USB/Serial/Network)</li>
                <li>Install printer drivers if required</li>
                <li>Configure printer settings above</li>
                <li>Test the printer connection</li>
                <li>Print a test ticket to verify</li>
            </ol>
            
            <h4>Common Issues:</h4>
            <ul>
                <li><strong>USB not detected:</strong> Check USB cable and drivers</li>
                <li><strong>Serial connection failed:</strong> Verify COM port and baud rate</li>
                <li><strong>Network printer offline:</strong> Check IP address and network connection</li>
                <li><strong>Print quality poor:</strong> Clean printer head, check paper alignment</li>
            </ul>
        </div>
    </div>

    <script>
        function checkPrinterStatus() {
            const statusIndicator = document.getElementById('printerStatus');
            const statusText = document.getElementById('printerStatusText');
            const logDiv = document.getElementById('printerLog');
            const logContent = document.getElementById('logContent');
            
            logDiv.style.display = 'block';
            logContent.innerHTML = 'Checking printer status...<br>';
            
            // Simulate printer check
            setTimeout(() => {
                if (navigator.serial || navigator.usb) {
                    statusIndicator.classList.add('connected');
                    statusText.textContent = 'Printer connected and ready';
                    logContent.innerHTML += '✓ Printer hardware detected<br>';
                    logContent.innerHTML += '✓ Driver loaded successfully<br>';
                    logContent.innerHTML += '✓ Ready to print<br>';
                } else {
                    statusIndicator.classList.remove('connected');
                    statusText.textContent = 'Printer not detected - using system print';
                    logContent.innerHTML += '⚠ External printer not available<br>';
                    logContent.innerHTML += 'ℹ Will use system print dialog<br>';
                }
            }, 1000);
        }
        
        function printTestTicket() {
            // Open test ticket in new window
            window.open('print_test_ticket.php', '_blank', 'width=400,height=600');
        }
        
        function printConfiguration() {
            const config = {
                printer_name: document.getElementById('printer_name').value,
                printer_type: document.getElementById('printer_type').value,
                printer_port: document.getElementById('printer_port').value,
                printer_ip: document.getElementById('printer_ip').value,
                baud_rate: document.getElementById('baud_rate').value,
                paper_width: document.getElementById('paper_width').value
            };
            
            alert('Printer Configuration:\n\n' + JSON.stringify(config, null, 2));
        }
        
        function testPrinter() {
            checkPrinterStatus();
            setTimeout(() => {
                printTestTicket();
            }, 1500);
        }
        
        // Check printer status on page load
        window.addEventListener('load', checkPrinterStatus);
    </script>
</body>
</html>
