<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();

header('Content-Type: application/json');

try {
    $mpesaConfig = require __DIR__ . '/config/mpesa.php';
    
    $consumerKey    = $mpesaConfig['consumer_key'] ?? '';
    $consumerSecret = $mpesaConfig['consumer_secret'] ?? '';
    $shortcode      = $mpesaConfig['shortcode'] ?? '';
    $passkey        = $mpesaConfig['passkey'] ?? '';
    $callbackUrl    = $mpesaConfig['callback_url'] ?? '';
    $accountRef     = $mpesaConfig['account_reference'] ?? 'Tosha Express';
    $transactionDesc= $mpesaConfig['transaction_desc'] ?? 'Parcel Payment';
    $environment    = strtolower($mpesaConfig['environment'] ?? 'sandbox');
    
    if (
        !$consumerKey || $consumerKey === 'YOUR_CONSUMER_KEY' ||
        !$consumerSecret || $consumerSecret === 'YOUR_CONSUMER_SECRET' ||
        !$passkey || $passkey === 'YOUR_PASSKEY'
    ) {
        $setupGuide = file_exists(__DIR__ . '/MPESA_SETUP.md') 
            ? ' See MPESA_SETUP.md for detailed setup instructions.'
            : ' Visit https://developer.safaricom.co.ke/ to get your credentials.';
        throw new Exception(
            'M-Pesa credentials are not configured. ' .
            'Please update config/mpesa.php with your Consumer Key, Consumer Secret, and Passkey from the Safaricom Developer Portal.' .
            $setupGuide
        );
    }
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!is_array($data)) {
        throw new Exception('Invalid request payload.');
    }
    
    // Remove spaces and formatting characters before validation
    $phone = preg_replace('/[\s\-\(\)]/', '', trim($data['phone'] ?? ''));
    $amount = (float)($data['amount'] ?? 0);
    $senderName = trim($data['sender_name'] ?? '');
    $route = trim($data['route'] ?? '');
    
    // Validate Kenyan phone number: accepts 07XXXXXXXX or 01XXXXXXXX
    // Formats: 07XXXXXXXX, 01XXXXXXXX, +2547XXXXXXXX, +2541XXXXXXXX, 2547XXXXXXXX, 2541XXXXXXXX
    // Pattern: 0 followed by 7 or 1, then 8 digits (local) OR 254 followed by 7 or 1, then 8 digits (international)
    if ($phone === '' || !preg_match('/^(?:\+?254[17]|0[17])\d{8}$/', $phone)) {
        throw new Exception('Invalid phone number format. Please use 07XXXXXXXX or 01XXXXXXXX format.');
    }
    
    if ($amount <= 0) {
        throw new Exception('Invalid amount specified.');
    }
    
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $msisdn = formatMsisdn($phone);
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $baseUrl = $environment === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';

    // Validate callback URL
    if (strpos($callbackUrl, 'localhost') !== false || strpos($callbackUrl, '127.0.0.1') !== false) {
        throw new Exception(
            'Invalid Callback URL: M-Pesa requires a publicly accessible HTTPS URL. ' .
            'For localhost testing, use ngrok or similar tool. ' .
            'Current URL: ' . $callbackUrl . '. ' .
            'See MPESA_SETUP.md for instructions on setting up ngrok.'
        );
    }
    
    if (strpos($callbackUrl, 'http://') === 0) {
        throw new Exception(
            'Invalid Callback URL: M-Pesa requires HTTPS (not HTTP). ' .
            'Current URL: ' . $callbackUrl . '. ' .
            'Please use an HTTPS URL or set up ngrok for localhost testing.'
        );
    }
    
    $accessToken = fetchAccessToken($baseUrl, $consumerKey, $consumerSecret);
    
    $payload = [
        'BusinessShortCode' => (int)$shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int)ceil($amount),
        'PartyA'            => (int)$msisdn,
        'PartyB'            => (int)$shortcode,
        'PhoneNumber'       => (int)$msisdn,
        'CallBackURL'       => $callbackUrl,
        'AccountReference'  => mb_substr($accountRef ?: $route ?: 'Parcel', 0, 12),
        'TransactionDesc'   => mb_substr($transactionDesc ?: $route ?: 'Parcel Payment', 0, 32)
    ];
    
    $stkResponse = sendStkPush($baseUrl, $accessToken, $payload);
    
    $logEntry = [
        'timestamp' => date('c'),
        'phone' => $msisdn,
        'amount' => $amount,
        'sender_name' => $senderName,
        'route' => $route,
        'type' => 'parcel',
        'user' => $_SESSION['username'] ?? 'unknown',
        'payload' => $payload,
        'response' => $stkResponse
    ];
    
    file_put_contents(
        $logDir . '/mpesa_parcel_requests.log',
        json_encode($logEntry) . PHP_EOL,
        FILE_APPEND
    );
    
    echo json_encode([
        'success' => true,
        'message' => $stkResponse['CustomerMessage'] ?? 'M-Pesa STK push initiated. Awaiting customer confirmation.',
        'CheckoutRequestID' => $stkResponse['CheckoutRequestID'] ?? null,
        'MerchantRequestID' => $stkResponse['MerchantRequestID'] ?? null
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function formatMsisdn(string $phone): string
{
    // Remove all non-digit characters
    $digits = preg_replace('/\D+/', '', $phone);
    
    // Already in international format (254XXXXXXXXX) - 12 digits
    if (strlen($digits) === 12 && strpos($digits, '254') === 0) {
        return $digits;
    }
    
    // Local format starting with 0 (07XXXXXXXX or 01XXXXXXXX) - 10 digits
    // Convert: 07XXXXXXXX -> 2547XXXXXXXX, 01XXXXXXXX -> 2541XXXXXXXX
    if (strlen($digits) === 10 && strpos($digits, '0') === 0) {
        return '254' . substr($digits, 1);
    }
    
    // Format without leading 0 (7XXXXXXXX or 1XXXXXXXX) - 9 digits
    // Convert: 7XXXXXXXX -> 2547XXXXXXXX, 1XXXXXXXX -> 2541XXXXXXXX
    if (strlen($digits) === 9 && (strpos($digits, '7') === 0 || strpos($digits, '1') === 0)) {
        return '254' . $digits;
    }
    
    // If it's already 12 digits, return as is (might be 254XXXXXXXXX)
    if (strlen($digits) === 12) {
        return $digits;
    }
    
    // Fallback: try to convert any 10-digit number starting with 0
    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '254' . substr($digits, 1);
    }
    
    // Fallback: try to convert any 9-digit number starting with 7 or 1
    if (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
        return '254' . $digits;
    }
    
    return $digits;
}

function fetchAccessToken(string $baseUrl, string $consumerKey, string $consumerSecret): string
{
    $url = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    
    // Enhanced headers to mimic a real browser and avoid API blocking
    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Cache-Control: max-age=0',
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for testing (enable in production)
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Failed to obtain M-Pesa access token (HTTP ' . $httpCode . '): ' . $error);
    }
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (empty($decoded['access_token'])) {
        $errorMsg = 'Invalid response while obtaining access token (HTTP ' . $httpCode . ')';
        if (isset($decoded['errorMessage'])) {
            $errorMsg .= ': ' . $decoded['errorMessage'];
        } else {
            $errorMsg .= '. Response: ' . substr($response, 0, 200);
        }
        throw new Exception($errorMsg);
    }
    
    return $decoded['access_token'];
}

function sendStkPush(string $baseUrl, string $accessToken, array $payload): array
{
    $url = $baseUrl . '/mpesa/stkpush/v1/processrequest';
    
    // Enhanced headers to mimic a real browser and avoid API blocking
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
        'Cache-Control: no-cache',
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for testing (enable in production)
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Failed to initiate STK Push (HTTP ' . $httpCode . '): ' . $error);
    }
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new Exception('Invalid STK Push response (HTTP ' . $httpCode . '): ' . substr($response, 0, 200));
    }
    
    if (($decoded['ResponseCode'] ?? '') !== '0') {
        $errorMessage = $decoded['errorMessage'] ?? $decoded['CustomerMessage'] ?? $decoded['ResponseDescription'] ?? 'Unknown STK push error';
        throw new Exception($errorMessage);
    }
    
    return $decoded;
}

