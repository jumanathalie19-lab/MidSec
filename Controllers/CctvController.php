<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/CctvController.php
//
//  Handles: CCTV feed creation, update, status toggle,
//           deletion, retrieval, nearest-feed lookup,
//           operational status summary, audit trail.
//  Model:   CctvModel
//
//  Role access summary:
//    create()       — admin only
//    update()       — admin only
//    toggleStatus() — admin only
//    delete()       — admin only (two-step: toggle first)
//    index()        — resident, guard, admin (stream_url role-aware)
//    show()         — resident, guard, admin (stream_url role-aware)
//    getNearest()   — guard, admin (server-side topology data)
//    statusSummary()— admin only
//    auditTrail()   — admin only
// ============================================================

require_once __DIR__ . '/../models/CctvModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class CctvController {

    private CctvModel  $cctvModel;
    private JwtHandler $jwt;

    // Allowed status_filter values for index()
    private const ALLOWED_STATUS_FILTERS = ['active', 'inactive'];

    public function __construct() {
        $this->cctvModel = new CctvModel();
        $this->jwt       = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/cctv
    //  Protected — admin only.
    //
    //  Registers a new CCTV camera feed. The stored procedure:
    //    - Verifies caller is a verified active admin.
    //    - Checks for duplicate feed_name and stream_url.
    //    - Creates new feed with feed_status = 'active'.
    //    - Records creation in audit_log.
    //  GPS coordinates validated before calling the model.
    //  stream_url returned in the response to the admin who
    //  created it — admin is the appropriate recipient here.
    // ----------------------------------------------------------
    public function create(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        // ---- Input validation --------------------------------
        $feed_name  = Request::sanitizeString($body['feed_name']  ?? '');
        $location   = Request::sanitizeString($body['location']   ?? '');
        $stream_url = trim($body['stream_url'] ?? '');  // not HTML-encoded — RTSP URL
        $latitude   = filter_var($body['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
        $longitude  = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if (empty($feed_name)) {
            Response::error('Feed name is required.', 400);
        }

        if (empty($location)) {
            Response::error('Feed location is required.', 400);
        }

        if (empty($stream_url)) {
            Response::error('Stream URL is required.', 400);
        }

        $this->validateGps($latitude, $longitude);

        // ---- Model call --------------------------------------
        $result = $this->cctvModel->addFeed(
            $caller['user_id'],
            $feed_name,
            $location,
            $stream_url,
            (float)$latitude,
            (float)$longitude
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')  ? 403
                  : (str_contains($result['message'], 'already exists')? 409 : 422);
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'CCTV feed added successfully.',
            201
        );
    }

    // ----------------------------------------------------------
    //  PUT /api/cctv/{id}
    //  Protected — admin only.
    //
    //  Updates all fields of an existing CCTV feed.
    //  Use cases: camera physically relocated (GPS + stream_url),
    //  feed renamed, location description updated.
    //  Procedure checks for duplicate feed_name and stream_url
    //  excluding the current feed (allows updating other fields
    //  without triggering false duplicate errors).
    //  Records old and new values in audit_log.
    // ----------------------------------------------------------
    public function update(int $feed_id): void {
        Request::requireMethod('PUT');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        if ($feed_id <= 0) {
            Response::error('A valid feed ID is required.', 400);
        }

        $feed_name  = Request::sanitizeString($body['feed_name']  ?? '');
        $location   = Request::sanitizeString($body['location']   ?? '');
        $stream_url = trim($body['stream_url'] ?? '');
        $latitude   = filter_var($body['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
        $longitude  = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if (empty($feed_name)) {
            Response::error('Feed name is required.', 400);
        }

        if (empty($location)) {
            Response::error('Feed location is required.', 400);
        }

        if (empty($stream_url)) {
            Response::error('Stream URL is required.', 400);
        }

        $this->validateGps($latitude, $longitude);

        $result = $this->cctvModel->updateFeed(
            $caller['user_id'],
            $feed_id,
            $feed_name,
            $location,
            $stream_url,
            (float)$latitude,
            (float)$longitude
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')  ? 403
                  : (str_contains($result['message'], 'not found')     ? 404
                  : (str_contains($result['message'], 'already exists')? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'CCTV feed updated successfully.');
    }

    // ----------------------------------------------------------
    //  PATCH /api/cctv/{id}/status
    //  Protected — admin only.
    //
    //  Bidirectionally toggles feed status between 'active' and
    //  'inactive'. The original sp_deactivate_cctv_feed was
    //  dropped — this calls sp_toggle_cctv_feed_status.
    //
    //  $reason is MANDATORY — the procedure SIGNALs if empty.
    //  Validated at controller layer for a clean 400 response.
    //
    //  The procedure returns an operational impact warning if
    //  any active panic alerts are currently linked to the feed
    //  being toggled offline. This warning is passed through in
    //  the response for the admin to act on.
    //
    //  TWO-STEP DELETE CONTEXT:
    //  If the admin intends to delete a feed, they must call
    //  this endpoint first to set it to 'inactive', then call
    //  DELETE /api/cctv/{id}. Attempting to delete an active
    //  feed returns a 409 from the delete endpoint directing
    //  the admin here.
    // ----------------------------------------------------------
    public function toggleStatus(int $feed_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $reason = Request::sanitizeString($body['reason'] ?? '');

        if (empty($reason)) {
            Response::error(
                'A reason is required when toggling CCTV feed status.',
                400
            );
        }

        if ($feed_id <= 0) {
            Response::error('A valid feed ID is required.', 400);
        }

        $result = $this->cctvModel->toggleFeedStatus(
            $caller['user_id'],
            $feed_id,
            $reason
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        // Pass through the full response including any operational
        // impact warning about linked active panic alerts.
        Response::success($result['data'], 'CCTV feed status toggled.');
    }

    // ----------------------------------------------------------
    //  DELETE /api/cctv/{id}
    //  Protected — admin only.
    //
    //  Permanently deletes a CCTV feed record.
    //
    //  TWO-STEP PROCESS (enforced by the stored procedure):
    //  Step 1: Call PATCH /api/cctv/{id}/status to set feed
    //          to 'inactive'. This acknowledges the operational
    //          impact and is required before deletion.
    //  Step 2: Call this endpoint. The procedure checks
    //          feed_status = 'inactive' before deleting.
    //
    //  The procedure also blocks deletion if any incidents or
    //  panic alerts reference this feed (evidence preservation).
    //
    //  $reason is MANDATORY — validated before model call.
    //  This is a permanent hard DELETE (unlike incidents which
    //  use soft delete) — CCTV feed records are operational
    //  configuration data, not security evidence. However,
    //  the audit_log entry is written before the DELETE within
    //  the same transaction.
    // ----------------------------------------------------------
    public function delete(int $feed_id): void {
        Request::requireMethod('DELETE');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $reason = Request::sanitizeString($body['reason'] ?? '');

        if (empty($reason)) {
            Response::error(
                'A documented reason is required when deleting a CCTV feed.',
                400
            );
        }

        if ($feed_id <= 0) {
            Response::error('A valid feed ID is required.', 400);
        }

        $result = $this->cctvModel->deleteFeed(
            $caller['user_id'],
            $feed_id,
            $reason
        );

        if (!$result['success']) {
            // 409 for: must be inactive first OR linked to incidents/panics
            $code = str_contains($result['message'], 'Access denied')     ? 403
                  : (str_contains($result['message'], 'not found')        ? 404
                  : (str_contains($result['message'], 'must be deactivated')
                  || str_contains($result['message'], 'referenced')       ? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'CCTV feed permanently deleted.');
    }

    // ----------------------------------------------------------
    //  GET /api/cctv
    //  Protected — resident, guard, admin.
    //
    //  Returns all CCTV feeds with role-aware stream_url:
    //    resident → stream_url = NULL (never receives RTSP URLs)
    //    guard    → stream_url returned (needs live feed access)
    //    admin    → stream_url returned
    //
    //  Optional filter: ?status=active|inactive
    //  Used by admin for maintenance views.
    //  Residents may use this to display a coverage map
    //  (feed name and location only — no stream_url).
    // ----------------------------------------------------------
    public function index(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        $status_filter = $this->optionalEnum(
            $_GET['status'] ?? null,
            self::ALLOWED_STATUS_FILTERS
        );

        $result = $this->cctvModel->getAllFeeds(
            $caller['user_id'],   // requesting_user_id — drives stream_url disclosure
            $status_filter
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'CCTV feeds retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/cctv/{id}
    //  Protected — resident, guard, admin.
    //
    //  Role-aware stream_url disclosure enforced by procedure:
    //    resident → stream_url = NULL
    //    guard    → stream_url returned
    //    admin    → stream_url returned
    //
    //  $caller['user_id'] passed as $requesting_user_id to
    //  enable the procedure's CASE expression for disclosure.
    // ----------------------------------------------------------
    public function show(int $feed_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        if ($feed_id <= 0) {
            Response::error('A valid feed ID is required.', 400);
        }

        $result = $this->cctvModel->getFeedById(
            $caller['user_id'],
            $feed_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'not found') ? 404 : 422;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'CCTV feed retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/cctv/nearest?latitude=X&longitude=Y
    //  Protected — guard, admin only.
    //
    //  Returns the nearest active CCTV feed to the given GPS
    //  point. Restricted to guard/admin — this procedure reveals
    //  the camera network's geographic distribution.
    //  stream_url NOT returned by sp_get_nearest_cctv (removed
    //  in Section 5 — server-side use only). Guards retrieve
    //  the stream_url through show() after the feed_id is known.
    //  GPS validated before calling the model.
    // ----------------------------------------------------------
    public function getNearest(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        $latitude  = filter_var($_GET['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_GET['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        $this->validateGps($latitude, $longitude);

        $result = $this->cctvModel->getNearestFeed(
            $caller['user_id'],
            (float)$latitude,
            (float)$longitude
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403 : 422;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Nearest CCTV feed retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/cctv/summary
    //  Protected — admin only.
    //
    //  Returns the admin operational CCTV health overview.
    //
    //  MULTIPLE RESULT SETS:
    //  CctvModel::getFeedStatusSummary() returns fetchMultiple()
    //  format: ['data' => [ [set1rows], [set2rows] ]]
    //    [0] Overall counts: total, active, inactive feeds
    //    [1] Per-feed activity: linked incident and panic counts,
    //        open incidents, active panics per feed
    //  stream_url NOT in either result set — aggregate only.
    // ----------------------------------------------------------
    public function statusSummary(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $result = $this->cctvModel->getFeedStatusSummary($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        // Destructure both result sets into named keys
        Response::success([
            'overall_counts'    => $result['data'][0][0] ?? [],  // single summary row
            'per_feed_activity' => $result['data'][1]    ?? [],  // multiple feed rows
        ], 'CCTV status summary retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/cctv/{id}/audit
    //  Protected — admin only.
    //
    //  Returns the complete audit history for a specific feed:
    //  every add, update, toggle, and delete action — with
    //  actor name, role, and timestamp.
    //  Reads from audit_log table — works even if the feed
    //  record itself has been deleted (post-deletion review).
    //  No stream_url in the audit trail.
    // ----------------------------------------------------------
    public function auditTrail(int $feed_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        if ($feed_id <= 0) {
            Response::error('A valid feed ID is required.', 400);
        }

        $result = $this->cctvModel->getCctvAuditTrail(
            $caller['user_id'],
            $feed_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'No audit records') ? 404 : 500;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'CCTV audit trail retrieved.');
    }

    // ----------------------------------------------------------
    //  Private: validateGps()
    //  Shared GPS validation used by create(), update(),
    //  and getNearest(). Terminates with 400 if invalid.
    // ----------------------------------------------------------
    private function validateGps(mixed $latitude, mixed $longitude): void {
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