<?php
/**
 * Simple QR Code Generator for TOSHA EXPRESS
 * Generates QR codes using Google Charts API
 */

function generateQRCode($data, $size = 200) {
    // Encode the data for URL
    $encodedData = urlencode($data);
    
    // Google Charts QR Code API
    $qrUrl = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}";
    
    return $qrUrl;
}

function generateTicketQRData($ticket) {
    // Create structured data for QR code
    $qrData = [
        'ticket_no' => $ticket['ticket_no'],
        'passenger' => $ticket['passenger_name'],
        'phone' => $ticket['phone'],
        'route' => $ticket['from_route'] . ' → ' . $ticket['to_route'],
        'bus' => $ticket['bus_name'] . ' (' . $ticket['plate_no'] . ')',
        'seat' => $ticket['seat_no'],
        'date' => $ticket['travel_date'],
        'time' => $ticket['travel_time'],
        'fare' => $ticket['fare'],
        'company' => 'TOSHA EXPRESS'
    ];
    
    // Convert to JSON for QR code
    return json_encode($qrData, JSON_PRETTY_PRINT);
}

function generateSimpleQRData($ticket) {
    // Simple text format for QR code
    $qrText = "TOSHA EXPRESS TICKET\n";
    $qrText .= "Ticket: " . $ticket['ticket_no'] . "\n";
    $qrText .= "Passenger: " . $ticket['passenger_name'] . "\n";
    $qrText .= "Phone: " . $ticket['phone'] . "\n";
    $qrText .= "Route: " . $ticket['from_route'] . " → " . $ticket['to_route'] . "\n";
    $qrText .= "Bus: " . $ticket['bus_name'] . " (" . $ticket['plate_no'] . ")\n";
    $qrText .= "Seat: " . $ticket['seat_no'] . "\n";
    $qrText .= "Date: " . date('M d, Y', strtotime($ticket['travel_date'])) . "\n";
    $qrText .= "Time: " . date('g:i A', strtotime($ticket['travel_time'])) . "\n";
    $qrText .= "Fare: KSh " . number_format($ticket['fare'], 2);
    
    return $qrText;
}
?>
