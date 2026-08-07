<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/IncidentController.php
//
//  Handles: incident creation, retrieval, status updates,
//           soft deletion, restoration, deleted audit view.
//  Model:   IncidentModel
//
//  Role access summary:
//    create()       — resident only
//    index()        — guard, admin
//    getByUser()    — resident (own), guard, admin
//    show()         — resident (own), guard, admin
//    updateStatus() — guard, admin
//    delete()       — admin only (soft delete)
//    getDeleted()   — admin only
//    restore()      — admin only
// ============================================================

require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class IncidentController {

    private IncidentModel $incidentModel;
    private JwtHandler    $jwt;

    // Allowed ENUM values — validated at controller layer before
    // reaching the model, providing a clean 400 response instead
    // of a raw database ENUM violation error.
    private const ALLOWED_TYPES = [
        'suspicious_activity',
        'theft_breakin',
        'property_damage',
    ];

    private const ALLOWED_STATUSES = [
        'open',
        'under_review',
        'resolved',
    ];

    public function __construct() {
        $this->incidentModel = new IncidentModel();
        $this->jwt           = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/incidents
    //  Protected — resident only.
    //
    //  Creates a new incident report. The stored procedure:
    //    - Verifies caller is a verified active resident.
    //    - Auto-assigns the nearest active CCTV feed.
    //    - Creates an audit_log entry.
    //  GPS coordinates validated before calling the model.
    //  stream_url is NOT returned in the response (excluded
    //  by the stored procedure for resident callers).
    // ----------------------------------------------------------
    public function create(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['resident']);
        $body   = Request::json();

        // ---- Input validation --------------------------------
        $incident_type = Request::sanitizeString($body['incident_type'] ?? '');
        $description   = Request::sanitizeString($body['description']   ?? '');
        $latitude      = filter_var($body['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
        $longitude     = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if (!in_array($incident_type, self::ALLOWED_TYPES, true)) {
            Response::error(
                "incident_type must be one of: " . implode(', ', self::ALLOWED_TYPES) . '.',
                400
            );
        }

        if ($latitude === false || $latitude === null) {
            Response::error('A valid latitude is required.', 400);
        }

        if ($longitude === false || $longitude === null) {
            Response::error('A valid longitude is required.', 400);
        }

        // GPS range validation
        // Latitude:  -90 to +90   (Midview Court is near Nairobi ~-1.27°)
        // Longitude: -180 to +180 (Nairobi ~36.94°)
        if ($latitude < -90 || $latitude > 90) {
            Response::error('Latitude must be between -90 and 90.', 400);
        }

        if ($longitude < -180 || $longitude > 180) {
            Response::error('Longitude must be between -180 and 180.', 400);
        }

        if (empty($description)) {
            Response::error('Incident description is required.', 400);
        }

        // ---- Model call --------------------------------------
        // $caller['user_id'] passed as user_id — procedure verifies
        // the caller is a verified active resident internally.
        $result = $this->incidentModel->createIncident(
            $caller['user_id'],
            $incident_type,
            $description,
            (float)$latitude,
            (float)$longitude
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403 : 422;
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Incident reported successfully.',
            201
        );
    }

    // ----------------------------------------------------------
    //  GET /api/incidents
    //  Protected — guard, admin only.
    //
    //  Returns all non-soft-deleted incidents with optional
    //  filters for status and incident_type.
    //  resident_phone included in response (guards need contact).
    //  stream_url excluded (served via CCTV module separately).
    //  Null filters passed as null — procedure treats NULL as
    //  "no filter". Empty strings must not be passed.
    // ----------------------------------------------------------
    public function index(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        // Optional filters — null means no filter
        $status        = $this->optionalEnum($_GET['status']        ?? null, self::ALLOWED_STATUSES);
        $incident_type = $this->optionalEnum($_GET['incident_type'] ?? null, self::ALLOWED_TYPES);

        $result = $this->incidentModel->getAllIncidents(
            $caller['user_id'],
            $status,
            $incident_type
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Incidents retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/incidents/user/{id}
    //  GET /api/incidents/user        (own incidents — resident)
    //  Protected — resident (own only), guard, admin.
    //
    //  Residents: $target_user_id must equal their own user_id.
    //             sp_get_incidents_by_user enforces this — returns
    //             403 if a resident tries to access another's history.
    //  Guards/admins: may pass any valid user_id.
    //
    //  When called without {id}, defaults to the caller's own ID —
    //  this is the primary use case for the resident dashboard.
    // ----------------------------------------------------------
    public function getByUser(?int $target_user_id = null): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        // Default: caller views their own incidents
        $target = $target_user_id ?? $caller['user_id'];

        if ($target <= 0) {
            Response::error('A valid user ID is required.', 400);
        }

        // Residents attempting to view another user's incidents
        // are blocked here as an early check before hitting the DB.
        if ($caller['role'] === 'resident' && $target !== $caller['user_id']) {
            Response::error('Residents can only view their own incident history.', 403);
        }

        $result = $this->incidentModel->getIncidentsByUser(
            $caller['user_id'],
            $target
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403 : 404;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Incidents retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/incidents/{id}
    //  Protected — resident (own), guard, admin.
    //
    //  Role-aware field disclosure enforced by the procedure:
    //    resident → no phone numbers, no stream_url
    //    guard    → resident_phone visible, no stream_url
    //    admin    → all fields including stream_url, guard_phone
    //
    //  IDOR protection: $caller['user_id'] passed as
    //  $requesting_user_id — procedure blocks residents from
    //  accessing other residents' incidents.
    // ----------------------------------------------------------
    public function show(int $incident_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->incidentModel->getIncidentById(
            $caller['user_id'],   // requesting_user_id — drives RBAC + disclosure
            $incident_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403 : 404;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Incident retrieved.');
    }

    // ----------------------------------------------------------
    //  PATCH /api/incidents/{id}/status
    //  Protected — guard, admin.
    //
    //  Updates incident status and records the guard note.
    //  Business rule enforced in the procedure:
    //    - Only admin can re-open a resolved incident.
    //    - Guards may only move to 'under_review' or 'resolved'.
    //  assigned_guard_id is set to the responding guard automatically
    //  by the stored procedure.
    //  Returns updated incident summary + confirmation message.
    // ----------------------------------------------------------
    public function updateStatus(int $incident_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['guard', 'admin']);
        $body   = Request::json();

        $new_status = Request::sanitizeString($body['status']     ?? '');
        $guard_note = Request::sanitizeString($body['guard_note'] ?? '');

        if (!in_array($new_status, self::ALLOWED_STATUSES, true)) {
            Response::error(
                "status must be one of: " . implode(', ', self::ALLOWED_STATUSES) . '.',
                400
            );
        }

        if (empty($guard_note)) {
            Response::error('A guard note is required when updating incident status.', 400);
        }

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->incidentModel->updateIncidentStatus(
            $incident_id,
            $caller['user_id'],   // guard_id — procedure verifies role
            $new_status,
            $guard_note
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')  ? 403
                  : (str_contains($result['message'], 'not found')     ? 404
                  : (str_contains($result['message'], 'Only admins')   ? 403 : 422));
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Incident status updated.');
    }

    // ----------------------------------------------------------
    //  DELETE /api/incidents/{id}
    //  Protected — admin only.
    //
    //  SOFT DELETE — not a hard DELETE FROM incidents.
    //  Incident records are security evidence retained for
    //  KDPA 2019 compliance and police investigation purposes.
    //  Sets is_deleted = 1 and records deleted_by, deleted_at.
    //
    //  $reason is MANDATORY — required by the stored procedure.
    //  The procedure also blocks deletion if police escalations
    //  exist for the incident (evidence preservation).
    //
    //  Two-step check: controller validates reason is non-empty
    //  before calling the model, giving a clean 400 response
    //  rather than a database SIGNAL error.
    // ----------------------------------------------------------
    public function delete(int $incident_id): void {
        Request::requireMethod('DELETE');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $reason = Request::sanitizeString($body['reason'] ?? '');

        if (empty($reason)) {
            Response::error(
                'A documented reason is required when deleting an incident.',
                400
            );
        }

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->incidentModel->deleteIncident(
            $caller['user_id'],
            $incident_id,
            $reason
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')     ? 403
                  : (str_contains($result['message'], 'not found')        ? 404
                  : (str_contains($result['message'], 'police escalation')? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Incident soft-deleted. Record retained for audit and compliance.'
        );
    }

    // ----------------------------------------------------------
    //  GET /api/incidents/deleted
    //  Protected — admin only.
    //
    //  Returns all soft-deleted incident records for admin
    //  audit review. Required for KDPA 2019 accountability —
    //  admins must be able to see what was removed, by whom,
    //  and when.
    //  Includes: incident_id, type, description, status at
    //  deletion, reported_at, deleted_at, reported_by,
    //  house_no, deleted_by_name, deleted_by_role.
    // ----------------------------------------------------------
    public function getDeleted(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $result = $this->incidentModel->getDeletedIncidents($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Deleted incidents retrieved.');
    }

    // ----------------------------------------------------------
    //  PATCH /api/incidents/{id}/restore
    //  Protected — admin only.
    //
    //  Restores a soft-deleted incident back to active view
    //  (is_deleted reset to 0). Requires a documented reason.
    //  The procedure blocks restoring an incident that is not
    //  currently soft-deleted.
    // ----------------------------------------------------------
    public function restore(int $incident_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $reason = Request::sanitizeString($body['reason'] ?? '');

        if (empty($reason)) {
            Response::error(
                'A documented reason is required when restoring an incident.',
                400
            );
        }

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->incidentModel->restoreIncident(
            $caller['user_id'],
            $incident_id,
            $reason
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Incident restored to active view.');
    }

    // ----------------------------------------------------------
    //  Private: requireAuth()
    //  Same pattern as AuthController — extracts JWT from the
    //  Authorization header and verifies the caller's role.
    //  Defence in depth: role checked here AND in the procedure.
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
    //  Validates an optional query string parameter against an
    //  allowed set of values. Returns null if not provided.
    //  Returns a clean 400 error if provided but invalid.
    //  Prevents empty strings from being passed to the model
    //  as filter values (which would match nothing in the DB).
    // ----------------------------------------------------------
    private function optionalEnum(?string $value, array $allowed): ?string {
        if ($value === null || $value === '') return null;
        $clean = Request::sanitizeString($value);
        if (!in_array($clean, $allowed, true)) {
            Response::error(
                "Invalid filter value '{$clean}'. Allowed: " . implode(', ', $allowed) . '.',
                400
            );
        }
        return $clean;
    }
}