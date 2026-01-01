<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Optional filters
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
$params = [];
if (in_array($status, ['pending','approved','in_transit','delivered'])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($date_from) { $where[] = 'DATE(created_at) >= ?'; $params[] = $date_from; }
if ($date_to) { $where[] = 'DATE(created_at) <= ?'; $params[] = $date_to; }
$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "SELECT * FROM parcels $whereSQL ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$parcels) {
    die('No parcels found to print.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Print All Parcels</title>
    <style>
        @page { size: A4; margin: 0.5in; }
        body {
            font-family: Arial, sans-serif;
            margin: 0; padding: 0;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
            background-color: #FFD700;
            background-image:
                radial-gradient(rgba(13, 20, 66, 0.20) 2px, transparent 2px),
                radial-gradient(rgba(13, 20, 66, 0.12) 2px, transparent 2px);
            background-position: 0 0, 11px 11px;
            background-size: 22px 22px;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #FFC107, #B8860B);
            color: #fff; padding: 12px 16px; text-align: center; border-radius: 8px;
            margin: 12px;
        }
        .controls { position: fixed; top: 12px; right: 12px; background:#fff; padding:10px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); }
        .btn { background:#1a237e; color:#fff; border:none; padding:8px 12px; border-radius:5px; cursor:pointer; font-weight:bold; }
        .btn:hover { background:#0d1442; }
        .table-wrapper { padding: 16px; }
        table { width: 100%; border-collapse: collapse; background:#fff; border-radius:8px; overflow:hidden; }
        thead { background:#fff8e1; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; text-align: left; }
        .footer { text-align:center; font-size:10px; color:#555; margin: 16px; }
        @media print { .controls { display:none } }
    </style>
</head>
<body>
    <div class="controls">
        <button class="btn" onclick="window.print()">🖨️ Print</button>
        <button class="btn" onclick="window.close()">❌ Close</button>
    </div>

    <div class="header">
        <h2 style="margin:0;">TOSHA EXPRESS - Parcels</h2>
        <div style="font-size:12px;opacity:0.9;">All Parcels<?php echo $status? (' - '.strtoupper($status)) : ''; ?></div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Parcel No</th>
                    <th>Sender</th>
                    <th>Receiver</th>
                    <th>Route</th>
                    <th>Weight</th>
                    <th>Cost</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parcels as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['parcel_no']); ?></td>
                    <td><?php echo htmlspecialchars($p['sender_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['receiver_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['from_route'].' → '.$p['to_route']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($p['weight'],2)); ?> kg</td>
                    <td>KSh <?php echo htmlspecialchars(number_format($p['cost'],2)); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($p['status'])); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        Contact: 0722696460 • System developed by Joseph Bigoah Puok
    </div>

    <script>
        window.addEventListener('load', function(){ setTimeout(()=>window.print(), 600); });
    </script>
</body>
</html>

