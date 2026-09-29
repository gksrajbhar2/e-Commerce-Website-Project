<?php
/**
 * eSewa ePay v2 configuration.
 *
 * ESEWA_ENV controls which set of credentials/URLs are used.
 *   'test' -> eSewa's public UAT (sandbox) environment. No signup needed and
 *             no real money moves. Merchant code + secret key below are the
 *             ones eSewa publishes at developer.esewa.com.np.
 *   'live' -> Real payments. You MUST replace MERCHANT_CODE and SECRET_KEY
 *             with the values eSewa issues you after merchant onboarding.
 *
 * TEST LOGINS for the eSewa payment page (sandbox only). eSewa's own
 * documentation lists two different sets on two different pages, and which
 * one is active can change, so if the first is rejected, try the second:
 *
 *   Set A  (ePay v2 page)         eSewa ID: 9806800001 (…02 to …05 also work)
 *                                 Password: Nepal@123
 *   Set B  (Test-credentials page) eSewa ID: 9711111111 (…12, …13)
 *                                 Password: Test@123
 *   Both:  MPIN: 1122   |   Verification token / OTP: 123456
 *
 * Use the admin panel's "eSewa check" page to confirm this server can reach
 * eSewa and that request signing is working.
 */
define('ESEWA_ENV', 'test'); // 'test' | 'live'

if (ESEWA_ENV === 'live') {
    define('ESEWA_MERCHANT_CODE', 'YOUR_LIVE_PRODUCT_CODE');   // from eSewa
    define('ESEWA_SECRET_KEY',    'YOUR_LIVE_SECRET_KEY');     // from eSewa
    define('ESEWA_FORM_URL',      'https://epay.esewa.com.np/api/epay/main/v2/form');
    define('ESEWA_STATUS_URL',    'https://epay.esewa.com.np/api/epay/transaction/status/');
} else {
    define('ESEWA_MERCHANT_CODE', 'EPAYTEST');
    define('ESEWA_SECRET_KEY',    '8gBm/:&EnhH.1/q');
    define('ESEWA_FORM_URL',      'https://rc-epay.esewa.com.np/api/epay/main/v2/form');
    define('ESEWA_STATUS_URL',    'https://rc.esewa.com.np/api/epay/transaction/status/');
}

// Where eSewa redirects the customer's browser back to after payment.
// BASE_URL must match the address you open the site at (same port!) and be
// reachable by the customer's browser — eSewa never calls these server-to-server.
define('BASE_URL', 'http://localhost:8000');
define('ESEWA_SUCCESS_URL', BASE_URL . '/esewa/success.php');
define('ESEWA_FAILURE_URL', BASE_URL . '/esewa/failure.php');

// The server-to-server status check verifies HTTPS certificates. On some
// Windows/XAMPP installs PHP has no CA bundle, which makes that check fail
// with "SSL certificate problem". The check is a secondary confirmation only
// (the signed callback is the authoritative one), so payments still work, but
// if the "eSewa check" admin page reports an SSL error you can set this to
// false for LOCAL TESTING ONLY. Always keep it true on a real server.
define('ESEWA_VERIFY_SSL', true);
