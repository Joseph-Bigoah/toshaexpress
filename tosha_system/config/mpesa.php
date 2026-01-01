<?php
/**
 * M-Pesa Configuration
 * 
 * To configure M-Pesa integration:
 * 
 * 1. For SANDBOX (Testing):
 *    - Visit: https://developer.safaricom.co.ke/
 *    - Create an account and app
 *    - Get your Consumer Key, Consumer Secret, and Passkey from the app dashboard
 *    - Use shortcode: 174379 (default sandbox shortcode)
 * 
 * 2. For PRODUCTION:
 *    - Contact Safaricom to get your production credentials
 *    - Update all values below with your production credentials
 *    - Change 'environment' to 'production'
 * 
 * 3. Callback URL:
 *    - For localhost: Use ngrok or similar tool to expose your local server
 *    - For production: Use your actual domain
 *    - Example: https://yourdomain.com/tosha_system/stk_callback.php
 */

// Auto-detect base URL for callback
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the script directory (config directory)
    $configDir = __DIR__;
    $rootDir = dirname($configDir);
    
    // Get document root
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $rootDirNormalized = str_replace('\\', '/', $rootDir);
    
    // Calculate relative path from document root
    if (strpos($rootDirNormalized, $docRoot) === 0) {
        $relativePath = substr($rootDirNormalized, strlen($docRoot));
        $relativePath = trim($relativePath, '/');
    } else {
        // Fallback: try to extract from SCRIPT_NAME
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        $relativePath = trim($scriptDir, '/');
        // Remove 'config' if present
        $relativePath = preg_replace('#/config$#', '', $relativePath);
    }
    
    // Construct URL
    if ($relativePath) {
        return $protocol . '://' . $host . '/' . trim($relativePath, '/');
    }
    
    return $protocol . '://' . $host;
}

// Get base URL (only if not set via environment variable)
$baseUrl = getBaseUrl();
$callbackUrl = rtrim($baseUrl, '/') . '/stk_callback.php';

// ⚠️ IMPORTANT: M-Pesa requires HTTPS and publicly accessible URLs
// For localhost testing, you MUST use ngrok or similar tool
// The callback URL must be accessible from the internet (not just localhost)
// Example: https://abc123.ngrok.io/tosha_system/stk_callback.php

return [
    // Environment: 'sandbox' for testing, 'production' for live
    'environment'       => getenv('MPESA_ENV') ?: 'sandbox',
    
    // ============================================================================
    // ⚠️ REQUIRED: Replace the placeholder values below with your actual credentials
    // Get them from: https://developer.safaricom.co.ke/ (after creating an app)
    // ============================================================================
    
    // M-Pesa API Credentials - Get these from https://developer.safaricom.co.ke/
    // ✅ Consumer Key configured
    'consumer_key'      => getenv('MPESA_CONSUMER_KEY') ?: 'JpnsgZt8RakOCHXdgitthIpbTFVr83vvZ4BvO3Lc06gwsrSB',
    
    // ✅ Consumer Secret configured
    'consumer_secret'   => getenv('MPESA_CONSUMER_SECRET') ?: 'CNdEKF6tv5vIG4Z76ABNYdUvCueh5XTVO11Ic8oHDbeObB6dugP6PKWZ9QLGdGMQ',
    
    // Shortcode: Must match the shortcode in your Safaricom Developer Portal app
    // For sandbox testing, common shortcodes are: 174379 or 174776
    // ⚠️ IMPORTANT: Check your app dashboard to get the correct shortcode
    // The shortcode must be the same as configured in your M-Pesa app
    'shortcode'         => getenv('MPESA_SHORTCODE') ?: '174379',
    
    // ✅ Passkey configured (default sandbox passkey)
    'passkey'           => getenv('MPESA_PASSKEY') ?: 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    
    // Callback URL: Using ngrok for localhost testing
    // ⚠️ IMPORTANT: Make sure ngrok is running and forwarding to port 80 (XAMPP default)
    // If your XAMPP uses a different port, update ngrok accordingly
    // Current ngrok URL: https://payably-hypereutectoid-krystyna.ngrok-free.dev
    'callback_url'      => getenv('MPESA_CALLBACK_URL') ?: 'https://payably-hypereutectoid-krystyna.ngrok-free.dev/tosha_system/stk_callback.php',
    
    // Account reference for transactions
    'account_reference' => getenv('MPESA_ACCOUNT_REFERENCE') ?: 'Tosha Express',
    
    // Transaction description
    'transaction_desc'  => getenv('MPESA_TRANSACTION_DESC') ?: 'Ticket Payment'
];

