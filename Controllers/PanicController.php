<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/PanicController.php
//
//  Handles: panic trigger, guard response, alert closure,
//           active dashboard, alert history, resident self-view,
//           panic statistics.
//  Model:   PanicModel
//
//  Role access summary:
//    trigger()    — resident only
//    respond()    — guard, admin
//    close()      — guard, admin
//    getActive()  — guard, admin
//    show()       — resident (own), guard, admin
//    history()    — guard, admin
//    myPanics()   — resident only
//    statistics() — guard, admin
// ============================================================

require_once __DIR__ . '/../models/PanicModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class PanicController {

    private PanicModel $panicModel;
    private JwtHandler $jwt;

    // Allowed status values from Section 1 schema ENUM
    private const ALLOWED_STATUSES = ['active', 'responded', 'closed'];

    public function __construct() {
        $this->panicModel = new PanicModel();
        $this->jwt        = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/panic
    //  Protected — resident only.
    //
    //  Triggers a panic alert with the resident's GPS location.
    //  The stored procedure:
    //    - Verifies caller is a verified active resident.
    //    - Blocks a second trigger if an active alert already
    //      exists for this resident (duplicate panic guard).
    //    - Auto-assigns the nearest active CCTV feed (nullable).
    //    - Creates an audit_log entry.
    //  stream_url NOT returned to the resident in the response.
    //  GPS coordinates validated before calling the model.
    // ----------------------------------------------------------
    public function trigger(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['resident']);
        $body   = Request::json();

        // ---- GPS validation ----------------------------------
        $latitude  = filter_var($body['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

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

        // ---- Model call --------------------------------------
        // $caller['user_id'] used as p_user_id — procedure verifies
        // caller is a verified active resident and enforces the
        // duplicate active panic check internally.
        $result = $this->panicModel->triggerPanic(
            $caller['user_id'],
            (float)$latitude,
            (float)$longitude
        );

        if (!$result['success']) {
            // Duplicate panic returns a clear SIGNAL message.
            // Map to 409 Conflict — not a generic 400.
            $code = str_contains($result['message'], 'already have an active') ? 409
                  : (str_contains($result['message'], 'Access denied')         ? 403 : 422);
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Panic alert triggered. A guard has been notified.',
            201
        );
    }

    // ----------------------------------------------------------
    //  PATCH /api/panic/{id}/respond
    //  Protected — guard, admin.
    //
    //  Assigns a guard to an active panic alert and sets its
    //  status to 'responded'. The stored procedure uses
    //  SELECT ... FOR UPDATE (row-level lock) to prevent two
    //  guards simultaneously claiming the same alert.
    //  If a guard loses the race, the procedure returns a SIGNAL
    //  message ("already been responded to or closed") which
    //  BaseModel forwards as an error — mapped to 409 here.
    //  Returns: alert detail including resident_phone and
    //  cctv_url for the responding guard.
    //  DO NOT add retry logic here — the race condition is
    //  intentionally resolved by the database lock.
    // ----------------------------------------------------------
    public function respond(int $alert_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['guard', 'admin']);

        if ($alert_id <= 0) {
            Response::error('A valid alert ID is required.', 400);
        }

        $result = $this->panicModel->respondToPanic(
            $alert_id,
            $caller['user_id']   // guard_id — procedure verifies role
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')          ? 403
                  : (str_contains($result['message'], 'not found')             ? 404
                  : (str_contains($result['message'], 'already been responded')? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'You have been assigned to this panic alert.'
        );
    }

    // ----------------------------------------------------------
    //  PATCH /api/panic/{id}/close
    //  Protected — guard, admin.
    //
    //  Closes a panic alert and records the resolution outcome.
    //
    //  CRITICAL FIX from model audit Section 3:
    //  The original sp_close_panic had NO role check. Any user
    //  could close any alert, silently cancelling an emergency
    //  response. The corrected procedure requires p_closed_by
    //  (guard or admin) and mandatory p_resolution_notes.
    //
    //  resolution_notes validated as non-empty at controller
    //  layer before reaching the procedure (clean 400 vs SIGNAL).
    // ----------------------------------------------------------
    public function close(int $alert_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['guard', 'admin']);
        $body   = Request::json();

        $resolution_notes = Request::sanitizeString(
            $body['resolution_notes'] ?? ''
        );

        if (empty($resolution_notes)) {
            Response::error(
                'Resolution notes are required. Please document the outcome before closing this alert.',
                400
            );
        }

        if ($alert_id <= 0) {
            Response::error('A valid alert ID is required.', 400);
        }

        $result = $this->panicModel->closePanic(
            $caller['user_id'],   // closed_by — procedure verifies role
            $alert_id,
            $resolution_notes
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')  ? 403
                  : (str_contains($result['message'], 'not found')     ? 404
                  : (str_contains($result['message'], 'already closed')? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Panic alert closed and outcome documented.'
        );
    }

    // ----------------------------------------------------------
    //  GET /api/panic/active
    //  Protected — guard, admin only.
    //
    //  Returns all currently active panic alerts for the guard
    //  dashboard. Ordered oldest-first (longest-waiting resident
    //  at top). Includes:
    //    - resident_phone (guard needs this to contact resident)
    //    - cctv_url (guard needs live feed)
    //    - GPS coordinates
    //    - minutes_elapsed (operational urgency indicator)
    //
    //  SECURITY: This endpoint must NEVER be accessible to
    //  residents — it exposes real-time GPS and phone data for
    //  all panicking residents simultaneously. Role enforced
    //  at controller layer AND inside the stored procedure.
    // ----------------------------------------------------------
    public function getActive(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        $result = $this->panicModel->getActivePanics($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Active panic alerts retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/panic/{id}
    //  Protected — resident (own), guard, admin.
    //
    //  Role-aware field disclosure enforced by the procedure:
    //    resident → no resident_phone, no cctv_url, no guard_phone
    //    guard    → resident_phone and cctv_url visible
    //    admin    → all fields including guard_phone
    //
    //  IDOR protection: $caller['user_id'] passed as
    //  $requesting_user_id — procedure blocks residents from
    //  accessing other residents' panic alerts.
    // ----------------------------------------------------------
    public function show(int $alert_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        if ($alert_id <= 0) {
            Response::error('A valid alert ID is required.', 400);
        }

        $result = $this->panicModel->getPanicById(
            $caller['user_id'],   // requesting_user_id — drives RBAC + disclosure
            $alert_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Panic alert retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/panic/history
    //  Protected — guard, admin only.
    //
    //  Returns historical panic alerts with optional filters:
    //    status    — one of: active, responded, closed
    //    date_from — YYYY-MM-DD
    //    date_to   — YYYY-MM-DD
    //  stream_url excluded from history view — not needed for
    //  reviewing past events; served only during active response.
    //  Date strings validated before passing to the model.
    //  Null filters passed as null — procedure treats NULL as
    //  "no filter".
    // ----------------------------------------------------------
    public function history(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        $status    = $this->optionalEnum($_GET['status'] ?? null, self::ALLOWED_STATUSES);
        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->panicModel->getPanicHistory(
            $caller['user_id'],
            $status,
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Panic alert history retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/panic/my
    //  Protected — resident only.
    //
    //  Allows a resident to view their own panic alert history.
    //  Separate from history() which is guard/admin only.
    //  Ownership enforced inside sp_get_my_panics via user_id
    //  — a resident cannot view another resident's alerts.
    //  stream_url excluded (residents never receive RTSP URLs).
    //  guard_phone excluded (resident does not need this).
    // ----------------------------------------------------------
    public function myPanics(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident']);

        $result = $this->panicModel->getMyPanics($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Your panic alerts retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/panic/statistics
    //  Protected — guard, admin.
    //
    //  Returns panic alert performance statistics.
    //  Optional parameters:
    //    date_from            — YYYY-MM-DD
    //    date_to              — YYYY-MM-DD
    //    response_target_mins — positive integer (default: 15)
    //
    //  MULTIPLE RESULT SETS:
    //  PanicModel::getPanicStatistics() returns fetchMultiple()
    //  format: ['data' => [ [set1rows], [set2rows] ]]
    //    [0] Overall performance summary
    //    [1] Per-guard response performance breakdown
    //
    //  The controller returns both sets nested under their
    //  respective keys for the client to consume directly.
    //  No personal data — aggregate statistics only.
    // ----------------------------------------------------------
    public function statistics(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        // Validate response_target_mins if provided
        $target_raw  = $_GET['response_target_mins'] ?? null;
        $target_mins = 15; // default matching procedure internal default

        if ($target_raw !== null) {
            $target_mins = filter_var($target_raw, FILTER_VALIDATE_INT);
            if ($target_mins === false || $target_mins <= 0) {
                Response::error(
                    'response_target_mins must be a positive integer.',
                    400
                );
            }
        }

        $result = $this->panicModel->getPanicStatistics(
            $caller['user_id'],
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        // fetchMultiple() returns nested array:
        // $result['data'][0] = overall summary (single row)
        // $result['data'][1] = per-guard breakdown (multiple rows)
        Response::success([
            'overall_summary'      => $result['data'][0] ?? [],
            'guard_performance'    => $result['data'][1] ?? [],
            'response_target_mins' => $target_mins,
        ], 'Panic statistics retrieved.');
    }

    // ----------------------------------------------------------
    //  Private: requireAuth()
    // ----------------------------------------------------------
    private function requireAuth(array $allowedRoles): array {
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
    //  Private: optionalEnum()
    // ----------------------------------------------------------
    private function optionalEnum(?string $value, array $allowed): ?string {
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
}