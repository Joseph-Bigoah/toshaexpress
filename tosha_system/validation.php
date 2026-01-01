<?php
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

file_put_contents(
    $logDir . '/mpesa_validation.log',
    date('c') . ' ' . $rawInput . PHP_EOL,
    FILE_APPEND
);

// TODO: Inspect payload and validate the account/amount if needed.

echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Accepted'
]);

