<?php
/**
 * M-Pesa Connection Test Script
 * 
 * This script tests your M-Pesa API connection by attempting to get an access token.
 * Use this to verify your credentials are working correctly.
 */

require_once 'config/mpesa.php';

$mpesaConfig = require __DIR__ . '/config/mpesa.php';

$consumerKey = $mpesaConfig['consumer_key'];
$consumerSecret = $mpesaConfig['consumer_secret'];
$environment = strtolower($mpesaConfig['environment'] ?? 'sandbox');

$baseUrl = $environment === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke';

echo "<!DOCTYPE html>";
echo "<html><head><title>M-Pesa Connection Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #333; }
    h2 { color: #28a745; }
    h3 { color: #666; margin-top: 20px; }
    .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; }
    .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; }
    .info { color: #0c5460; background: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .config-info { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style></head><body>";
echo "<div class='container'>";
echo "<h1>🔌 M-Pesa API Connection Test</h1>";

// Display configuration
echo "<div class='config-info'>";
echo "<h3>Configuration:</h3>";
echo "<p><strong>Environment:</strong> " . htmlspecialchars($environment) . "</p>";
echo "<p><strong>Base URL:</strong> " . htmlspecialchars($baseUrl) . "</p>";
echo "<p><strong>Consumer Key:</strong> " . htmlspecialchars(substr($consumerKey, 0, 20)) . "...</p>";
echo "<p><strong>Consumer Secret:</strong> " . (empty($consumerSecret) ? '<span style="color:red;">NOT SET</span>' : '***' . substr($consumerSecret, -10)) . "</p>";
echo "</div>";

// Check if credentials are configured
if ($consumerKey === 'YOUR_CONSUMER_KEY' || $consumerSecret === 'YOUR_CONSUMER_SECRET' || empty($consumerKey) || empty($consumerSecret)) {
    echo "<div class='error'>";
    echo "<h2>❌ Credentials Not Configured</h2>";
    echo "<p>Please update <code>config/mpesa.php</code> with your actual Consumer Key and Consumer Secret from the Safaricom Developer Portal.</p>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

// Test connection
$url = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);

// Enhanced headers to mimic a real browser
$headers = [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/json',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
    'Cache-Control: max-age=0',
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL check for testing
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
$curlErrno = curl_errno($curl);

curl_close($curl);

echo "<h3>HTTP Status Code: <strong>$httpCode</strong></h3>";

if ($curlError) {
    echo "<div class='error'>";
    echo "<h2>❌ cURL Error</h2>";
    echo "<p><strong>Error Code:</strong> $curlErrno</p>";
    echo "<p><strong>Error Message:</strong> $curlError</p>";
    echo "</div>";
} else {
    // Try to parse JSON response
    $json = json_decode($response, true);
    
    if ($json && isset($json['access_token'])) {
        echo "<div class='success'>";
        echo "<h2>✅ SUCCESS! Access Token Received</h2>";
        echo "<p>Your M-Pesa API credentials are working correctly!</p>";
        echo "<p><strong>Access Token:</strong></p>";
        echo "<pre>" . htmlspecialchars($json['access_token']) . "</pre>";
        echo "<p><strong>Token Type:</strong> " . htmlspecialchars($json['expires_in'] ?? 'N/A') . " seconds</p>";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<h3>✅ Connection Test Passed</h3>";
        echo "<p>Your M-Pesa integration is properly configured. You can now use the M-Pesa payment button in the ticket booking system.</p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h2>❌ FAILED - Invalid Response</h2>";
        echo "<p>The API returned an unexpected response. This could mean:</p>";
        echo "<ul>";
        echo "<li>Invalid credentials (Consumer Key or Consumer Secret)</li>";
        echo "<li>API endpoint is blocked or unavailable</li>";
        echo "<li>Network connectivity issues</li>";
        echo "</ul>";
        echo "<p><strong>Response:</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        echo "</div>";
    }
}

echo "<div class='info'>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>If the test passed, try booking a ticket and using M-Pesa payment</li>";
echo "<li>If the test failed, verify your credentials in <code>config/mpesa.php</code></li>";
echo "<li>Check the logs in <code>logs/mpesa_requests.log</code> for detailed error information</li>";
echo "</ul>";
echo "</div>";

echo "</div></body></html>";
?>

