<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$report_type = $_GET['type'] ?? 'daily';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get report data
$tickets_data = [];
$parcels_data = [];
$total_income = 0;

if ($report_type === 'daily') {
    $query = "SELECT COUNT(*) as count, SUM(fare) as total FROM tickets 
              WHERE DATE(created_at) = ? AND status = 'confirmed'";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from]);
    $tickets_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "SELECT COUNT(*) as count, SUM(cost) as total FROM parcels 
              WHERE DATE(created_at) = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from]);
    $parcels_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_income = ($tickets_data['total'] ?? 0) + ($parcels_data['total'] ?? 0);
} elseif ($report_type === 'weekly') {
    $query = "SELECT COUNT(*) as count, SUM(fare) as total FROM tickets 
              WHERE created_at >= DATE_SUB(?, INTERVAL 7 DAY) AND created_at <= ? AND status = 'confirmed'";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $tickets_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "SELECT COUNT(*) as count, SUM(cost) as total FROM parcels 
              WHERE created_at >= DATE_SUB(?, INTERVAL 7 DAY) AND created_at <= ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $parcels_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_income = ($tickets_data['total'] ?? 0) + ($parcels_data['total'] ?? 0);
} elseif ($report_type === 'monthly') {
    $query = "SELECT COUNT(*) as count, SUM(fare) as total FROM tickets 
              WHERE YEAR(created_at) = YEAR(?) AND MONTH(created_at) = MONTH(?) AND status = 'confirmed'";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_from]);
    $tickets_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "SELECT COUNT(*) as count, SUM(cost) as total FROM parcels 
              WHERE YEAR(created_at) = YEAR(?) AND MONTH(created_at) = MONTH(?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_from]);
    $parcels_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_income = ($tickets_data['total'] ?? 0) + ($parcels_data['total'] ?? 0);
}

// Get detailed data for table
$detailed_tickets = [];
$detailed_parcels = [];

if ($report_type === 'daily') {
    $query = "SELECT t.*, b.bus_name FROM tickets t 
              LEFT JOIN buses b ON t.bus_id = b.id 
              WHERE DATE(t.created_at) = ? AND t.status = 'confirmed'
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from]);
    $detailed_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $query = "SELECT * FROM parcels WHERE DATE(created_at) = ? ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from]);
    $detailed_parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($report_type === 'weekly') {
    $query = "SELECT t.*, b.bus_name FROM tickets t 
              LEFT JOIN buses b ON t.bus_id = b.id 
              WHERE t.created_at >= DATE_SUB(?, INTERVAL 7 DAY) AND t.created_at <= ? AND t.status = 'confirmed'
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $detailed_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $query = "SELECT * FROM parcels WHERE created_at >= DATE_SUB(?, INTERVAL 7 DAY) AND created_at <= ? ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $detailed_parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($report_type === 'monthly') {
    $query = "SELECT t.*, b.bus_name FROM tickets t 
              LEFT JOIN buses b ON t.bus_id = b.id 
              WHERE YEAR(t.created_at) = YEAR(?) AND MONTH(t.created_at) = MONTH(?) AND t.status = 'confirmed'
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_from]);
    $detailed_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $query = "SELECT * FROM parcels WHERE YEAR(created_at) = YEAR(?) AND MONTH(created_at) = MONTH(?) ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_from]);
    $detailed_parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">Generate Report</div>
            
            <form method="GET" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem; align-items: end;">
                    <div class="form-group">
                        <label for="type">Report Type:</label>
                        <select id="type" name="type" class="form-control" onchange="updateDateFields()">
                            <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">From Date:</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?php echo $date_from; ?>" required>
                    </div>
                    
                    <div class="form-group" id="date_to_group" style="<?php echo $report_type === 'weekly' ? '' : 'display: none;'; ?>">
                        <label for="date_to">To Date:</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>">
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" class="btn btn-success" onclick="printReport()">Print Report</button>
                    <button type="button" class="btn btn-warning" onclick="exportReport()">Export CSV</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <?php echo ucfirst($report_type); ?> Report Summary
                <span style="float: right; font-size: 0.9rem; font-weight: normal;">
                    <?php echo $report_type === 'weekly' ? $date_from . ' to ' . $date_to : $date_from; ?>
                </span>
            </div>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <h3><?php echo number_format($tickets_data['count'] ?? 0); ?></h3>
                    <p>Tickets Sold</p>
                </div>
                <div class="dashboard-card">
                    <h3><?php echo number_format($parcels_data['count'] ?? 0); ?></h3>
                    <p>Parcels Sent</p>
                </div>
                <div class="dashboard-card">
                    <h3>KSh <?php echo number_format($tickets_data['total'] ?? 0, 2); ?></h3>
                    <p>Ticket Revenue</p>
                </div>
                <div class="dashboard-card">
                    <h3>KSh <?php echo number_format($parcels_data['total'] ?? 0, 2); ?></h3>
                    <p>Parcel Revenue</p>
                </div>
                <div class="dashboard-card" style="grid-column: 1/-1; background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                    <h3>KSh <?php echo number_format($total_income, 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div class="card">
                <div class="card-header">Ticket Details</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Passenger</th>
                                <th>Route</th>
                                <th>Fare</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailed_tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['ticket_no']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['passenger_name']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['from_route'] . ' → ' . $ticket['to_route']); ?></td>
                                <td>KSh <?php echo number_format($ticket['fare'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Parcel Details</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Parcel No</th>
                                <th>Sender</th>
                                <th>Route</th>
                                <th>Cost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailed_parcels as $parcel): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($parcel['parcel_no']); ?></td>
                                <td><?php echo htmlspecialchars($parcel['sender_name']); ?></td>
                                <td><?php echo htmlspecialchars($parcel['from_route'] . ' → ' . $parcel['to_route']); ?></td>
                                <td>KSh <?php echo number_format($parcel['cost'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($parcel['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .table-responsive {
            overflow-x: auto;
        }
    </style>

    <script>
        function updateDateFields() {
            const reportType = document.getElementById('type').value;
            const dateToGroup = document.getElementById('date_to_group');
            
            if (reportType === 'weekly') {
                dateToGroup.style.display = 'block';
                document.getElementById('date_to').required = true;
            } else {
                dateToGroup.style.display = 'none';
                document.getElementById('date_to').required = false;
            }
        }
        
        function printReport() {
            window.print();
        }
        
        function exportReport() {
            // Create CSV content
            let csvContent = "TOSHA EXPRESS - <?php echo ucfirst($report_type); ?> Report\n";
            csvContent += "Date: <?php echo $date_from; ?><?php echo $report_type === 'weekly' ? ' to ' . $date_to : ''; ?>\n\n";
            
            csvContent += "SUMMARY\n";
            csvContent += "Tickets Sold,<?php echo $tickets_data['count'] ?? 0; ?>\n";
            csvContent += "Parcels Sent,<?php echo $parcels_data['count'] ?? 0; ?>\n";
            csvContent += "Ticket Revenue,KSh <?php echo number_format($tickets_data['total'] ?? 0, 2); ?>\n";
            csvContent += "Parcel Revenue,KSh <?php echo number_format($parcels_data['total'] ?? 0, 2); ?>\n";
            csvContent += "Total Revenue,KSh <?php echo number_format($total_income, 2); ?>\n\n";
            
            csvContent += "TICKET DETAILS\n";
            csvContent += "Ticket No,Passenger,Route,Fare,Date\n";
            <?php foreach ($detailed_tickets as $ticket): ?>
            csvContent += "<?php echo $ticket['ticket_no']; ?>,<?php echo $ticket['passenger_name']; ?>,<?php echo $ticket['from_route'] . ' → ' . $ticket['to_route']; ?>,KSh <?php echo number_format($ticket['fare'], 2); ?>,<?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>\n";
            <?php endforeach; ?>
            
            csvContent += "\nPARCEL DETAILS\n";
            csvContent += "Parcel No,Sender,Route,Cost,Date\n";
            <?php foreach ($detailed_parcels as $parcel): ?>
            csvContent += "<?php echo $parcel['parcel_no']; ?>,<?php echo $parcel['sender_name']; ?>,<?php echo $parcel['from_route'] . ' → ' . $parcel['to_route']; ?>,KSh <?php echo number_format($parcel['cost'], 2); ?>,<?php echo date('M d, Y', strtotime($parcel['created_at'])); ?>\n";
            <?php endforeach; ?>
            
            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'tosha_express_<?php echo $report_type; ?>_report_<?php echo $date_from; ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
