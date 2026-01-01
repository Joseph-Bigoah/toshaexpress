<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics
$stats = [];

// Total tickets sold
$query = "SELECT COUNT(*) as total FROM tickets WHERE status = 'confirmed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_tickets'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total parcels sent
$query = "SELECT COUNT(*) as total FROM parcels";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_parcels'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Daily income
$query = "SELECT SUM(fare) as total FROM tickets WHERE DATE(created_at) = CURDATE() AND status = 'confirmed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['daily_income'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Active buses
$query = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_buses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h3><?php echo number_format($stats['total_tickets']); ?></h3>
                <p>Total Tickets Sold</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo number_format($stats['total_parcels']); ?></h3>
                <p>Total Parcels Sent</p>
            </div>
            <div class="dashboard-card">
                <h3>KSh <?php echo number_format($stats['daily_income'], 2); ?></h3>
                <p>Daily Income</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $stats['active_buses']; ?></h3>
                <p>Active Buses</p>
            </div>
        </div>

        <?php include 'seat_availability_widget.php'; ?>

        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
                <a href="ticket_booking.php" class="btn btn-primary">Book New Ticket</a>
                <a href="parcel_management.php" class="btn btn-success">Record New Parcel</a>
                <a href="manage_bookings.php" class="btn btn-warning">Manage Bookings</a>
                <a href="print_all_tickets_bulk.php" class="btn btn-info">🖨️ Bulk Print</a>
                <a href="qr_scanner.php" class="btn btn-secondary">QR Scanner</a>
                <a href="reports.php" class="btn btn-info">View Reports</a>
                <a href="bus_management.php" class="btn btn-danger">Manage Buses</a>
            </div>
        </div>
    </div>

    <style>
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background:rgba(202, 139, 2, 0.89); color: black; }
        .badge-info { background: #17a2b8; color: white; }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</body>
</html>
