<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  config/JwtHandler.php — Minimal HS256 JWT encode/decode
//  No external dependencies — self-contained implementation.
//
//  FIXES APPLIED:
//    [J1] Dev fallback secret upgraded to 64-char random hex
//    [J2] Header alg verified on decode (rejects 'alg:none')
//    [J3] nbf (not-before) claim validated on decode
//    [J4] base64UrlDecode padding simplified to standard form
// ============================================================

class JwtHandler {

    /** @var string HMAC-SHA256 signing secret */
    private string $secret;

    /** @var string JWT algorithm header value */
    private string $algo = 'HS256';

    /** @var int Default token lifetime in seconds (1 hour) */
    private int $ttl;

    public function __construct() {
        // --------------------------------------------------------
        // Secret key resolution order:
        //   1. JWT_SECRET environment variable (production)
        //   2. Dev-only fallback below (never use in production)
        //
        // To set in production (Linux/Apache):
        //   SetEnv JWT_SECRET your_64_char_random_string
        //
        // Generate a strong secret with:
        //   php -r "echo bin2hex(random_bytes(32));"
        // --------------------------------------------------------
        $envSecret    = getenv('JWT_SECRET');
        $this->secret = ($envSecret !== false && $envSecret !== '')
            ? $envSecret
            // [J1] FIX: 64-char hex fallback — still dev-only,
            // but has proper entropy if accidentally used.
            // REPLACE THIS before any real deployment.
            : 'a3f8c2d1e4b7960524aef138cd904b7612e5d839f2701c48ba3e96d05471f8c2';

        $envTtl    = getenv('JWT_TTL');
        $this->ttl = ($envTtl !== false && ctype_digit($envTtl))
            ? (int) $envTtl
            : 3600; // 1 hour default
    }

    // ----------------------------------------------------------
    //  encode()
    //  Build and sign a JWT containing the given payload claims.
    //
    //  Standard claims added automatically:
    //    iat — issued-at timestamp
    //    exp — expiry timestamp (iat + ttl)
    //    nbf — not-before (same as iat — token valid immediately)
    //
    //  @param array    $payload   Claims: user_id, role, names.
    //  @param int|null $expiresIn Override TTL in seconds.
    // ----------------------------------------------------------
    public function encode(array $payload, ?int $expiresIn = null): string {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algo,
        ];

        $now    = time();
        $claims = array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,                           // valid immediately
            'exp' => $now + ($expiresIn ?? $this->ttl),
        ]);

        $headerEncoded  = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($claims));
        $signature      = $this->sign("{$headerEncoded}.{$payloadEncoded}");

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    // ----------------------------------------------------------
    //  decode()
    //  Verify and decode a JWT string.
    //
    //  Checks (in order):
    //    1. Three-part structure
    //    2. Header declares alg = HS256  [J2 NEW]
    //    3. Signature matches (constant-time comparison)
    //    4. nbf — token is not used before valid date [J3 NEW]
    //    5. exp — token is not expired
    //
    //  @return array|false Decoded claims on success, false on any failure.
    // ----------------------------------------------------------
    public function decode(string $token): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // [J2] FIX: Verify the header declares HS256.
        // Rejects tokens with alg:none or any other algorithm.
        $headerJson = $this->base64UrlDecode($headerEncoded);
        $header     = json_decode($headerJson, true);
        if (
            !is_array($header)
            || !isset($header['alg'])
            || $header['alg'] !== $this->algo
        ) {
            return false;
        }

        // Verify signature using constant-time comparison
        // (prevents timing attacks on HMAC comparison)
        $expectedSignature = $this->sign("{$headerEncoded}.{$payloadEncoded}");
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        $claims      = json_decode($payloadJson, true);
        if (!is_array($claims)) {
            return false;
        }

        $now = time();

        // [J3] FIX: Reject tokens used before their not-before time
        if (isset($claims['nbf']) && $now < (int) $claims['nbf']) {
            return false;
        }

        // Reject expired tokens
        if (isset($claims['exp']) && $now >= (int) $claims['exp']) {
            return false;
        }

        return $claims;
    }

    // ----------------------------------------------------------
    //  validate()
    //  Convenience wrapper — true if token is valid and not expired.
    // ----------------------------------------------------------
    public function validate(string $token): bool {
        return $this->decode($token) !== false;
    }

    // ----------------------------------------------------------
    //  Private helpers
    // ----------------------------------------------------------

    private function sign(string $data): string {
        $hash = hash_hmac('sha256', $data, $this->secret, true);
        return $this->base64UrlEncode($hash);
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // [J4] FIX: Simplified padding — standard one-liner.
    // Equivalent to: pad to the next multiple of 4.
    private function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}