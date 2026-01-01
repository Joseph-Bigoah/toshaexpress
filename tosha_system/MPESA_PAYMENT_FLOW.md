# M-Pesa Payment Flow - Where Payments Go

## Overview

This document explains where M-Pesa payments are sent and how the payment flow works in the TOSHA EXPRESS system.

## Where Payments Are Sent

### 1. Business Shortcode (PayBill Number)

**Payments go to your Business Shortcode**, which is configured in `config/mpesa.php`:

- **Sandbox (Testing):** Shortcode `174379` (or your configured shortcode)
- **Production:** Your registered business shortcode from Safaricom

### 2. How It Works

1. **Customer Initiates Payment:**
   - Customer clicks "Pay with M-Pesa" button
   - System sends STK Push request to M-Pesa
   - Customer receives prompt on their phone

2. **Customer Completes Payment:**
   - Customer enters M-Pesa PIN
   - Payment is processed by Safaricom
   - **Money is sent to your Business Shortcode account**

3. **Payment Confirmation:**
   - M-Pesa sends callback to: `stk_callback.php`
   - System updates ticket status to "confirmed"
   - Payment receipt is stored in database

## Important: Setting Up Your PayBill Account

### For Sandbox (Testing)
- Sandbox payments are **simulated** - no real money changes hands
- You can test the full payment flow without real transactions

### For Production (Real Payments)
You need to:

1. **Register a PayBill Account with Safaricom:**
   - Contact Safaricom Business Support
   - Register your business
   - Get your Business Shortcode (PayBill number)
   - Set up your M-Pesa Business account

2. **Configure in Developer Portal:**
   - Use your registered Business Shortcode
   - Link it to your M-Pesa app
   - Configure callback URLs

3. **Receive Payments:**
   - All payments go to your PayBill account
   - You can withdraw to your bank account
   - Check transactions in your M-Pesa Business dashboard

## Payment Flow Diagram

```
Customer → Clicks "Pay with M-Pesa"
    ↓
System → Sends STK Push to M-Pesa API
    ↓
M-Pesa → Sends prompt to customer's phone
    ↓
Customer → Enters PIN and confirms
    ↓
M-Pesa → Processes payment
    ↓
M-Pesa → Sends money to YOUR Business Shortcode
    ↓
M-Pesa → Sends callback to stk_callback.php
    ↓
System → Updates ticket status to "confirmed"
    ↓
System → Stores M-Pesa receipt number
```

## Checking Payment Status

### In the System

1. **View Tickets:**
   - Go to "Manage Bookings"
   - Look for tickets with `payment_method = 'mpesa'`
   - Check `mpesa_receipt` column for receipt number

2. **Check Logs:**
   - `logs/mpesa_payments.log` - Successful payments
   - `logs/mpesa_stk_callback.log` - All callbacks
   - `logs/mpesa_callback_errors.log` - Error logs

### In Your M-Pesa Account

1. **M-Pesa Business Dashboard:**
   - Log in to your M-Pesa Business portal
   - View transactions for your Business Shortcode
   - Download statements

2. **M-Pesa Statements:**
   - Check your PayBill account statements
   - Verify amounts match your ticket sales

## Payment Matching

The system matches payments to tickets using:

1. **Phone Number** - Customer's phone number
2. **Amount** - Payment amount matches ticket fare
3. **Time Window** - Ticket created within last 30 minutes

If a payment can't be matched automatically:
- It's logged in `mpesa_payments.log` with status "ticket_not_found"
- You can manually match it later

## Troubleshooting

### Payment Received But Ticket Not Updated

1. Check `logs/mpesa_payments.log` for the payment
2. Verify phone number format matches
3. Check if amount matches exactly
4. Verify ticket was created within 30 minutes

### Payment Not Received

1. Check M-Pesa Business dashboard
2. Verify Business Shortcode is correct
3. Check callback URL is accessible
4. Review `logs/mpesa_stk_callback.log`

## Security Notes

⚠️ **Important:**

1. **Never share your Business Shortcode credentials**
2. **Keep callback URLs secure**
3. **Verify all payments in your M-Pesa account**
4. **Reconcile payments regularly**

## Support

- **Safaricom Business Support:** Contact for PayBill registration
- **M-Pesa Developer Portal:** https://developer.safaricom.co.ke/
- **M-Pesa Business Dashboard:** Check your transactions

## Summary

- ✅ Payments go to your **Business Shortcode** (PayBill number)
- ✅ For sandbox: Use shortcode `174379` (testing only)
- ✅ For production: Register PayBill account with Safaricom
- ✅ System automatically updates tickets when payment is confirmed
- ✅ All payments are logged for reconciliation

