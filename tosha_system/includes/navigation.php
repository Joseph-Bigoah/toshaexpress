<?php
// Get current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

<div class="header">
    <div class="container">
        <h1>TOSHA EXPRESS</h1>
        <p class="motto">"Safest Mean Of Transport At Affordable Fares"</p>
    </div>
</div>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>TOSHA EXPRESS</h3>
        <p>Management System</p>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">📊 Dashboard</a></li>
            <li><a href="ticket_booking.php" class="<?php echo $current_page === 'ticket_booking.php' ? 'active' : ''; ?>">🎫 Book Ticket</a></li>
            <li><a href="manage_bookings.php" class="<?php echo $current_page === 'manage_bookings.php' ? 'active' : ''; ?>">📋 Manage Bookings</a></li>
            <li><a href="seat_management.php" class="<?php echo $current_page === 'seat_management.php' ? 'active' : ''; ?>">🪑 Seat Management</a></li>
            <li><a href="bus_management.php" class="<?php echo $current_page === 'bus_management.php' ? 'active' : ''; ?>">🚌 Bus Management</a></li>
            <li><a href="parcel_management.php" class="<?php echo $current_page === 'parcel_management.php' ? 'active' : ''; ?>">📦 Record Parcel</a></li>
            <li><a href="route_management.php" class="<?php echo $current_page === 'route_management.php' ? 'active' : ''; ?>">🛣️ Routes</a></li>
            <?php if (isAdmin()): ?>
            <li><a href="user_management.php" class="<?php echo $current_page === 'user_management.php' ? 'active' : ''; ?>">👥 Users</a></li>
            <?php endif; ?>
            <li><a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">📈 Reports</a></li>
            <li><a href="print_all_tickets_bulk.php" class="<?php echo $current_page === 'print_all_tickets_bulk.php' ? 'active' : ''; ?>">🖨️ Bulk Print</a></li>
            <li><a href="barcode_scanner.php" class="<?php echo $current_page === 'barcode_scanner.php' ? 'active' : ''; ?>">📱 Barcode Scanner</a></li>
            <li><a href="qr_scanner.php" class="<?php echo $current_page === 'qr_scanner.php' ? 'active' : ''; ?>">📱 QR Scanner</a></li>
            <li><a href="printer_config.php" class="<?php echo $current_page === 'printer_config.php' ? 'active' : ''; ?>">🖨️ Printer Config</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </nav>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !toggle.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
