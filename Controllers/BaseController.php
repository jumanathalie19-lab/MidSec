<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/BaseController.php
//
//  Abstract base class for all controllers.
//  Provides: requireAuth(), optionalEnum(), validateGps(),
//            errorCode() — shared across all controllers.
//  Cross-controller fix: eliminates 7 duplicate requireAuth()
//  implementations and 5 duplicate optionalEnum() implementations.
// ============================================================

require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

abstract class BaseController {

    private JwtHandler $jwt;

    public function __construct() {
        $this->jwt = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  requireAuth()
    //  Extracts and validates the JWT from the Authorization
    //  header. Verifies caller role is in $allowedRoles.
    //  Returns decoded JWT payload on success.
    //  Terminates with 401 or 403 on failure.
    //
    //  Defence in depth: role checked here (controller layer)
    //  AND inside every stored procedure (database layer).
    //  Both checks must pass independently.
    // ----------------------------------------------------------
    protected function requireAuth(array $allowedRoles): array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::error('Authentication token required.', 401);
        }

        $token   = trim(substr($authHeader, 7));
        $payload = $this->jwt->decode($token);

        if (!$payload) {
            Response::error('Invalid or expired token.', 401);
        }

        if (!in_array($payload['role'], $allowedRoles, true)) {
            Response::error(
                'You do not have permission to perform this action.',
                403
            );
        }

        return $payload;
    }

    // ----------------------------------------------------------
    //  optionalEnum()
    //  Validates an optional query string or body parameter
    //  against an allowed set of ENUM values.
    //  Returns null if not provided (procedure treats as no filter).
    //  Returns clean 400 if provided but invalid.
    //  Prevents empty strings reaching the model as filter values.
    // ----------------------------------------------------------
    protected function optionalEnum(?string $value, array $allowed): ?string {
        if ($value === null || $value === '') return null;
        $clean = Request::sanitizeString($value);
        if (!in_array($clean, $allowed, true)) {
            Response::error(
                "Invalid value '{$clean}'. Allowed: " . implode(', ', $allowed) . '.',
                400
            );
        }
        return $clean;
    }

    // ----------------------------------------------------------
    //  validateGps()
    //  Validates latitude and longitude ranges.
    //  Used by: IncidentController, PanicController, CctvController.
    //  Latitude:  -90 to +90   (Midview Court ~-1.27°)
    //  Longitude: -180 to +180 (Midview Court ~36.94°)
    //  Terminates with 400 if invalid.
    // ----------------------------------------------------------
    protected function validateGps(mixed $latitude, mixed $longitude): void {
        if ($latitude === false || $latitude === null) {
            Response::error('A valid latitude is required.', 400);
        }

        if ($longitude === false || $longitude === null) {
            Response::error('A valid longitude is required.', 400);
        }

        if ($latitude < -90 || $latitude > 90) {
            Response::error('Latitude must be between -90 and 90.', 400);
        }

        if ($longitude < -180 || $longitude > 180) {
            Response::error('Longitude must be between -180 and 180.', 400);
        }
    }

    // ----------------------------------------------------------
    //  errorCode()
    //  Maps SIGNAL message content to HTTP status codes.
    //  Consistent mapping applied across all controllers.
    //  Eliminates ad-hoc ternary chains in individual controllers.
    // ----------------------------------------------------------
    protected function errorCode(string $message, int $default = 422): int {
        return match (true) {
            str_contains($message, 'Access denied')             => 403,
            str_contains($message, 'Authentication')            => 401,
            str_contains($message, 'not found')                 => 404,
            str_contains($message, 'already')                   => 409,
            str_contains($message, 'duplicate')                 => 409,
            str_contains($message, 'conflict')                  => 409,
            str_contains($message, 'referenced')                => 409,
            str_contains($message, 'must be deactivated')       => 409,
            str_contains($message, 'police escalation')         => 409,
            str_contains($message, 'No audit records')          => 404,
            str_contains($message, 'system error')              => 500,
            default                                             => $default,
        };
    }
}