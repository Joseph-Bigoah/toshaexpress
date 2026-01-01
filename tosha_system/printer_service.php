<?php
/**
 * TOSHA EXPRESS Printer Service
 * Handles different types of external printers
 */

class PrinterService {
    private $printer_config;
    
    public function __construct() {
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $config_file = 'config/printer_settings.json';
        if (file_exists($config_file)) {
            $this->printer_config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->printer_config = [
                'name' => 'TOSHA Express Printer',
                'type' => 'thermal',
                'port' => 'usb',
                'ip' => '',
                'baud_rate' => 9600,
                'paper_width' => 80
            ];
        }
    }
    
    public function printTicket($ticket_data) {
        switch ($this->printer_config['type']) {
            case 'thermal':
                return $this->printThermalTicket($ticket_data);
            case 'dot_matrix':
                return $this->printDotMatrixTicket($ticket_data);
            case 'network':
                return $this->printNetworkTicket($ticket_data);
            default:
                return $this->printStandardTicket($ticket_data);
        }
    }
    
    private function printThermalTicket($ticket) {
        $data = '';
        
        // ESC/POS commands for thermal printers
        $data .= "\x1B\x40"; // Initialize printer
        $data .= "\x1B\x61\x01"; // Center alignment
        $data .= "\x1B\x21\x30"; // Double height, double width
        $data .= "TOSHA EXPRESS\n";
        $data .= "\x1B\x21\x00"; // Normal text
        $data .= "Safest Mean Of Transport\n";
        $data .= "At Affordable Fares\n";
        $data .= "\x1B\x61\x00"; // Left alignment
        $data .= "================================\n";
        $data .= "Ticket No: " . $ticket['ticket_no'] . "\n";
        $data .= "Passenger: " . $ticket['passenger_name'] . "\n";
        $data .= "Phone: " . $ticket['phone'] . "\n";
        $data .= "Route: " . $ticket['from_route'] . " → " . $ticket['to_route'] . "\n";
        $data .= "Bus: " . $ticket['bus_name'] . " (" . $ticket['plate_no'] . ")\n";
        $data .= "Driver: " . $ticket['driver_name'] . "\n";
        $data .= "Seat No: " . $ticket['seat_no'] . "\n";
        $data .= "Travel Date: " . date('M d, Y', strtotime($ticket['travel_date'])) . "\n";
        $data .= "Travel Time: " . date('g:i A', strtotime($ticket['travel_time'])) . "\n";
        $data .= "Payment Method: " . $this->formatPaymentDisplay($ticket['payment_method'] ?? null) . "\n";
        $data .= "Booking Date: " . date('M d, Y g:i A', strtotime($ticket['created_at'])) . "\n";
        $data .= "================================\n";
        $data .= "Fare Amount: KSh " . number_format($ticket['fare'], 2) . "\n";
        $data .= "================================\n";
        $data .= "Important:\n";
        $data .= "• Arrive 30 minutes before departure\n";
        $data .= "• Keep ticket safe for boarding\n";
        $data .= "• No refunds for no-shows\n";
        $data .= "• Contact: +254 700 000 000\n";
        $data .= "Thank you for choosing TOSHA EXPRESS!\n";
        $data .= "================================\n";
        $data .= "\n\n\n"; // Extra paper feed
        $data .= "\x1D\x56\x00"; // Cut paper
        
        return $this->sendToPrinter($data);
    }
    
    private function printDotMatrixTicket($ticket) {
        $data = '';
        
        // IBM Proprinter commands
        $data .= "\x1B\x40"; // Initialize
        $data .= "\x1B\x61\x01"; // Center
        $data .= "\x1B\x45\x01"; // Bold
        $data .= "TOSHA EXPRESS\n";
        $data .= "\x1B\x45\x00"; // Normal
        $data .= "Safest Mean Of Transport At Affordable Fares\n";
        $data .= "\x1B\x61\x00"; // Left align
        $data .= "================================\n";
        $data .= "Ticket No: " . $ticket['ticket_no'] . "\n";
        $data .= "Passenger: " . $ticket['passenger_name'] . "\n";
        $data .= "Phone: " . $ticket['phone'] . "\n";
        $data .= "Route: " . $ticket['from_route'] . " → " . $ticket['to_route'] . "\n";
        $data .= "Bus: " . $ticket['bus_name'] . " (" . $ticket['plate_no'] . ")\n";
        $data .= "Driver: " . $ticket['driver_name'] . "\n";
        $data .= "Seat No: " . $ticket['seat_no'] . "\n";
        $data .= "Travel Date: " . date('M d, Y', strtotime($ticket['travel_date'])) . "\n";
        $data .= "Travel Time: " . date('g:i A', strtotime($ticket['travel_time'])) . "\n";
        $data .= "Payment Method: " . $this->formatPaymentDisplay($ticket['payment_method'] ?? null) . "\n";
        $data .= "Booking Date: " . date('M d, Y g:i A', strtotime($ticket['created_at'])) . "\n";
        $data .= "================================\n";
        $data .= "Fare Amount: KSh " . number_format($ticket['fare'], 2) . "\n";
        $data .= "================================\n";
        $data .= "Important:\n";
        $data .= "• Arrive 30 minutes before departure\n";
        $data .= "• Keep ticket safe for boarding\n";
        $data .= "• No refunds for no-shows\n";
        $data .= "• Contact: +254 700 000 000\n";
        $data .= "Thank you for choosing TOSHA EXPRESS!\n";
        $data .= "================================\n";
        $data .= "\n\n\n"; // Extra paper feed
        $data .= "\x0C"; // Form feed
        
        return $this->sendToPrinter($data);
    }
    
    private function printNetworkTicket($ticket) {
        $data = $this->generatePlainTextTicket($ticket);
        
        if (!empty($this->printer_config['ip'])) {
            return $this->sendToNetworkPrinter($data);
        } else {
            return $this->sendToPrinter($data);
        }
    }
    
    private function printStandardTicket($ticket) {
        $data = $this->generatePlainTextTicket($ticket);
        return $this->sendToPrinter($data);
    }
    
    private function generatePlainTextTicket($ticket) {
        $data = '';
        $data .= "================================\n";
        $data .= "        TOSHA EXPRESS\n";
        $data .= "  Safest Mean Of Transport\n";
        $data .= "      At Affordable Fares\n";
        $data .= "================================\n\n";
        $data .= "Ticket No: " . $ticket['ticket_no'] . "\n";
        $data .= "Passenger: " . $ticket['passenger_name'] . "\n";
        $data .= "Phone: " . $ticket['phone'] . "\n";
        $data .= "Route: " . $ticket['from_route'] . " → " . $ticket['to_route'] . "\n";
        $data .= "Bus: " . $ticket['bus_name'] . " (" . $ticket['plate_no'] . ")\n";
        $data .= "Driver: " . $ticket['driver_name'] . "\n";
        $data .= "Seat No: " . $ticket['seat_no'] . "\n";
        $data .= "Travel Date: " . date('M d, Y', strtotime($ticket['travel_date'])) . "\n";
        $data .= "Travel Time: " . date('g:i A', strtotime($ticket['travel_time'])) . "\n";
        $data .= "Payment Method: " . $this->formatPaymentDisplay($ticket['payment_method'] ?? null) . "\n";
        $data .= "Booking Date: " . date('M d, Y g:i A', strtotime($ticket['created_at'])) . "\n\n";
        $data .= "Fare Amount: KSh " . number_format($ticket['fare'], 2) . "\n\n";
        $data .= "================================\n";
        $data .= "Important:\n";
        $data .= "• Arrive 30 minutes before departure\n";
        $data .= "• Keep ticket safe for boarding\n";
        $data .= "• No refunds for no-shows\n";
        $data .= "• Contact: +254 700 000 000\n";
        $data .= "Thank you for choosing TOSHA EXPRESS!\n";
        $data .= "================================\n";
        $data .= "\n\n\n"; // Extra paper feed
        
        return $data;
    }
    
    private function formatPaymentDisplay($value) {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return 'NOT SPECIFIED';
        }
        $normalized = strtolower($raw);
        if ($normalized === 'mpesa' || $normalized === 'm-pesa') {
            return 'M-PESA';
        }
        if ($normalized === 'cash') {
            return 'CASH';
        }
        return strtoupper($raw);
    }
    
    private function sendToPrinter($data) {
        switch ($this->printer_config['port']) {
            case 'usb':
                return $this->sendToUSBPrinter($data);
            case 'serial':
                return $this->sendToSerialPrinter($data);
            case 'parallel':
                return $this->sendToParallelPrinter($data);
            case 'network':
                return $this->sendToNetworkPrinter($data);
            default:
                return $this->sendToDefaultPrinter($data);
        }
    }
    
    private function sendToUSBPrinter($data) {
        // For USB printers, we'll use system print command
        $temp_file = tempnam(sys_get_temp_dir(), 'tosha_ticket_');
        file_put_contents($temp_file, $data);
        
        $command = '';
        if (PHP_OS_FAMILY === 'Windows') {
            $command = "copy \"$temp_file\" \"PRN\"";
        } else {
            $command = "lp \"$temp_file\"";
        }
        
        $result = shell_exec($command);
        unlink($temp_file);
        
        return $result !== null;
    }
    
    private function sendToSerialPrinter($data) {
        // Serial printer implementation
        $port = $this->printer_config['port'] ?? 'COM1';
        $baud_rate = $this->printer_config['baud_rate'] ?? 9600;
        
        // This would require a serial communication library
        // For now, we'll use a simple file output
        $log_file = 'logs/printer_' . date('Y-m-d') . '.log';
        file_put_contents($log_file, $data . "\n", FILE_APPEND);
        
        return true;
    }
    
    private function sendToParallelPrinter($data) {
        // Parallel printer implementation
        $port = $this->printer_config['port'] ?? 'LPT1';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $handle = fopen($port, 'w');
            if ($handle) {
                fwrite($handle, $data);
                fclose($handle);
                return true;
            }
        }
        
        return false;
    }
    
    private function sendToNetworkPrinter($data) {
        $ip = $this->printer_config['ip'];
        $port = 9100; // Standard printer port
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }
        
        $result = socket_connect($socket, $ip, $port);
        if ($result === false) {
            socket_close($socket);
            return false;
        }
        
        socket_write($socket, $data, strlen($data));
        socket_close($socket);
        
        return true;
    }
    
    private function sendToDefaultPrinter($data) {
        // Default printer - save to file for manual printing
        $log_file = 'logs/printer_' . date('Y-m-d_H-i-s') . '.txt';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, $data);
        
        return true;
    }
    
    public function getPrinterStatus() {
        // Check if printer is available
        switch ($this->printer_config['port']) {
            case 'usb':
                return $this->checkUSBPrinter();
            case 'serial':
                return $this->checkSerialPrinter();
            case 'network':
                return $this->checkNetworkPrinter();
            default:
                return true; // Assume available
        }
    }
    
    private function checkUSBPrinter() {
        // Check if USB printer is connected
        if (PHP_OS_FAMILY === 'Windows') {
            $result = shell_exec('wmic printer get name,status');
            return strpos($result, 'Ready') !== false;
        } else {
            $result = shell_exec('lpstat -p');
            return !empty($result);
        }
    }
    
    private function checkSerialPrinter() {
        // Check if serial port is available
        $port = $this->printer_config['port'] ?? 'COM1';
        return file_exists($port);
    }
    
    private function checkNetworkPrinter() {
        $ip = $this->printer_config['ip'];
        if (empty($ip)) {
            return false;
        }
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }
        
        $result = @socket_connect($socket, $ip, 9100);
        socket_close($socket);
        
        return $result !== false;
    }
}
?>
