# M-Pesa Integration Setup Guide

This guide will help you configure M-Pesa STK Push payments for TOSHA EXPRESS.

## Prerequisites

1. A Safaricom Developer Account
2. An M-Pesa App registered on the Safaricom Developer Portal
3. For localhost testing: ngrok or similar tunneling tool

## Step 1: Create Safaricom Developer Account

1. Visit: https://developer.safaricom.co.ke/
2. Click "Get Started" or "Sign Up"
3. Complete the registration process
4. Verify your email address

## Step 2: Create an App

1. Log in to the Safaricom Developer Portal
2. Go to "My Apps" section
3. Click "Create App"
4. Fill in the app details:
   - App Name: TOSHA EXPRESS (or your preferred name)
   - Description: Bus ticket payment system
5. Select the following APIs:
   - M-Pesa Express (STK Push)
6. Save the app

## Step 3: Get Your Credentials

After creating the app, you'll get:

1. **Consumer Key** - Found in the app dashboard
2. **Consumer Secret** - Found in the app dashboard (click "Show" to reveal)
3. **Passkey** - Found in the app dashboard under "App Settings" or "Credentials"
4. **Shortcode** - For sandbox, use: `174379`

## Step 4: Configure the System

Edit the file: `config/mpesa.php`

Replace the placeholder values:

```php
'consumer_key'      => 'YOUR_ACTUAL_CONSUMER_KEY',
'consumer_secret'   => 'YOUR_ACTUAL_CONSUMER_SECRET',
'passkey'           => 'YOUR_ACTUAL_PASSKEY',
```

### Example:

```php
'consumer_key'      => 'abc123xyz456',
'consumer_secret'   => 'def789uvw012',
'passkey'           => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
```

## Step 5: Configure Callback URL

### For Localhost Testing:

1. Install ngrok: https://ngrok.com/
2. Start your XAMPP server
3. Run ngrok: `ngrok http 80` (or your Apache port)
4. Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)
5. Update `config/mpesa.php`:

```php
'callback_url' => 'https://abc123.ngrok.io/tosha_system/stk_callback.php',
```

**Important:** The callback URL must be HTTPS and publicly accessible.

### For Production:

Update the callback URL to your production domain:

```php
'callback_url' => 'https://yourdomain.com/tosha_system/stk_callback.php',
```

## Step 6: Test the Integration

1. Make sure you're using sandbox mode: `'environment' => 'sandbox'`
2. Use a test phone number registered with Safaricom Developer Portal
3. Try booking a ticket and selecting M-Pesa payment
4. Check the logs in `logs/mpesa_requests.log` for debugging

## Sandbox Test Credentials

For testing purposes, Safaricom provides:

- **Shortcode**: `174379`
- **Test Phone Numbers**: Register test numbers in the Developer Portal
- **Test Amounts**: Use amounts between KSh 1 and KSh 70,000

## Production Setup

When ready for production:

1. Contact Safaricom to get production credentials
2. Update `config/mpesa.php`:
   - Change `'environment' => 'production'`
   - Update all credentials with production values
   - Update callback URL to production domain
   - Update shortcode to your business shortcode

## Troubleshooting

### Error: "M-Pesa credentials are not configured"

- Make sure you've replaced `YOUR_CONSUMER_KEY`, `YOUR_CONSUMER_SECRET`, and `YOUR_PASSKEY` in `config/mpesa.php`
- Check that the credentials are correct (no extra spaces)

### Error: "Failed to obtain M-Pesa access token"

- Verify your Consumer Key and Consumer Secret are correct
- Check your internet connection
- Ensure the Safaricom API is accessible

### Error: "Invalid STK Push response"

- Check that your Passkey is correct
- Verify your shortcode matches your app configuration
- Ensure callback URL is publicly accessible (HTTPS)

### STK Push not received on phone

- Verify the phone number format (should be 07XXXXXXXX or +2547XXXXXXXX)
- Check that the phone number is registered for testing (sandbox)
- Ensure the phone has M-Pesa registered and active

## Security Notes

⚠️ **Important Security Considerations:**

1. **Never commit credentials to version control**
   - Add `config/mpesa.php` to `.gitignore`
   - Use environment variables for production

2. **Use environment variables (Recommended for Production):**

Set these in your server environment:
```bash
export MPESA_CONSUMER_KEY="your_key"
export MPESA_CONSUMER_SECRET="your_secret"
export MPESA_PASSKEY="your_passkey"
export MPESA_SHORTCODE="your_shortcode"
export MPESA_CALLBACK_URL="https://yourdomain.com/stk_callback.php"
```

3. **Keep credentials secure**
   - Don't share credentials
   - Rotate credentials regularly
   - Use different credentials for sandbox and production

## Support

For M-Pesa API issues:
- Safaricom Developer Portal: https://developer.safaricom.co.ke/
- API Documentation: https://developer.safaricom.co.ke/APIs/M-PesaExpress
- Support: developer@safaricom.co.ke

## Files Involved

- `config/mpesa.php` - Configuration file
- `initiate_mpesa.php` - Initiates STK Push
- `stk_callback.php` - Handles M-Pesa callbacks
- `logs/mpesa_requests.log` - Request logs
- `logs/mpesa_stk_callback.log` - Callback logs

