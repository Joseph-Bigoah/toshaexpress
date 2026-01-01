# How to Find Your M-Pesa Passkey

## Current Status
✅ Consumer Key: Configured  
✅ Consumer Secret: Configured  
❌ Passkey: **STILL NEEDED**

## Steps to Find Your Passkey

### Method 1: In Your App Dashboard

1. **Log in** to https://developer.safaricom.co.ke/
2. Go to **"My Apps"** section
3. **Click on your app** (the one you created)
4. Look for one of these sections:
   - **"App Settings"** or **"Settings"**
   - **"Credentials"** or **"API Credentials"**
   - **"Lipa na M-Pesa Online"** section
   - **"STK Push Settings"**

5. In that section, you should see:
   - **"Passkey"** or **"Lipa na M-Pesa Online Passkey"**
   - It's usually a **long string** (64+ characters)
   - Example format: `bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919`

### Method 2: Generate Passkey (If Not Visible)

If you don't see a Passkey:

1. In your app dashboard, look for:
   - **"Generate Passkey"** button
   - **"Create Passkey"** option
   - **"Lipa na M-Pesa Online"** → **"Generate"**

2. Click to generate a new Passkey
3. **Copy the generated Passkey** immediately (you might not be able to see it again)

### Method 3: Check App Configuration

1. Go to your app's **"Configuration"** tab
2. Look for **"Lipa na M-Pesa Online"** settings
3. The Passkey should be listed there

### Method 4: For Sandbox Testing

If you're using the **sandbox environment** and can't find a Passkey:

1. Some sandbox apps might use a **default test passkey**
2. Check the Safaricom Developer Portal documentation
3. Or contact Safaricom Developer Support: developer@safaricom.co.ke

## What the Passkey Looks Like

The Passkey is typically:
- **64 characters long** (hexadecimal)
- Contains only: **letters (a-f)** and **numbers (0-9)**
- Example: `bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919`

## Once You Have the Passkey

1. Open `config/mpesa.php`
2. Find line 83:
   ```php
   'passkey' => getenv('MPESA_PASSKEY') ?: 'YOUR_PASSKEY',
   ```
3. Replace `'YOUR_PASSKEY'` with your actual Passkey:
   ```php
   'passkey' => getenv('MPESA_PASSKEY') ?: 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
   ```
4. **Save the file**
5. The error should disappear!

## Still Can't Find It?

If you've checked all the above and still can't find your Passkey:

1. **Contact Safaricom Developer Support:**
   - Email: developer@safaricom.co.ke
   - Portal: https://developer.safaricom.co.ke/support

2. **Check the Documentation:**
   - https://developer.safaricom.co.ke/APIs/M-PesaExpress
   - Look for "Passkey" or "Lipa na M-Pesa Online" section

3. **Verify Your App Type:**
   - Make sure your app has **"M-Pesa Express (STK Push)"** API enabled
   - The Passkey is only available for apps with STK Push enabled

## Quick Checklist

- [ ] Logged into Safaricom Developer Portal
- [ ] Opened your app
- [ ] Checked "App Settings"
- [ ] Checked "Credentials" section
- [ ] Checked "Lipa na M-Pesa Online" section
- [ ] Looked for "Generate Passkey" button
- [ ] Contacted support if still not found

