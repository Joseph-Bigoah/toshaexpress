# Fix "Merchant does not exist" Error

## Problem
The error "Merchant does not exist" means the **shortcode** in your configuration doesn't match the shortcode configured in your Safaricom Developer Portal app.

## Solution: Find the Correct Shortcode

### Step 1: Log into Safaricom Developer Portal
1. Go to: https://developer.safaricom.co.ke/
2. Log in with your account

### Step 2: Find Your App's Shortcode
1. Go to **"My Apps"** section
2. Click on your app (the one you're using for M-Pesa)
3. Look for one of these sections:
   - **"App Settings"**
   - **"Configuration"**
   - **"Lipa na M-Pesa Online"**
   - **"STK Push Settings"**

4. Find the **Shortcode** or **Business Shortcode** field
   - It's usually a 6-digit number
   - Common sandbox shortcodes: `174379` or `174776`

### Step 3: Update Your Configuration

Open `config/mpesa.php` and find line 84:

```php
'shortcode' => getenv('MPESA_SHORTCODE') ?: '174379',
```

Replace `174379` with the shortcode from your Developer Portal app.

**Example:**
If your app shows shortcode `174776`, update to:
```php
'shortcode' => getenv('MPESA_SHORTCODE') ?: '174776',
```

### Step 4: Verify All Settings Match

Make sure these match between your config and Developer Portal:

1. ✅ **Consumer Key** - Should match
2. ✅ **Consumer Secret** - Should match  
3. ✅ **Shortcode** - **MUST MATCH** (this is causing the error)
4. ✅ **Passkey** - Should match
5. ✅ **Environment** - Should be 'sandbox' for testing

## Common Sandbox Shortcodes

- **174379** - Most common sandbox shortcode
- **174776** - Alternative sandbox shortcode
- Your app might have a different one - **check your Developer Portal**

## Quick Fix

If you're not sure which shortcode to use, try these in order:

1. **First try:** `174379` (most common)
   ```php
   'shortcode' => getenv('MPESA_SHORTCODE') ?: '174379',
   ```

2. **If that doesn't work, try:** `174776`
   ```php
   'shortcode' => getenv('MPESA_SHORTCODE') ?: '174776',
   ```

3. **Best solution:** Check your Developer Portal and use the exact shortcode shown there

## Verify Your Configuration

After updating, test again:
1. Try booking a ticket with M-Pesa payment
2. The error should be resolved if the shortcode matches

## Still Getting the Error?

If you've updated the shortcode and still get the error:

1. **Double-check the shortcode** in Developer Portal
2. **Verify the Consumer Key and Secret** are correct
3. **Make sure you're using sandbox environment** (not production)
4. **Check that your app has STK Push enabled** in the Developer Portal
5. **Contact Safaricom Developer Support** if the issue persists

## Need Help?

- Safaricom Developer Portal: https://developer.safaricom.co.ke/
- Support Email: developer@safaricom.co.ke
- API Documentation: https://developer.safaricom.co.ke/APIs/M-PesaExpress

