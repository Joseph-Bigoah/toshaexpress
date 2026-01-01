<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all buses from database
$query = "SELECT * FROM buses WHERE status = 'active' ORDER BY bus_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected bus or default to first bus
$selected_bus_id = $_GET['bus_id'] ?? ($buses[0]['id'] ?? '');
$seats = [];

if ($selected_bus_id) {
    $query = "SELECT seat_no, status FROM seats WHERE bus_id = ? ORDER BY seat_no";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_bus_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bus details
    $query = "SELECT * FROM buses WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_bus_id]);
    $selected_bus = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOSHA EXPRESS - Seat Map Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1e3c72 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .demo-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
        }
        
        .bus-layout {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
        }
        
        .bus-body {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border: 3px solid #1e3c72;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .seat-map {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .seat {
            width: 50px;
            height: 50px;
            border: 2px solid #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: bold;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .seat.available {
            background: linear-gradient(145deg, #e9ecef, #dee2e6);
            color: #495057;
            border-color: #adb5bd;
        }
        
        .seat.available:hover {
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }
        
        .seat.booked {
            background: linear-gradient(145deg, #28a745, #20c997);
            color: white;
            cursor: not-allowed;
            border-color: #1e7e34;
        }
        
        .seat.selected {
            background: linear-gradient(145deg, #1a237e, #0d1442);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            border-color: #bd2130;
        }
        
        .seat-legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .legend-color {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: 2px solid #ddd;
        }
        
        .bus-info {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            border-left: 5px solid #1e3c72;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #1e3c72;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .demo-controls {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #1a237e, #0d1442);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <div class="header">
            <h1>TOSHA EXPRESS</h1>
            <p style="margin: 10px 0 0 0; font-style: italic;">"Safest Mean Of Transport At Affordable Fares"</p>
            <h2 style="margin: 20px 0 0 0;">Bus Seat Map - Real Bus Data</h2>
            
            <div style="margin: 20px 0;">
                <label for="busSelect" style="color: white; font-weight: bold; margin-right: 10px;">Select Bus:</label>
                <select id="busSelect" onchange="changeBus()" style="padding: 8px 15px; border-radius: 5px; border: none; font-size: 16px;">
                    <?php foreach ($buses as $bus): ?>
                    <option value="<?php echo $bus['id']; ?>" <?php echo ($selected_bus_id == $bus['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bus['bus_name'] . ' - ' . $bus['plate_no']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (isset($selected_bus)): ?>
            <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px; margin-top: 15px;">
                <h3 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($selected_bus['bus_name']); ?></h3>
                <p style="margin: 5px 0;"><strong>Plate Number:</strong> <?php echo htmlspecialchars($selected_bus['plate_no']); ?></p>
                <p style="margin: 5px 0;"><strong>Driver:</strong> <?php echo htmlspecialchars($selected_bus['driver_name']); ?></p>
                <p style="margin: 5px 0;"><strong>Color:</strong> <?php echo htmlspecialchars($selected_bus['color']); ?></p>
                <p style="margin: 5px 0;"><strong>Capacity:</strong> <?php echo $selected_bus['capacity']; ?> seats</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="bus-layout">
            <div class="bus-body">
                <div class="seat-map" id="seatMap">
                    <?php if (!empty($seats)): ?>
                        <?php foreach ($seats as $seat): ?>
                        <div class="seat <?php echo $seat['status']; ?>" 
                             data-seat="<?php echo $seat['seat_no']; ?>"
                             onclick="toggleSeat(this)">
                            <?php echo $seat['seat_no']; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; grid-column: 1/-1; padding: 20px;">
                            No seats found for this bus. Please select a different bus.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="seat-legend">
            <div class="legend-item">
                <div class="legend-color available"></div>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <div class="legend-color selected"></div>
                <span>Selected</span>
            </div>
            <div class="legend-item">
                <div class="legend-color booked"></div>
                <span>Booked</span>
            </div>
        </div>
        
        <div class="bus-info">
            <h3>Bus Configuration</h3>
            <p><strong>Total Seats:</strong> 60 | <strong>Layout:</strong> 4 columns × 15 rows | <strong>Capacity:</strong> 60 passengers</p>
            <p><strong>Seat Numbering:</strong> S01 to S60 (Left to Right, Top to Bottom)</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="totalSeats">60</div>
                <div class="stat-label">Total Seats</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="availableSeats">60</div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="selectedSeats">0</div>
                <div class="stat-label">Selected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="bookedSeats">0</div>
                <div class="stat-label">Booked</div>
            </div>
        </div>
        
        <div class="demo-controls">
            <button class="btn" onclick="randomBookSeats()">Random Book 10 Seats</button>
            <button class="btn btn-danger" onclick="clearAllSeats()">Clear All</button>
            <button class="btn btn-success" onclick="bookSelectedSeats()">Book Selected</button>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Selected Seats:</strong> <span id="selectedSeatsList">None</span></p>
            <p style="color: #6c757d; font-size: 0.9rem;">
                Click on available seats to select them. Selected seats will be booked when you click "Book Selected".
            </p>
        </div>
    </div>

    <script>
        let selectedSeats = [];
        let bookedSeats = [];
        
        // Initialize with real data
        function initializeSeats() {
            const seats = document.querySelectorAll('.seat');
            selectedSeats = [];
            bookedSeats = [];
            
            seats.forEach(seat => {
                if (seat.classList.contains('booked')) {
                    bookedSeats.push(seat.dataset.seat);
                }
            });
            
            updateStats();
        }
        
        function changeBus() {
            const busId = document.getElementById('busSelect').value;
            window.location.href = `?bus_id=${busId}`;
        }
        
        function toggleSeat(seatElement) {
            const seatNo = seatElement.dataset.seat;
            
            if (seatElement.classList.contains('booked')) {
                return; // Can't select booked seats
            }
            
            if (seatElement.classList.contains('selected')) {
                // Deselect seat
                seatElement.classList.remove('selected');
                seatElement.classList.add('available');
                selectedSeats = selectedSeats.filter(seat => seat !== seatNo);
            } else {
                // Select seat
                seatElement.classList.remove('available');
                seatElement.classList.add('selected');
                selectedSeats.push(seatNo);
            }
            
            updateSelectedSeats();
            updateStats();
        }
        
        function updateSelectedSeats() {
            const selectedSeatsList = document.getElementById('selectedSeatsList');
            if (selectedSeats.length > 0) {
                selectedSeatsList.textContent = selectedSeats.join(', ');
            } else {
                selectedSeatsList.textContent = 'None';
            }
        }
        
        function updateStats() {
            const totalSeats = document.querySelectorAll('.seat').length;
            const availableCount = document.querySelectorAll('.seat.available').length;
            const selectedCount = selectedSeats.length;
            const bookedCount = document.querySelectorAll('.seat.booked').length;
            
            document.getElementById('totalSeats').textContent = totalSeats;
            document.getElementById('availableSeats').textContent = availableCount;
            document.getElementById('selectedSeats').textContent = selectedCount;
            document.getElementById('bookedSeats').textContent = bookedCount;
        }
        
        function randomBookSeats() {
            const availableSeats = document.querySelectorAll('.seat.available');
            const seatsToBook = Math.min(10, availableSeats.length);
            
            for (let i = 0; i < seatsToBook; i++) {
                const randomIndex = Math.floor(Math.random() * availableSeats.length);
                const seat = availableSeats[randomIndex];
                const seatNo = seat.dataset.seat;
                
                seat.classList.remove('available');
                seat.classList.add('booked');
                bookedSeats.push(seatNo);
            }
            
            updateStats();
        }
        
        function clearAllSeats() {
            const allSeats = document.querySelectorAll('.seat');
            allSeats.forEach(seat => {
                seat.classList.remove('selected', 'booked');
                seat.classList.add('available');
            });
            
            selectedSeats = [];
            bookedSeats = [];
            updateSelectedSeats();
            updateStats();
        }
        
        function bookSelectedSeats() {
            if (selectedSeats.length === 0) {
                alert('Please select at least one seat to book.');
                return;
            }
            
            selectedSeats.forEach(seatNo => {
                const seat = document.querySelector(`[data-seat="${seatNo}"]`);
                seat.classList.remove('selected');
                seat.classList.add('booked');
                bookedSeats.push(seatNo);
            });
            
            selectedSeats = [];
            updateSelectedSeats();
            updateStats();
            
            alert(`Successfully booked ${bookedSeats.length} seats!`);
        }
        
        // Initialize the seat map with real data
        initializeSeats();
    </script>
</body>
</html>
