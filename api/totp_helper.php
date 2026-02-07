<?php

declare(strict_types=1);


function generateTotpSecret(int $length = 32): string {
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $base32Chars[random_int(0, 31)];
    }
    
    return $secret;
}

//Generate backup codes

function generateBackupCodes(int $count = 8): array {
    $codes = [];
    
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-character alphanumeric code
        $code = strtoupper(bin2hex(random_bytes(4)));
        // Format as XXXX-XXXX
        $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }
    
    return $codes;
}

//Decode Base32 string
 
function base32Decode(string $secret): string {
    if (empty($secret)) {
        return '';
    }

    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32CharsFlipped = array_flip(str_split($base32Chars));
    
    // Remove padding and normalize
    $secret = rtrim(strtoupper($secret), '=');
    
    $binaryString = '';
    
    foreach (str_split($secret) as $char) {
        if (!isset($base32CharsFlipped[$char])) {
            error_log("Invalid base32 character: $char");
            return '';
        }
        $binaryString .= str_pad(decbin($base32CharsFlipped[$char]), 5, '0', STR_PAD_LEFT);
    }
    
    $result = '';
    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $result .= chr(bindec($byte));
        }
    }
    
    return $result;
}

/**
 * Generate TOTP code for a given secret and time
 */
function getTotpCode(string $secret, ?int $timeSlice = null): string {
    if ($timeSlice === null) {
        $timeSlice = (int)floor(time() / 30);
    }
    
    $secretKey = base32Decode($secret);
    
    if (empty($secretKey)) {
        error_log("Failed to decode secret: $secret");
        return '000000';
    }
    
    // Pack time into binary string (8 bytes, big-endian)
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    
    // Hash with secret key
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    
    // Use last nibble of result as index
    $offset = ord(substr($hash, -1)) & 0x0F;
    
    // Grab 4 bytes of the result
    $hashpart = substr($hash, $offset, 4);
    
    // Unpack binary value
    $value = unpack('N', $hashpart);
    $value = $value[1];
    
    // Only 32 bits
    $value = $value & 0x7FFFFFFF;
    
    $modulo = pow(10, 6);
    
    return str_pad((string)($value % $modulo), 6, '0', STR_PAD_LEFT);
}

/**
 * ✅ FIXED: Verify a TOTP code with proper time window and debugging
 */
function verifyTotpCode(string $secret, string $code, int $discrepancy = 1): bool {
    // Clean the input code
    $code = trim($code);
    
    error_log("=== TOTP Verification Debug ===");
    error_log("Secret (first 8 chars): " . substr($secret, 0, 8) . "...");
    error_log("Code received: " . $code);
    error_log("Discrepancy window: ±" . $discrepancy);
    error_log("Current server time: " . date('Y-m-d H:i:s'));
    error_log("Unix timestamp: " . time());
    
    if (!preg_match('/^\d{6}$/', $code)) {
        error_log("❌ Invalid code format: $code");
        return false;
    }
    
    $currentTimeSlice = (int)floor(time() / 30);
    error_log("Current time slice: " . $currentTimeSlice);
    
    // Check current time slice and adjacent ones (to account for time drift)
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $testTimeSlice = $currentTimeSlice + $i;
        $calculatedCode = getTotpCode($secret, $testTimeSlice);
        
        $offset = $i * 30;
        $timeOffset = ($offset >= 0 ? "+" : "") . $offset . "s";
        
        error_log("Testing window $i ($timeOffset): Expected code = $calculatedCode");
        
        // ✅ CRITICAL FIX: Use hash_equals for timing-safe comparison
        if (hash_equals($calculatedCode, $code)) {
            error_log("✅ CODE MATCHED at window $i ($timeOffset)!");
            return true;
        }
    }
    
    error_log("❌ Code did not match any window from -$discrepancy to +$discrepancy");
    
    // Additional debugging: check wider window
    error_log("--- Extended Window Check (for debugging) ---");
    for ($i = -5; $i <= 5; $i++) {
        $testTimeSlice = $currentTimeSlice + $i;
        $calculatedCode = getTotpCode($secret, $testTimeSlice);
        if (hash_equals($calculatedCode, $code)) {
            $offset = $i * 30;
            error_log("⚠️ CODE WOULD MATCH at window $i ({$offset}s offset) - outside allowed discrepancy!");
            error_log("⚠️ This suggests either:");
            error_log("   1. Server time is off by ~{$offset} seconds");
            error_log("   2. User's device time is off");
            error_log("   3. Code was used from a previous/future time window");
            break;
        }
    }
    
    return false;
}

/**
 * Get the OTPAuth URL for manual entry or QR code generation
 */
function getTotpUri(string $secret, string $username, string $issuer = 'SecureApp'): string {
    $label = rawurlencode($issuer . ':' . $username);

    $params = http_build_query([
        'secret'    => $secret,
        'issuer'    => $issuer,
        'algorithm' => 'SHA1',
        'digits'    => 6,
        'period'    => 30,
    ]);

    return "otpauth://totp/{$label}?{$params}";
}

/**
 * Generate QR code URL using QR Server API
 */
function getTotpQrCodeUrl(string $secret, string $username, string $issuer = 'SecureApp'): string {
    $otpauthUri = getTotpUri($secret, $username, $issuer);
    
    // Use QR Server API
    return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
        . urlencode($otpauthUri);
}