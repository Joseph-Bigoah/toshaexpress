<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$parcel_no = $_GET['parcel_no'] ?? '';

if (empty($parcel_no)) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM parcels WHERE parcel_no = ?";
$stmt = $db->prepare($query);
$stmt->execute([$parcel_no]);
$parcel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parcel) {
    echo "Parcel not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Parcel Receipt</title>
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
        .parcel-info {
            text-align: left;
            margin: 20px 0;
        }
        .parcel-info div {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        .parcel-info .label {
            font-weight: bold;
        }
        .parcel-info .value {
            color: #333;
        }
        .cost {
            background: #f0f0f0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .cost .amount {
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
        
        <div class="parcel-info">
            <div>
                <span class="label">Parcel No:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['parcel_no']); ?></span>
            </div>
            <div>
                <span class="label">Sender:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['sender_name']); ?></span>
            </div>
            <div>
                <span class="label">Sender Phone:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['sender_phone']); ?></span>
            </div>
            <div>
                <span class="label">Receiver:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['receiver_name']); ?></span>
            </div>
            <div>
                <span class="label">Receiver Phone:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['receiver_phone']); ?></span>
            </div>
            <div>
                <span class="label">Route:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['from_route'] . ' → ' . $parcel['to_route']); ?></span>
            </div>
            <div>
                <span class="label">Description:</span>
                <span class="value"><?php echo htmlspecialchars($parcel['description']); ?></span>
            </div>
            <div>
                <span class="label">Weight:</span>
                <span class="value"><?php echo $parcel['weight']; ?> kg</span>
            </div>
            <div>
                <span class="label">Status:</span>
                <span class="value"><?php echo ucfirst($parcel['status']); ?></span>
            </div>
            <div>
                <span class="label">Date Sent:</span>
                <span class="value"><?php echo date('M d, Y g:i A', strtotime($parcel['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="cost">
            <div>Shipping Cost:</div>
            <div class="amount">KSh <?php echo number_format($parcel['cost'], 2); ?></div>
        </div>
        
        <div class="barcode">
            ||| <?php echo str_pad($parcel['parcel_no'], 20, '0', STR_PAD_LEFT); ?> |||
        </div>
        
        <div class="footer">
            <p><strong>Important:</strong></p>
            <p>• Keep this receipt for tracking</p>
            <p>• Parcel will be delivered within 24-48 hours</p>
            <p>• Contact receiver before delivery</p>
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
