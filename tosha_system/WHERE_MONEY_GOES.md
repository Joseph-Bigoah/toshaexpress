# Where Does the Money Go? - M-Pesa Payment Explanation

## Quick Answer

**The money goes to your Business Shortcode (PayBill number) - NOT a phone number.**

You **DO NOT need to provide a phone number**. The shortcode you configured IS your payment account.

## How It Works

### 1. What Happens When Customer Enters PIN

```
Customer enters PIN
    ↓
M-Pesa processes payment
    ↓
Money is sent to: Business Shortcode 174379 (your configured shortcode)
    ↓
Payment appears in your M-Pesa Business account
```

### 2. Your Current Configuration

**Shortcode:** `174379` (configured in `config/mpesa.php`)

This shortcode is:
- ✅ Your payment receiving account
- ✅ Where all M-Pesa payments go
- ✅ Already configured - no phone number needed

## For Sandbox (Testing) - Current Setup

**Shortcode: 174379**

- This is a **test/sandbox shortcode**
- Payments are **simulated** (no real money)
- Used for testing the payment flow
- You can test without any account setup

## For Production (Real Money) - What You Need

### Option 1: Use Existing PayBill Account

If you already have a PayBill account with Safaricom:

1. **Get your Business Shortcode:**
   - This is your PayBill number (e.g., 123456)
   - You should already have this from Safaricom

2. **Update config/mpesa.php:**
   ```php
   'shortcode' => 'YOUR_PAYBILL_NUMBER',  // e.g., '123456'
   'environment' => 'production',
   ```

3. **Link to your app:**
   - In Safaricom Developer Portal
   - Link your PayBill number to your M-Pesa app
   - Configure callback URLs

### Option 2: Register New PayBill Account

If you don't have a PayBill account:

1. **Contact Safaricom Business Support:**
   - Phone: 100 (Safaricom customer care)
   - Visit: Safaricom Business Center
   - Website: https://www.safaricom.co.ke/business/

2. **Register Your Business:**
   - Provide business registration documents
   - Get your Business Shortcode (PayBill number)
   - Set up M-Pesa Business account

3. **Configure in System:**
   - Update `config/mpesa.php` with your shortcode
   - Set environment to 'production'
   - Update all credentials

## Important: No Phone Number Needed!

❌ **You DO NOT need to provide:**
- Your personal phone number
- A receiving phone number
- Any additional account number

✅ **You ONLY need:**
- Business Shortcode (PayBill number)
- This is already configured in your system

## How to Check Where Money Goes

### In Your Configuration

Check `config/mpesa.php` line 84:
```php
'shortcode' => '174379',  // This is where payments go
```

### In M-Pesa Business Dashboard

1. Log in to your M-Pesa Business portal
2. View transactions for your Business Shortcode
3. All payments will show under this shortcode

## Payment Flow Explained

```
Customer Books Ticket
    ↓
Customer Clicks "Pay with M-Pesa"
    ↓
System sends STK Push with:
    - Amount: KSh 1,500 (example)
    - Business Shortcode: 174379 (YOUR account)
    - Customer Phone: 0712345678
    ↓
Customer receives prompt:
    "Pay KSh 1,500 to 174379?"
    ↓
Customer enters PIN
    ↓
M-Pesa processes:
    - Deducts KSh 1,500 from customer's M-Pesa
    - Sends KSh 1,500 to Business Shortcode 174379
    ↓
Payment appears in YOUR Business Shortcode account
    ↓
You can withdraw to your bank account
```

## Where to Find Your Money

### For Sandbox (Current)
- Money is **simulated** - not real
- Used for testing only
- No actual money changes hands

### For Production
- Money goes to your **Business Shortcode account**
- Check your **M-Pesa Business dashboard**
- View transactions by logging into your PayBill account
- Withdraw to your bank account from M-Pesa Business portal

## Summary

✅ **Money goes to:** Business Shortcode (174379 for sandbox, your PayBill number for production)

✅ **No phone number needed:** The shortcode IS your account

✅ **Already configured:** Your system is set up correctly

✅ **For production:** Register PayBill account with Safaricom and update shortcode

## Next Steps

### If Testing (Sandbox):
- ✅ Everything is already configured
- ✅ No additional setup needed
- ✅ Test payments are simulated

### If Going Live (Production):
1. Register PayBill account with Safaricom
2. Get your Business Shortcode
3. Update `config/mpesa.php`:
   - Change shortcode to your PayBill number
   - Change environment to 'production'
4. Link PayBill to your M-Pesa app in Developer Portal

## Questions?

- **Safaricom Business Support:** 100 or visit business center
- **M-Pesa Developer Portal:** https://developer.safaricom.co.ke/
- **M-Pesa Business Portal:** Check your transactions

