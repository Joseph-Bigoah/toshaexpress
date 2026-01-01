<?php
header('Content-Type: application/json');

$mpesaConfig = require __DIR__ . '/config/mpesa.php';

$consumerKey    = $mpesaConfig['consumer_key'] ?? '';
$consumerSecret = $mpesaConfig['consumer_secret'] ?? '';
$shortcode      = $mpesaConfig['shortcode'] ?? '';
$passkey        = $mpesaConfig['passkey'] ?? '';
$callbackUrl    = $mpesaConfig['callback_url'] ?? '';
$environment    = strtolower($mpesaConfig['environment'] ?? 'sandbox');

if (
    !$consumerKey || $consumerKey === 'YOUR_CONSUMER_KEY' ||
    !$consumerSecret || $consumerSecret === 'YOUR_CONSUMER_SECRET' ||
    !$passkey || $passkey === 'YOUR_PASSKEY'
) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configure your M-Pesa credentials in config/mpesa.php or environment variables before running STK push.'
    ]);
    exit;
}

$msisdn   = formatMsisdn($_GET['phone'] ?? '254708374149');
$amount   = (int)ceil((float)($_GET['amount'] ?? 1));
$accountRef = substr($_GET['account_ref'] ?? ($mpesaConfig['account_reference'] ?? 'Test Payment'), 0, 12);
$trxDesc  = substr($_GET['desc'] ?? ($mpesaConfig['transaction_desc'] ?? 'STK Push Test'), 0, 32);

$timestamp = date('YmdHis');
$password  = base64_encode($shortcode . $passkey . $timestamp);
$baseUrl   = $environment === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke';

try {
    $accessToken = fetchAccessToken($baseUrl, $consumerKey, $consumerSecret);
    
    $payload = [
        'BusinessShortCode' => (int)$shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => $amount,
        'PartyA'            => (int)$msisdn,
        'PartyB'            => (int)$shortcode,
        'PhoneNumber'       => (int)$msisdn,
        'CallBackURL'       => $callbackUrl,
        'AccountReference'  => $accountRef,
        'TransactionDesc'   => $trxDesc,
    ];
    
    $response = sendStkPush($baseUrl, $accessToken, $payload);
    
    echo json_encode([
        'success' => true,
        'payload' => $payload,
        'response'=> $response
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function formatMsisdn(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    
    if (strpos($digits, '254') === 0) {
        return $digits;
    }
    
    if (strpos($digits, '0') === 0) {
        return '254' . substr($digits, 1);
    }
    
    if (strpos($digits, '7') === 0) {
        return '254' . $digits;
    }
    
    return $digits;
}

function fetchAccessToken(string $baseUrl, string $consumerKey, string $consumerSecret): string
{
    $url = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Failed to obtain M-Pesa access token: ' . $error);
    }
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (empty($decoded['access_token'])) {
        throw new Exception('Invalid response while obtaining access token: ' . $response);
    }
    
    return $decoded['access_token'];
}

function sendStkPush(string $baseUrl, string $accessToken, array $payload): array
{
    $url = $baseUrl . '/mpesa/stkpush/v1/processrequest';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Failed to initiate STK Push: ' . $error);
    }
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new Exception('Invalid STK Push response: ' . $response);
    }
    
    if (($decoded['ResponseCode'] ?? '') !== '0') {
        $errorMessage = $decoded['errorMessage'] ?? $decoded['CustomerMessage'] ?? $decoded['ResponseDescription'] ?? 'Unknown STK push error';
        throw new Exception($errorMessage);
    }
    
    return $decoded;
}

