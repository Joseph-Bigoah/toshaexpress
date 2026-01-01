<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$bus_id = $_GET['bus_id'] ?? '';

$database = new Database();
$db = $database->getConnection();

$bus = null;
$seats = [];

try {
    if ($bus_id) {
        $q = $db->prepare("SELECT * FROM buses WHERE id = ?");
        $q->execute([$bus_id]);
        $bus = $q->fetch(PDO::FETCH_ASSOC);

        if ($bus) {
            // Fetch seats with most recent confirmed ticket (if any)
            $sql = "SELECT s.seat_no, s.status, tt.ticket_no, tt.passenger_name, tt.from_route, tt.to_route
                    FROM seats s
                    LEFT JOIN (
                        SELECT t1.seat_no, t1.bus_id, t1.ticket_no, t1.passenger_name, t1.from_route, t1.to_route, t1.created_at
                        FROM tickets t1
                        INNER JOIN (
                            SELECT seat_no, bus_id, MAX(created_at) AS latest
                            FROM tickets
                            WHERE status = 'confirmed'
                            GROUP BY seat_no, bus_id
                        ) t2 ON t1.seat_no = t2.seat_no AND t1.bus_id = t2.bus_id AND t1.created_at = t2.latest
                    ) tt ON tt.seat_no = s.seat_no AND tt.bus_id = s.bus_id
                    WHERE s.bus_id = ?
                    ORDER BY s.seat_no";
            $q = $db->prepare($sql);
            $q->execute([$bus_id]);
            $seats = $q->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    die('Error fetching seats: ' . $e->getMessage());
}

if (!$bus) {
    die('No bus selected or bus not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Print Seats (<?php echo htmlspecialchars($bus['bus_name']); ?>)</title>
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
        .controls { position: fixed; top: 12px; right: 12px; background:#fff; padding:10px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); }
        .btn { background:#1a237e; color:#fff; border:none; padding:8px 12px; border-radius:5px; cursor:pointer; font-weight:bold; }
        .btn:hover { background:#0d1442; }
        .header { background: linear-gradient(135deg, #FFC107, #B8860B); color:#fff; padding:12px 16px; margin:12px; border-radius:8px; text-align:center; }
        .meta { font-size:12px; opacity:0.9; }
        .table-wrap { padding: 16px; }
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
        <h2 style="margin:0;">TOSHA EXPRESS - Seats</h2>
        <div class="meta">Bus: <?php echo htmlspecialchars($bus['bus_name']); ?> • Plate: <?php echo htmlspecialchars($bus['plate_no']); ?> • Driver: <?php echo htmlspecialchars($bus['driver_name']); ?></div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Seat No</th>
                    <th>Passenger</th>
                    <th>Ticket No</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seats as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['seat_no']); ?></td>
                    <td><?php echo htmlspecialchars($s['passenger_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['ticket_no'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['from_route'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['to_route'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($s['status'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">Contact: 0722696460 • System developed by Joseph Bigoah Puok</div>

    <script>
        window.addEventListener('load', function(){ setTimeout(()=>window.print(), 600); });
    </script>
</body>
</html>

