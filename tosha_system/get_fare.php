<?php
require_once 'config/database.php';

header('Content-Type: application/json');

$from_route = $_GET['from'] ?? '';
$to_route = $_GET['to'] ?? '';

if (empty($from_route) || empty($to_route)) {
    echo json_encode(['success' => false, 'message' => 'Missing route parameters']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Get fare for the route
    $query = "SELECT fare FROM routes WHERE start_point = ? AND destination = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$from_route, $to_route]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($route) {
        echo json_encode([
            'success' => true,
            'fare' => $route['fare'],
            'from' => $from_route,
            'to' => $to_route
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Route not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
