<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get all buses with seat statistics
$query = "SELECT 
            b.*,
            COUNT(s.id) as total_seats,
            SUM(CASE WHEN s.status = 'available' THEN 1 ELSE 0 END) as available_seats,
            SUM(CASE WHEN s.status = 'booked' THEN 1 ELSE 0 END) as booked_seats,
            SUM(CASE WHEN s.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_seats
          FROM buses b 
          LEFT JOIN seats s ON b.id = s.bus_id 
          WHERE b.status = 'active'
          GROUP BY b.id 
          ORDER BY b.bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="seat-availability-widget">
    <h3>🪑 Seat Availability Overview</h3>
    <div class="buses-grid">
        <?php foreach ($buses as $bus): ?>
        <div class="bus-availability-card">
            <div class="bus-header">
                <h4><?php echo htmlspecialchars($bus['bus_name']); ?></h4>
                <span class="bus-plate"><?php echo htmlspecialchars($bus['plate_no']); ?></span>
            </div>
            
            <div class="seat-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $bus['available_seats']; ?></span>
                    <span class="stat-label">Available</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $bus['booked_seats']; ?></span>
                    <span class="stat-label">Booked</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $bus['maintenance_seats']; ?></span>
                    <span class="stat-label">Maintenance</span>
                </div>
            </div>
            
            <div class="availability-bar">
                <?php 
                $total = $bus['total_seats'];
                $available = $bus['available_seats'];
                $booked = $bus['booked_seats'];
                $maintenance = $bus['maintenance_seats'];
                
                if ($total > 0) {
                    $available_percent = ($available / $total) * 100;
                    $booked_percent = ($booked / $total) * 100;
                    $maintenance_percent = ($maintenance / $total) * 100;
                } else {
                    $available_percent = $booked_percent = $maintenance_percent = 0;
                }
                ?>
                <div class="bar-segment available" style="width: <?php echo $available_percent; ?>%"></div>
                <div class="bar-segment booked" style="width: <?php echo $booked_percent; ?>%"></div>
                <div class="bar-segment maintenance" style="width: <?php echo $maintenance_percent; ?>%"></div>
            </div>
            
            <div class="bus-actions">
                <a href="seat_management.php?bus_id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-primary">Manage Seats</a>
                <a href="ticket_booking.php?bus_id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-success">Book Ticket</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.seat-availability-widget {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.seat-availability-widget h3 {
    margin: 0 0 20px 0;
    color: #1a237e;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 10px;
}

.buses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.bus-availability-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.bus-availability-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.bus-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.bus-header h4 {
    margin: 0;
    color: #1a237e;
    font-size: 1.1rem;
}

.bus-plate {
    background: #e3f2fd;
    color: #0d1442;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
}

.seat-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #1a237e;
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
}

.availability-bar {
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 15px;
    display: flex;
}

.bar-segment {
    height: 100%;
    transition: width 0.3s ease;
}

.bar-segment.available {
    background: #28a745;
}

.bar-segment.booked {
    background: #dc3545;
}

.bar-segment.maintenance {
    background: #6c757d;
}

.bus-actions {
    display: flex;
    gap: 10px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-1px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #1e7e34;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .buses-grid {
        grid-template-columns: 1fr;
    }
    
    .seat-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .bus-actions {
        flex-direction: column;
    }
}
</style>
