<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/PoliceController.php
//
//  Handles: incident escalation to police, escalation status
//           tracking, escalation closure, escalation retrieval
//           by ID and by incident.
//  Model:   PoliceModel
//
//  Role access summary:
//    escalate()       — guard, admin
//    index()          — guard, admin
//    show()           — guard, admin (role-aware field disclosure)
//    updateStatus()   — admin only
//    close()          — admin only
//    getByIncident()  — guard, admin
// ============================================================

require_once __DIR__ . '/../models/PoliceModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class PoliceController {

    private PoliceModel $policeModel;
    private JwtHandler  $jwt;

    // Allowed escalation status values — validated at controller
    // layer before reaching the ENUM column in the procedure.
    private const ALLOWED_ESCALATION_STATUSES = [
        'acknowledged',
        'dispatched',
        'closed',
        'no_action',
    ];

    public function __construct() {
        $this->policeModel = new PoliceModel();
        $this->jwt         = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/police/escalate
    //  Protected — guard, admin only.
    //
    //  Escalates a security incident to police. The procedure:
    //    - Verifies caller is a verified active guard or admin.
    //    - Confirms incident exists and is not soft-deleted.
    //    - Prevents duplicate escalations (one per incident).
    //    - Generates a non-predictable reference code:
    //        ESC-YYYY-[4 random hex]-[4 random hex]
    //    - Auto-updates incident status to 'under_review'.
    //    - Records escalation in audit_log.
    //  $notes is MANDATORY — guard must document why police
    //  involvement is required.
    // ----------------------------------------------------------
    public function escalate(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['guard', 'admin']);
        $body   = Request::json();

        // ---- Input validation --------------------------------
        $incident_id  = filter_var($body['incident_id']  ?? null, FILTER_VALIDATE_INT);
        $station_name = Request::sanitizeString($body['station_name'] ?? '');
        $notes        = Request::sanitizeString($body['notes']        ?? '');

        if ($incident_id === false || $incident_id === null || $incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        if (empty($station_name)) {
            Response::error('Police station name is required.', 400);
        }

        if (empty($notes)) {
            Response::error(
                'Escalation notes are required. Please document why police involvement is needed.',
                400
            );
        }

        // ---- Model call --------------------------------------
        $result = $this->policeModel->escalate(
            (int)$incident_id,
            $caller['user_id'],   // escalated_by — procedure verifies role
            $station_name,
            $notes
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')       ? 403
                  : (str_contains($result['message'], 'not found')          ? 404
                  : (str_contains($result['message'], 'already been escalated') ? 409 : 422));
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Incident escalated to police. Reference code generated.',
            201
        );
    }

    // ----------------------------------------------------------
    //  GET /api/police
    //  Protected — guard, admin only.
    //
    //  Returns all escalation records with optional date filters.
    //  resident_phone excluded from list view (data minimization
    //  — available in show() when the guard needs it for a
    //  specific escalation).
    //  cctv_url excluded from all escalation views.
    //  $date_from / $date_to: NULL = no date filter.
    // ----------------------------------------------------------
    public function index(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->policeModel->getAllEscalations(
            $caller['user_id'],
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Escalations retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/police/{id}
    //  Protected — guard, admin only.
    //
    //  Role-aware field disclosure enforced by the procedure:
    //    guard → resident_phone visible; cctv_url and
    //            guard_phone returned as NULL
    //    admin → all fields: resident_phone, cctv_url,
    //            guard_phone all returned
    //
    //  $caller['user_id'] passed as $requesting_user_id to
    //  enable the procedure's CASE expression for disclosure.
    // ----------------------------------------------------------
    public function show(int $escalation_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        if ($escalation_id <= 0) {
            Response::error('A valid escalation ID is required.', 400);
        }

        $result = $this->policeModel->getEscalationById(
            $caller['user_id'],   // requesting_user_id — drives field disclosure
            $escalation_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Escalation retrieved.');
    }

    // ----------------------------------------------------------
    //  PATCH /api/police/{id}/status
    //  Protected — admin only.
    //
    //  Updates the police response progress for an escalation.
    //  $new_status options:
    //    'acknowledged' — police confirmed receipt
    //    'dispatched'   — officers dispatched
    //    'closed'       — case closed by police
    //    'no_action'    — police took no action
    //
    //  SIDE EFFECT: When status is 'closed' or 'no_action',
    //  the procedure automatically updates the linked incident
    //  to 'resolved'. The response communicates this to the
    //  client so the incident management UI can refresh.
    //
    //  $update_notes is MANDATORY — admin must document
    //  the police response detail.
    // ----------------------------------------------------------
    public function updateStatus(int $escalation_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $new_status   = Request::sanitizeString($body['status']       ?? '');
        $update_notes = Request::sanitizeString($body['update_notes'] ?? '');

        if (!in_array($new_status, self::ALLOWED_ESCALATION_STATUSES, true)) {
            Response::error(
                "status must be one of: " .
                implode(', ', self::ALLOWED_ESCALATION_STATUSES) . '.',
                400
            );
        }

        if (empty($update_notes)) {
            Response::error(
                'Update notes are required when changing escalation status.',
                400
            );
        }

        if ($escalation_id <= 0) {
            Response::error('A valid escalation ID is required.', 400);
        }

        $result = $this->policeModel->updateEscalationStatus(
            $caller['user_id'],
            $escalation_id,
            $new_status,
            $update_notes
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        // Communicate automatic incident resolution side effect
        // when status is 'closed' or 'no_action'.
        $message = in_array($new_status, ['closed', 'no_action'], true)
            ? 'Escalation status updated. Linked incident automatically resolved.'
            : 'Escalation status updated.';

        Response::success($result['data'], $message);
    }

    // ----------------------------------------------------------
    //  PATCH /api/police/{id}/close
    //  Protected — admin only.
    //
    //  Formally closes a police escalation with a documented
    //  final outcome. Distinct from updateStatus() — this
    //  represents the definitive end of the escalation lifecycle.
    //
    //  SIDE EFFECT: Linked incident automatically set to
    //  'resolved' within the same DB transaction. Two
    //  audit_log entries written atomically:
    //    1. Escalation closure
    //    2. Incident resolution via escalation close
    //  Response communicates both actions to the client.
    //
    //  $outcome_notes is MANDATORY — admin must document
    //  the final police response outcome.
    // ----------------------------------------------------------
    public function close(int $escalation_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $outcome_notes = Request::sanitizeString($body['outcome_notes'] ?? '');

        if (empty($outcome_notes)) {
            Response::error(
                'Outcome notes are required. Please document the final police response before closing.',
                400
            );
        }

        if ($escalation_id <= 0) {
            Response::error('A valid escalation ID is required.', 400);
        }

        $result = $this->policeModel->closeEscalation(
            $caller['user_id'],
            $escalation_id,
            $outcome_notes
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'Escalation closed. Outcome documented. Linked incident resolved.'
        );
    }

    // ----------------------------------------------------------
    //  GET /api/police/incident/{incident_id}
    //  Protected — guard, admin only.
    //
    //  Returns escalation records linked to a specific incident.
    //  Used by the incident detail view to display whether an
    //  incident has been escalated and what the reference code is.
    //  resident_phone excluded — available via show() when needed.
    //  cctv_url excluded from escalation view entirely.
    // ----------------------------------------------------------
    public function getByIncident(int $incident_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->policeModel->getEscalationsByIncident(
            $caller['user_id'],
            $incident_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 500);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Escalations for incident retrieved.');
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
}