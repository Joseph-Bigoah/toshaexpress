<?php
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

file_put_contents(
    $logDir . '/mpesa_confirmation.log',
    date('c') . ' ' . $rawInput . PHP_EOL,
    FILE_APPEND
);

// TODO: Parse payload and update ticket payment status accordingly.

echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Confirmation received successfully'
]);

