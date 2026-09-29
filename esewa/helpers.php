<?php
/**
 * eSewa ePay v2 signing / verification helpers.
 * Reference: https://developer.esewa.com.np/pages/Epay-V2
 */

/**
 * Build the HMAC-SHA256 (base64) signature eSewa expects/returns.
 * $data must contain every field named in $signedFieldNamesCsv, and the
 * message is built by joining "field=value" pairs in that exact order —
 * this is used both to SIGN our outgoing request and to VERIFY eSewa's
 * incoming callback.
 */
function esewa_build_signature(array $data, string $signedFieldNamesCsv, string $secret): string
{
    $parts = [];
    foreach (explode(',', $signedFieldNamesCsv) as $field) {
        $field = trim($field);
        $parts[] = $field . '=' . ($data[$field] ?? '');
    }
    $message = implode(',', $parts);
    return base64_encode(hash_hmac('sha256', $message, $secret, true));
}

/**
 * Decode the ?data= value eSewa appends to the success URL.
 *
 * Two real-world gotchas handled here:
 *  1. A "+" in base64 arrives as a space if it wasn't URL-encoded, which
 *     would silently corrupt the payload — turn spaces back into "+".
 *  2. eSewa often sends amounts as bare JSON numbers (e.g. 1000.0). PHP's
 *     json_decode would turn that into 1000 -> "1000", but eSewa signed the
 *     text "1000.0", so the signature would never match and a genuine payment
 *     would be rejected. We therefore keep the EXACT text of every numeric
 *     field as a string.
 */
function esewa_decode_callback(string $encoded): ?array
{
    $json = base64_decode(str_replace(' ', '+', trim($encoded)), true);
    if ($json === false || $json === '') {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    foreach ($data as $key => $value) {
        if (is_int($value) || is_float($value)) {
            $pattern = '/"' . preg_quote((string) $key, '/') . '"\s*:\s*(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/';
            $data[$key] = preg_match($pattern, $json, $m) ? $m[1] : (string) $value;
        }
    }
    return $data;
}

/** Turn "1,000.0" / "1000.00" / 1000 into a float for comparing against our order total. */
function esewa_amount_to_float($value): float
{
    return (float) str_replace(',', '', (string) $value);
}

/**
 * Verify a decoded eSewa callback payload: recomputes the signature from
 * the fields it says it signed and does a timing-safe comparison.
 *
 * eSewa formats total_amount inconsistently between examples ("1000.0",
 * "1,000.0", 1000, "1000.00"), so if the exact text doesn't verify we also
 * try the same amount in the other common formats. This is safe: an attacker
 * without the secret key cannot forge a valid HMAC for ANY format, and the
 * amount is separately checked against the order total by the caller.
 */
function esewa_verify_response(array $data): bool
{
    if (empty($data['signature']) || empty($data['signed_field_names'])) {
        return false;
    }
    $signed = array_map('trim', explode(',', $data['signed_field_names']));

    $candidates = [$data];
    if (in_array('total_amount', $signed, true) && isset($data['total_amount'])) {
        $n = esewa_amount_to_float($data['total_amount']);
        $formats = [
            number_format($n, 1, '.', ''),  number_format($n, 2, '.', ''),  (string) (float) $n,
            number_format($n, 0, '.', ''),  number_format($n, 1, '.', ','), number_format($n, 2, '.', ','),
        ];
        foreach (array_unique($formats) as $format) {
            $candidates[] = ['total_amount' => $format] + $data;
        }
    }

    foreach ($candidates as $candidate) {
        $expected = esewa_build_signature($candidate, $data['signed_field_names'], ESEWA_SECRET_KEY);
        if (hash_equals($expected, (string) $data['signature'])) {
            return true;
        }
    }
    return false;
}

/**
 * Small GET helper used by the status check and the admin "eSewa check"
 * page. Never throws — returns ['ok','http','body','error'].
 */
function esewa_http_get(string $url, int $timeout = 10): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'body' => '', 'error' => 'The PHP curl extension is not enabled.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => defined('ESEWA_VERIFY_SSL') ? (bool) ESEWA_VERIFY_SSL : true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body  = curl_exec($ch);
    $error = curl_error($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['ok' => $body !== false && $error === '', 'http' => $http, 'body' => $body === false ? '' : (string) $body, 'error' => $error];
}

/**
 * Server-to-server status check against eSewa, used as a second
 * confirmation alongside signature verification. Returns the decoded
 * response array, or null if the request itself failed (network issue,
 * timeout, SSL problem…) — callers treat null as "inconclusive", not as
 * "failed", since signature verification is the primary check.
 *
 * eSewa's response uses snake_case (status, ref_id, total_amount) on the
 * current API; only "status" is relied on here.
 */
function esewa_check_status(string $transactionUuid, string $totalAmount): ?array
{
    $url = ESEWA_STATUS_URL . '?' . http_build_query([
        'product_code'     => ESEWA_MERCHANT_CODE,
        'total_amount'     => $totalAmount,
        'transaction_uuid' => $transactionUuid,
    ]);

    $res = esewa_http_get($url);
    if (!$res['ok']) {
        error_log('eSewa status check failed: ' . $res['error']);
        return null;
    }
    $decoded = json_decode($res['body'], true);
    return is_array($decoded) ? $decoded : null;
}
