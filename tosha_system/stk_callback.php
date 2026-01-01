<?php
/**
 * M-Pesa STK Push Callback Handler
 * 
 * This endpoint receives payment confirmations from M-Pesa.
 * When a customer completes payment, M-Pesa sends a callback here.
 * 
 * WHERE PAYMENTS GO:
 * - Payments are sent to your Business Shortcode (configured in config/mpesa.php)
 * - For sandbox: Shortcode 174379 (or your configured shortcode)
 * - For production: Your registered business shortcode
 * - You need to set up a PayBill account with Safaricom to receive real money
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

// Log the callback
$logData = [
    'timestamp' => date('c'),
    'payload'   => $payload,
    'raw'       => $rawInput
];

file_put_contents(
    $logDir . '/mpesa_stk_callback.log',
    json_encode($logData) . PHP_EOL,
    FILE_APPEND
);

// Initialize response
$response = [
    'ResultCode' => 0,
    'ResultDesc' => 'Callback received successfully'
];

try {
    // Check if this is a valid M-Pesa callback
    if (!is_array($payload) || !isset($payload['Body'])) {
        throw new Exception('Invalid callback payload structure');
    }
    
    $body = $payload['Body'];
    $stkCallback = $body['stkCallback'] ?? null;
    
    if (!$stkCallback) {
        throw new Exception('Missing stkCallback in payload');
    }
    
    $merchantRequestID = $stkCallback['MerchantRequestID'] ?? '';
    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? '';
    $resultCode = $stkCallback['ResultCode'] ?? -1;
    $resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown';
    
    // Check if payment was successful
    if ($resultCode == 0) {
        // Payment successful - extract transaction details
        $callbackMetadata = $stkCallback['CallbackMetadata'] ?? [];
        $items = $callbackMetadata['Item'] ?? [];
        
        $transactionData = [];
        foreach ($items as $item) {
            $transactionData[$item['Name']] = $item['Value'] ?? '';
        }
        
        $mpesaReceiptNumber = $transactionData['MpesaReceiptNumber'] ?? '';
        $phoneNumber = $transactionData['PhoneNumber'] ?? '';
        $amount = $transactionData['Amount'] ?? 0;
        $transactionDate = $transactionData['TransactionDate'] ?? '';
        
        // Update ticket or parcel status in database
        if (!empty($phoneNumber) && $amount > 0) {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                // Format phone number (remove country code if present)
                $phone = preg_replace('/^254/', '0', $phoneNumber);
                $phone = preg_replace('/[^0-9]/', '', $phone);
                
                // First, try to find matching ticket
                // Look for tickets created in the last 30 minutes with matching amount
                $query = "SELECT * FROM tickets 
                         WHERE phone LIKE ? 
                         AND fare = ? 
                         AND payment_method = 'mpesa'
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                         ORDER BY created_at DESC 
                         LIMIT 1";
                
                $stmt = $db->prepare($query);
                $stmt->execute(["%$phone%", $amount]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ticket) {
                    // Update ticket with payment confirmation
                    // Add mpesa_receipt column if it doesn't exist
                    try {
                        $db->exec("ALTER TABLE tickets ADD COLUMN mpesa_receipt VARCHAR(50) AFTER payment_method");
                    } catch (Exception $e) {
                        // Column might already exist, ignore
                    }
                    
                    // Update ticket with M-Pesa receipt number
                    $updateQuery = "UPDATE tickets 
                                   SET mpesa_receipt = ?, 
                                       status = 'confirmed',
                                       payment_method = 'mpesa'
                                   WHERE id = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([$mpesaReceiptNumber, $ticket['id']]);
                    
                    // Log successful payment processing
                    $paymentLog = [
                        'timestamp' => date('c'),
                        'type' => 'ticket',
                        'ticket_id' => $ticket['id'],
                        'ticket_no' => $ticket['ticket_no'],
                        'mpesa_receipt' => $mpesaReceiptNumber,
                        'phone' => $phoneNumber,
                        'amount' => $amount,
                        'status' => 'processed'
                    ];
                    
                    file_put_contents(
                        $logDir . '/mpesa_payments.log',
                        json_encode($paymentLog) . PHP_EOL,
                        FILE_APPEND
                    );
                } else {
                    // Ticket not found, try to find matching parcel using sender phone
                    // Look for parcels created in the last 30 minutes with matching amount
                    $parcelQuery = "SELECT * FROM parcels 
                                   WHERE sender_phone LIKE ? 
                                   AND cost = ? 
                                   AND payment_method = 'mpesa'
                                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                                   ORDER BY created_at DESC 
                                   LIMIT 1";
                    
                    $parcelStmt = $db->prepare($parcelQuery);
                    $parcelStmt->execute(["%$phone%", $amount]);
                    $parcel = $parcelStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($parcel) {
                        // Ensure payment columns exist
                        try {
                            $db->exec("ALTER TABLE parcels ADD COLUMN payment_method ENUM('cash','mpesa') DEFAULT 'cash' AFTER cost");
                        } catch (Exception $e) {
                            // Column might already exist, ignore
                        }
                        
                        try {
                            $db->exec("ALTER TABLE parcels ADD COLUMN mpesa_receipt VARCHAR(50) AFTER payment_method");
                        } catch (Exception $e) {
                            // Column might already exist, ignore
                        }
                        
                        // Update parcel with M-Pesa receipt number
                        $updateParcelQuery = "UPDATE parcels 
                                             SET mpesa_receipt = ?, 
                                                 payment_method = 'mpesa',
                                                 status = 'approved'
                                             WHERE id = ?";
                        $updateParcelStmt = $db->prepare($updateParcelQuery);
                        $updateParcelStmt->execute([$mpesaReceiptNumber, $parcel['id']]);
                        
                        // Log successful parcel payment processing
                        $paymentLog = [
                            'timestamp' => date('c'),
                            'type' => 'parcel',
                            'parcel_id' => $parcel['id'],
                            'parcel_no' => $parcel['parcel_no'],
                            'mpesa_receipt' => $mpesaReceiptNumber,
                            'phone' => $phoneNumber,
                            'amount' => $amount,
                            'status' => 'processed'
                        ];
                        
                        file_put_contents(
                            $logDir . '/mpesa_payments.log',
                            json_encode($paymentLog) . PHP_EOL,
                            FILE_APPEND
                        );
                    } else {
                        // Neither ticket nor parcel found - log for manual review
                        $paymentLog = [
                            'timestamp' => date('c'),
                            'mpesa_receipt' => $mpesaReceiptNumber,
                            'phone' => $phoneNumber,
                            'amount' => $amount,
                            'status' => 'not_found',
                            'note' => 'Payment received but no matching ticket or parcel found'
                        ];
                        
                        file_put_contents(
                            $logDir . '/mpesa_payments.log',
                            json_encode($paymentLog) . PHP_EOL,
                            FILE_APPEND
                        );
                    }
                }
            }
        }
        
        $response['ResultDesc'] = 'Payment processed successfully';
    } else {
        // Payment failed or cancelled
        $response['ResultDesc'] = 'Payment ' . strtolower($resultDesc);
        
        // Log failed payment
        $paymentLog = [
            'timestamp' => date('c'),
            'checkout_request_id' => $checkoutRequestID,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'status' => 'failed'
        ];
        
        file_put_contents(
            $logDir . '/mpesa_payments.log',
            json_encode($paymentLog) . PHP_EOL,
            FILE_APPEND
        );
    }
    
} catch (Exception $e) {
    // Log error but still return success to M-Pesa (to avoid retries)
    $errorLog = [
        'timestamp' => date('c'),
        'error' => $e->getMessage(),
        'payload' => $payload
    ];
    
    file_put_contents(
        $logDir . '/mpesa_callback_errors.log',
        json_encode($errorLog) . PHP_EOL,
        FILE_APPEND
    );
    
    $response['ResultDesc'] = 'Callback received with errors: ' . $e->getMessage();
}

// Always return success to M-Pesa (to acknowledge receipt)
echo json_encode($response);

