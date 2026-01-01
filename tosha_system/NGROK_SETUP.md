# Setting Up Ngrok for M-Pesa Callback URL

## Problem
M-Pesa requires a **publicly accessible HTTPS URL** for the callback. If you're testing on localhost, you'll get the error:
```
Error: Bad Request - Invalid CallBackURL
```

## Solution: Use Ngrok

Ngrok creates a secure tunnel from a public HTTPS URL to your localhost server.

## Step 1: Install Ngrok

### Option A: Download from Website
1. Visit: https://ngrok.com/download
2. Download for your operating system (Mac, Windows, Linux)
3. Extract the ngrok executable

### Option B: Install via Package Manager

**Mac (Homebrew):**
```bash
brew install ngrok/ngrok/ngrok
```

**Windows (Chocolatey):**
```bash
choco install ngrok
```

**Linux:**
```bash
# Download and extract
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz
tar -xzf ngrok-v3-stable-linux-amd64.tgz
```

## Step 2: Sign Up for Free Account (Optional but Recommended)

1. Go to: https://dashboard.ngrok.com/signup
2. Create a free account
3. Get your authtoken from the dashboard
4. Run: `ngrok config add-authtoken YOUR_AUTH_TOKEN`

This gives you:
- Stable URLs (won't change on restart)
- More control over your tunnels
- Better for development

## Step 3: Start Your XAMPP Server

1. Open XAMPP Control Panel
2. Start **Apache** server
3. Note the port (usually **80**)

## Step 4: Start Ngrok

Open a terminal/command prompt and run:

```bash
ngrok http 80
```

Or if your Apache is on a different port:
```bash
ngrok http 8080
```

You'll see output like:
```
ngrok                                                                              
                                                                                   
Session Status                online                                              
Account                       Your Name (Plan: Free)                               
Version                       3.x.x                                                
Region                        United States (us)                                   
Latency                       -                                                    
Web Interface                 http://127.0.0.1:4040                                
Forwarding                    https://abc123xyz.ngrok-free.app -> http://localhost:80
                                                                                   
Connections                   ttl     opn     rt1     rt5     p50     p90          
                              0       0       0.00    0.00    0.00    0.00         
```

## Step 5: Copy Your Ngrok URL

From the output above, copy the **HTTPS URL**:
```
https://abc123xyz.ngrok-free.app
```

## Step 6: Update M-Pesa Configuration

### Option A: Update config/mpesa.php Directly

Open `config/mpesa.php` and find line 85:

```php
'callback_url' => getenv('MPESA_CALLBACK_URL') ?: $callbackUrl,
```

Replace with your ngrok URL:

```php
'callback_url' => getenv('MPESA_CALLBACK_URL') ?: 'https://abc123xyz.ngrok-free.app/tosha_system/stk_callback.php',
```

**Important:** Replace `abc123xyz.ngrok-free.app` with your actual ngrok URL!

### Option B: Use Environment Variable

Set the environment variable:

```bash
export MPESA_CALLBACK_URL=https://abc123xyz.ngrok-free.app/tosha_system/stk_callback.php
```

## Step 7: Test the Callback URL

1. Open your browser
2. Go to: `https://your-ngrok-url.ngrok-free.app/tosha_system/stk_callback.php`
3. You should see: `{"ResultCode":0,"ResultDesc":"Callback received successfully"}`

If you see this, your callback URL is working!

## Step 8: Test M-Pesa Payment

1. Go to your ticket booking page
2. Select route and seats
3. Choose "M-Pesa (STK Push)"
4. Enter phone number
5. Click "Pay with M-Pesa"

The payment should now work!

## Important Notes

### Free Ngrok URLs Change
- **Free accounts:** The URL changes every time you restart ngrok
- **Solution:** Sign up for a free account and use authtoken for stable URLs
- Or update the callback URL in config each time

### Ngrok Must Be Running
- Keep ngrok running while testing M-Pesa
- If you close ngrok, the callback URL won't work
- Keep the terminal window open

### Testing on Different Devices
- The ngrok URL works from anywhere (not just localhost)
- You can test on your phone using the ngrok URL
- Example: `https://abc123.ngrok-free.app/tosha_system/`

## Troubleshooting

### Error: "Invalid CallBackURL"
- Make sure ngrok is running
- Verify the URL in config/mpesa.php matches your ngrok URL
- Ensure the URL is HTTPS (not HTTP)
- Check that the path includes `/tosha_system/stk_callback.php`

### Error: "Connection Refused"
- Make sure XAMPP Apache is running
- Verify ngrok is forwarding to the correct port
- Check that your firewall isn't blocking connections

### Callback Not Received
- Check `logs/mpesa_stk_callback.log` for incoming callbacks
- Verify ngrok is still running
- Make sure the callback URL is publicly accessible

## Alternative: Use a Production Server

If you have a production server:
1. Deploy your code to the server
2. Use your domain's HTTPS URL
3. Update callback URL to: `https://yourdomain.com/tosha_system/stk_callback.php`

## Quick Reference

```bash
# Start ngrok
ngrok http 80

# Get your URL from the output
# Update config/mpesa.php with: https://YOUR-URL.ngrok-free.app/tosha_system/stk_callback.php
```

## Need Help?

- Ngrok Documentation: https://ngrok.com/docs
- Ngrok Dashboard: https://dashboard.ngrok.com/
- M-Pesa Setup Guide: See MPESA_SETUP.md

